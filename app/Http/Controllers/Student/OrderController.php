<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\BankTransfer;
use App\Services\Purchases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    /** My purchases, and what is unlocked until when. */
    public function index(Request $request)
    {
        $purchases = new Purchases($request->user());

        return view('student.orders', [
            'orders' => $purchases->orders(),
            'unlocked' => $purchases->unlocked(),
            'remindDays' => Purchases::REMIND_DAYS,
        ]);
    }

    /** A printable receipt for a payment that went through. Only the person who paid can open it. */
    public function receipt(Request $request, string $reference)
    {
        $order = $this->mine($request, $reference);
        abort_unless($order->isPaid(), 404);

        return view('student.receipt', [
            'order' => $order->load(['exam:id,name', 'items.subject:id,name']),
            'user' => $request->user(),
            'until' => $order->paid_at->copy()->addDays($order->access_days),
        ]);
    }

    public function show(Request $request, string $reference)
    {
        $order = $this->mine($request, $reference);

        return view('student.order', [
            'order' => $order->load(['exam:id,name', 'items.subject:id,name']),
            'bank' => $order->method === Order::BANK && $order->isOpen() ? BankTransfer::details() : null,
        ]);
    }

    /** "I have paid": the student tells us who sent the money and may attach the receipt, then an admin checks it. */
    public function submitProof(Request $request, string $reference)
    {
        $order = $this->mine($request, $reference);
        abort_unless($order->method === Order::BANK && $order->isOpen(), 404);

        $data = $request->validate([
            'payer_name' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
        ], ['proof.max' => 'The receipt must be 4 MB or smaller.', 'proof.mimes' => 'The receipt must be a picture (JPG, PNG, WebP) or a PDF.']);

        $path = $request->hasFile('proof') ? $request->file('proof')->store('proofs', 'local') : $order->proof_path;
        if ($request->hasFile('proof') && $order->proof_path) { Storage::disk('local')->delete($order->proof_path); }

        $order->update([
            'payer_name' => trim($data['payer_name']), 'note' => $data['note'] ?? null, 'proof_path' => $path,
            'status' => Order::AWAITING, 'submitted_at' => now(),
        ]);

        return redirect()->route('orders.show', $order->reference)->with('success', 'Thank you. We will check your transfer and unlock your subjects, usually within a few hours.');
    }

    /** Give up on an order that has not been paid. */
    public function cancel(Request $request, string $reference)
    {
        $order = $this->mine($request, $reference);
        abort_unless($order->isOpen(), 404);

        $order->update(['status' => Order::CANCELLED]);

        return redirect()->route('orders.index')->with('success', 'Order cancelled.');
    }

    private function mine(Request $request, string $reference): Order
    {
        return Order::where('reference', $reference)->where('user_id', $request->user()->id)->firstOrFail();
    }
}
