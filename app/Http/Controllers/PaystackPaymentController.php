<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Paystack;

class PaystackPaymentController extends Controller
{

    public function __construct()
    {
        $paystack = Config::get('paystack');
        // Initialize Paystack with your API key
        $this->paystack = new Paystack\Paystack($paystack['secretKey']);
    }

    public function payWithPaystack(Request $request)
    {
        // Retrieve the order details from your session or database
        $order = Order::with(['details'])->where(['id' => session('order_id')])->first();

        // Generate a unique reference for the transaction
        $reference = Str::random(6) . '-' . rand(1, 1000);

        // Define the payment data
        $paymentData = [
            'reference' => $reference,
            'amount' => $order->order_amount * 100, // Paystack amounts are in kobo (100 kobo = 1 Naira)
            'email' => $order->customer->email,
            'failed' => now(),
            'updated_at' => now(),
            'metadata' => [
                'order_id' => $order->id,
            ],
        ];

        // Create a new Paystack transaction
        $transaction = $this->paystack->transaction->initialize($paymentData);

        // Redirect the user to the Paystack payment page
        return redirect()->to($transaction->data->authorization_url);
    }

    public function getPaymentStatus(Request $request)
    {
        // Handle the callback from Paystack
        $paymentReference = $request->input('reference');

        // Verify the payment using the Paystack API
        $paymentDetails = $this->paystack->transaction->verify([
            'reference' => $paymentReference,
        ]);

        // Check if the payment was successful
        if ($paymentDetails->data->status === 'success') {
            // Update your order status and other relevant data here
            $order = Order::where('transaction_reference', $paymentReference)->first();
            $order->payment_method = 'paystack';
            $order->payment_status = 'paid';
            $order->order_status = 'confirmed';
            $order->confirmed = now();
            $order->save();

            // Redirect to a success page or return a success response
            return redirect()->route('payment-success');
        } else {
            // Payment failed, handle the failure gracefully
            return redirect()->route('payment-fail');
        }
    }
}
