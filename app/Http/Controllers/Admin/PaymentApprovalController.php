<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\PaymentApproved;
use App\Mail\PaymentDenied;

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
        try {
            $payment = Payment::with('user')->findOrFail($id);

            // Update payment
            $payment->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            // Create or update subscription
            $subscription = Subscription::updateOrCreate(
                ['user_id' => $payment->user_id],
                [
                    'start_date' => now(),
                    'end_date' => now()->addYear(),
                    'status' => 'active',
                ]
            );

            // Update user status
            $payment->user->update(['is_active' => true]);

            // Send approval notification email
            $this->sendApprovalEmail($payment);

            return response()->json([
                'success' => true,
                'message' => 'Payment approved and subscription activated.',
                'data' => [
                    'payment_id' => $payment->id,
                    'user_id' => $payment->user_id,
                    'subscription_end' => $subscription->end_date,
                    'email_sent' => true,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Payment approval failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve payment. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    // Deny payment
    public function deny($id, Request $request)
    {
        try {
            $payment = Payment::with('user')->findOrFail($id);

            $denialReason = $request->input('reason', 'No reason provided');
            
            $payment->update([
                'status' => 'denied',
                'denied_by' => $request->user()->id,
                'denied_at' => now(),
                'denial_reason' => $denialReason,
            ]);

            // Send denial notification email
            $this->sendDenialEmail($payment, $denialReason);

            return response()->json([
                'success' => true,
                'message' => 'Payment denied successfully.',
                'data' => [
                    'payment_id' => $payment->id,
                    'user_id' => $payment->user_id,
                    'denial_reason' => $denialReason,
                    'email_sent' => true,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Payment denial failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to deny payment. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Send payment approval email
     */
    private function sendApprovalEmail(Payment $payment)
    {
        try {
            $user = $payment->user;
            $subscription = Subscription::where('user_id', $user->id)->first();
            
            $mailData = [
                'user_name' => $user->name,
                'payment_amount' => number_format($payment->amount, 2),
                'payment_currency' => $payment->currency ?? 'USD',
                'payment_method' => $payment->method ?? 'Bank Transfer',
                'payment_reference' => $payment->reference ?? $payment->id,
                'payment_date' => $payment->created_at->format('F j, Y'),
                'approval_date' => now()->format('F j, Y'),
                'subscription_start' => $subscription->start_date->format('F j, Y'),
                'subscription_end' => $subscription->end_date->format('F j, Y'),
                'subscription_duration' => '1 Year',
                'account_activated' => true,
            ];

            // Check if we should use Mailable class or direct email
            if (class_exists(\App\Mail\PaymentApproved::class)) {
                Mail::to($user->email)->send(new \App\Mail\PaymentApproved($payment, $mailData));
            } else {
                // Fallback to simple email
                Mail::send('emails.payment-approved', $mailData, function ($message) use ($user) {
                    $message->to($user->email)
                            ->subject('Payment Approved - Your Subscription is Now Active');
                });
            }

            \Log::info('Payment approval email sent to: ' . $user->email);
            return true;
            
        } catch (\Exception $e) {
            \Log::error('Failed to send approval email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send payment denial email
     */
    private function sendDenialEmail(Payment $payment, $denialReason)
    {
        try {
            $user = $payment->user;
            
            $mailData = [
                'user_name' => $user->name,
                'payment_amount' => number_format($payment->amount, 2),
                'payment_currency' => $payment->currency ?? 'USD',
                'payment_method' => $payment->method ?? 'Bank Transfer',
                'payment_reference' => $payment->reference ?? $payment->id,
                'payment_date' => $payment->created_at->format('F j, Y'),
                'denial_date' => now()->format('F j, Y'),
                'denial_reason' => $denialReason,
                'support_contact' => config('mail.support_email', 'support@redatlearninghub.com'),
                'retry_instructions' => 'Please review your payment details and submit a new payment request if needed.',
            ];

            // Check if we should use Mailable class or direct email
            if (class_exists(\App\Mail\PaymentDenied::class)) {
                Mail::to($user->email)->send(new \App\Mail\PaymentDenied($payment, $denialReason, $mailData));
            } else {
                // Fallback to simple email
                Mail::send('emails.payment-denied', $mailData, function ($message) use ($user) {
                    $message->to($user->email)
                            ->subject('Payment Review Update - Action Required');
                });
            }

            \Log::info('Payment denial email sent to: ' . $user->email);
            return true;
            
        } catch (\Exception $e) {
            \Log::error('Failed to send denial email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get payment statistics for dashboard
     */
    public function getPaymentStats()
    {
        $stats = [
            'total_pending' => Payment::where('status', 'pending')->count(),
            'total_approved' => Payment::where('status', 'approved')->count(),
            'total_denied' => Payment::where('status', 'denied')->count(),
            'pending_amount' => Payment::where('status', 'pending')->sum('amount'),
            'approved_amount' => Payment::where('status', 'approved')->sum('amount'),
            'total_users' => User::count(),
            'active_subscriptions' => Subscription::where('status', 'active')->count(),
            'expiring_soon' => Subscription::where('end_date', '<=', now()->addDays(30))->count(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Get all payments with filters
     */
    public function getAllPayments(Request $request)
    {
        $query = Payment::with(['user:id,name,email', 'approver:id,name', 'denier:id,name']);
        
        // Apply filters
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        // Sort
        $sortField = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortField, $sortOrder);
        
        // Paginate
        $perPage = $request->get('per_page', 15);
        $payments = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'payments' => $payments,
            'filters' => $request->all(),
            'total' => $payments->total(),
        ]);
    }
}