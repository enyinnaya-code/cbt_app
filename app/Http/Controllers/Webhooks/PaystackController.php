<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\CheckoutController;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\Payments\Paystack;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaystackController extends Controller
{
    /**
     * Paystack calls this when a payment succeeds, even if the student closed the browser first.
     * The signature proves the call is Paystack's; the amount and reference are then checked against our own order.
     */
    public function __invoke(Request $request, OrderService $orders): Response
    {
        if (! Paystack::validSignature($request->getContent(), $request->header('x-paystack-signature'))) {
            return response('Invalid signature', 401);
        }

        $event = $request->json('event');
        $data = (array) $request->json('data', []);

        if ($event === 'charge.success' && ! empty($data['reference'])) {
            $order = Order::where('reference', $data['reference'])->where('method', Order::PAYSTACK)->first();
            if ($order) { CheckoutController::settle($order, $data, $orders); }
        }

        return response('OK', 200);   // always 200 for a genuine call, so Paystack stops retrying
    }
}
