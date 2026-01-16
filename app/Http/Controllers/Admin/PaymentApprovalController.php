<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;

class PaymentApprovalController extends Controller
{
    // Get all pending payments with user data
    public function getPendingPayments()
    {
        $payments = Payment::with('user:id,name,email')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'payments' => $payments
        ]);
    }

    // Approve payment
    public function approve($id, Request $request)
    {
        $payment = Payment::with('user')->findOrFail($id);

        // Update payment
        $payment->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        // Create or update subscription
        Subscription::updateOrCreate(
            ['user_id' => $payment->user_id],
            [
                'start_date' => now(),
                'end_date' => now()->addYear(),
                'status' => 'active',
            ]
        );

        // Update user status
        $payment->user->update(['is_active' => true]);

        // Optional: Send notification email
        // $payment->user->notify(new PaymentApproved($payment));

        return response()->json([
            'success' => true,
            'message' => 'Payment approved and subscription activated.',
        ]);
    }

    // Deny payment
    public function deny($id, Request $request)
    {
        $payment = Payment::findOrFail($id);

        $payment->update([
            'status' => 'denied',
            'denied_by' => $request->user()->id,
            'denied_at' => now(),
            'denial_reason' => $request->input('reason', 'No reason provided'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment denied.',
        ]);
    }
}