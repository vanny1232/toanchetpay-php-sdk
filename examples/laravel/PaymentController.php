<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ToanchetPayService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use ToanchetPay\Exceptions\ApiException;
use ToanchetPay\Exceptions\HttpException;

class PaymentController extends Controller
{
    private ToanchetPayService $toanchetpay;

    public function __construct(ToanchetPayService $toanchetpay)
    {
        $this->toanchetpay = $toanchetpay;
    }

    /** POST /payment/checkout — initiates payment and redirects the browser. */
    public function checkout(Request $request): Response
    {
        $request->validate([
            'order_id' => 'required|string',
            'amount'   => 'required|numeric|min:0.01',
        ]);

        try {
            $html = $this->toanchetpay->initiatePayment(
                txid:        $request->input('order_id'),
                amount:      (float) $request->input('amount'),
                description: 'Order #' . $request->input('order_id'),
                successUrl:  route('payment.success'),
                errorUrl:    route('payment.failed')
            );

            return response($html, 200)->header('Content-Type', 'text/html');

        } catch (ApiException $e) {
            return response("Payment gateway error: {$e->getApiMessage()}", 502);
        } catch (HttpException $e) {
            return response("Network error. Please try again.", 503);
        }
    }

    /** GET /payment/success — ACLEDA redirects here after success. */
    public function success(Request $request)
    {
        $paymentTokenId = $request->query('paymenttokenid', $request->input('paymentTokenid', ''));

        if (!$paymentTokenId) {
            return redirect()->route('home')->with('error', 'Missing payment token.');
        }

        try {
            $status = $this->toanchetpay->verifyPayment($paymentTokenId);

            if ($status->isSuccess() && $status->isConfirmed()) {
                // Update your order in the database here.
                return view('payment.success', ['status' => $status]);
            }

            return redirect()->route('home')->with('error', 'Payment not confirmed.');

        } catch (ApiException $e) {
            return redirect()->route('home')->with('error', $e->getApiMessage());
        }
    }

    /** GET /payment/failed — ACLEDA redirects here on failure. */
    public function failed(Request $request)
    {
        return view('payment.failed');
    }
}
