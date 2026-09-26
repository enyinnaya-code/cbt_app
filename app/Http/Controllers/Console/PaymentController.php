<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Orders and bank transfers waiting to be checked. Admins only (see routes). */
class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:' . implode(',', [Order::AWAITING, Order::PENDING, Order::PAID, Order::FAILED, Order::REJECTED, Order::CANCELLED])],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $orders = Order::query()->with(['user:id,name,email', 'exam:id,name'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('reference', 'like', '%' . addcslashes($v, '%_\\') . '%')
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', '%' . addcslashes($v, '%_\\') . '%')->orWhere('name', 'like', '%' . addcslashes($v, '%_\\') . '%'))))
            // Transfers waiting for a decision come first.
            ->orderByRaw('CASE status WHEN ? THEN 0 ELSE 1 END', [Order::AWAITING])->latest('id')
            ->paginate(25)->withQueryString();

        return view('console.payments.index', [
            'orders' => $orders,
            'filters' => $filters,
            'waiting' => Order::where('status', Order::AWAITING)->count(),
            'revenue' => [
                'month' => (int) Order::where('status', Order::PAID)->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
                'total' => (int) Order::where('status', Order::PAID)->sum('amount'),
            ],
        ]);
    }

    public function show(Order $order)
    {
        return view('console.payments.show', ['order' => $order->load(['user:id,name,email,phone', 'exam:id,name', 'items.subject:id,name', 'decider:id,name'])]);
    }

    /** Confirms that the money arrived and opens the subjects. */
    public function approve(Request $request, Order $order, OrderService $orders)
    {
        if ($order->isPaid()) { return back()->with('error', 'This order is already paid.'); }

        $orders->markPaid($order, $request->user());

        return redirect()->route('console.payments.show', $order)->with('success', 'Approved. The student now has access.');
    }

    /** Only a transfer that has not been paid can be turned down. The student sees the reason. */
    public function reject(Request $request, Order $order)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']], ['reason.required' => 'Tell the student why, so they know what to do next.']);

        if ($order->isPaid()) { return back()->with('error', 'A paid order cannot be rejected.'); }

        $order->update(['status' => Order::REJECTED, 'decision_note' => trim($data['reason']), 'decided_by' => $request->user()->id]);

        return redirect()->route('console.payments.show', $order)->with('success', 'Marked as not confirmed. The student can see your reason.');
    }

    /** The receipt the student uploaded. It sits outside the public folder, so only admins can open it. */
    public function proof(Order $order)
    {
        abort_unless($order->proof_path && Storage::disk('local')->exists($order->proof_path), 404);

        return Storage::disk('local')->response($order->proof_path);
    }
}
