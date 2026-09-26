<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\Payments\BankTransfer;
use App\Services\Payments\Paystack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(private OrderService $orders) {}

    /** Choose the subjects to unlock and how to pay. */
    public function show(Request $request)
    {
        $user = $request->user();
        $exams = Exam::where('is_active', true)->orderBy('sort_order')->get();

        $exam = $exams->firstWhere('slug', $request->query('exam'))
            ?? $exams->firstWhere('slug', $user->target_exam)
            ?? $exams->first();

        $catalog = $exam ? $this->orders->catalog($user, $exam) : collect();
        $picked = collect((array) $request->query('subjects', []))->map(fn ($s) => (string) $s)->all();

        return view('student.checkout', [
            'exams' => $exams,
            'exam' => $exam,
            'catalog' => $catalog,
            'picked' => $picked,
            'bundle' => $exam && $exam->bundle_price ? $exam->bundle_price : null,
            'methods' => ['paystack' => Paystack::configured(), 'bank_transfer' => BankTransfer::configured()],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'exam' => ['required', Rule::exists('exams', 'slug')],
            'subjects' => ['nullable', 'array', 'max:40'],
            'subjects.*' => ['string', 'max:100'],
            'bundle' => ['nullable', 'boolean'],
            'method' => ['required', Rule::in([Order::PAYSTACK, Order::BANK])],
        ]);

        if ($data['method'] === Order::PAYSTACK && ! Paystack::configured()) {
            return back()->withInput()->with('error', 'Online payment is not available right now. Please pay by bank transfer.');
        }
        if ($data['method'] === Order::BANK && ! BankTransfer::configured()) {
            return back()->withInput()->with('error', 'Bank transfer is not available right now.');
        }

        $exam = Exam::where('slug', $data['exam'])->where('is_active', true)->firstOrFail();
        $order = $this->orders->create($request->user(), $exam, $data['subjects'] ?? [], (bool) ($data['bundle'] ?? false), $data['method']);

        if ($order->method === Order::BANK) {
            return redirect()->route('orders.show', $order->reference);
        }

        try {
            $url = app(Paystack::class)->initialize($order, $request->user()->email, route('checkout.callback'));
        } catch (Throwable $e) {
            Log::warning('Paystack initialize failed', ['order' => $order->reference, 'error' => $e->getMessage()]);
            $order->update(['status' => Order::FAILED]);

            return redirect()->route('checkout.show', ['exam' => $exam->slug])->with('error', 'We could not reach the payment page. Nothing was charged. Please try again, or pay by bank transfer.');
        }

        return redirect()->away($url);
    }

    /**
     * Where Paystack sends the student back to. The redirect itself proves nothing, so ask Paystack what happened.
     * A slow or missing answer is not a failure: the webhook opens the subjects as soon as the payment is confirmed.
     */
    public function callback(Request $request)
    {
        $reference = (string) $request->query('reference', $request->query('trxref', ''));
        $order = Order::where('reference', $reference)->where('method', Order::PAYSTACK)->first();

        if (! $order) { return redirect()->route('pricing')->with('error', 'We could not find that payment.'); }

        $result = self::settle($order, app(Paystack::class)->verify($order->reference), $this->orders);

        return redirect()->route('orders.show', $order->reference)->with($result['flash'], $result['message']);
    }

    /**
     * Applies what Paystack says about a payment to an order. Used by the redirect and the webhook alike.
     *
     * @param  array<string,mixed>|null  $transaction  Paystack's transaction data, or null when it could not be reached
     * @return array{flash:string,message:string}
     */
    public static function settle(Order $order, ?array $transaction, OrderService $orders): array
    {
        if ($order->isPaid()) { return ['flash' => 'success', 'message' => 'Payment received. Your subjects are unlocked.']; }
        if ($transaction === null) { return ['flash' => 'error', 'message' => 'We could not confirm your payment just yet. If you were charged, your subjects will unlock within a few minutes.']; }

        $status = $transaction['status'] ?? '';

        if ($status === 'success') {
            $matches = (int) ($transaction['amount'] ?? -1) === $order->amount * 100
                && strtoupper((string) ($transaction['currency'] ?? '')) === $order->currency
                && (string) ($transaction['reference'] ?? '') === $order->reference;

            if (! $matches) {
                Log::error('Paystack payment does not match the order', ['order' => $order->reference, 'transaction' => $transaction]);

                return ['flash' => 'error', 'message' => 'Your payment did not match the order, so we have not unlocked anything. Please contact support with your reference ' . $order->reference . '.'];
            }

            $orders->markPaid($order, null, ['paystack' => ['id' => $transaction['id'] ?? null, 'channel' => $transaction['channel'] ?? null, 'paid_at' => $transaction['paid_at'] ?? null]]);

            return ['flash' => 'success', 'message' => 'Payment received. Your subjects are unlocked.'];
        }

        if (in_array($status, ['failed', 'abandoned', 'reversed'], true) && $order->status === Order::PENDING) {
            $order->update(['status' => Order::FAILED]);
        }

        return ['flash' => 'error', 'message' => 'The payment was not completed. You have not been charged.'];
    }
}
