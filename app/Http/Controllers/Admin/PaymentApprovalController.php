<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

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
            $emailSent = $this->sendApprovalEmail($payment, $subscription);

            return response()->json([
                'success' => true,
                'message' => 'Payment approved and subscription activated.',
                'data' => [
                    'payment_id' => $payment->id,
                    'user_id' => $payment->user_id,
                    'subscription_end' => $subscription->end_date,
                    'email_sent' => $emailSent,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Payment approval failed: ' . $e->getMessage());
            
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
            $emailSent = $this->sendDenialEmail($payment, $denialReason);

            return response()->json([
                'success' => true,
                'message' => 'Payment denied successfully.',
                'data' => [
                    'payment_id' => $payment->id,
                    'user_id' => $payment->user_id,
                    'denial_reason' => $denialReason,
                    'email_sent' => $emailSent,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Payment denial failed: ' . $e->getMessage());
            
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
    private function sendApprovalEmail(Payment $payment, Subscription $subscription)
    {
        try {
            $user = $payment->user;
            
            Log::info('Attempting to send approval email to: ' . $user->email);
            Log::info('User exists: ' . ($user ? 'Yes' : 'No'));
            
            $mailData = [
                'user_name' => $user->name,
                'payment_amount' => number_format($payment->amount, 2),
                'payment_currency' => $payment->currency ?? 'USD',
                'payment_method' => $payment->method ?? 'Bank Transfer',
                'payment_reference' => $payment->reference ?? 'PAY-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT),
                'payment_date' => $payment->created_at->format('F j, Y'),
                'approval_date' => now()->format('F j, Y'),
                'subscription_start' => $subscription->start_date->format('F j, Y'),
                'subscription_end' => $subscription->end_date->format('F j, Y'),
                'subscription_duration' => '1 Year',
                'app_url' => config('app.url', 'https://redatlearninghub.com'),
            ];

            Log::info('Mail data prepared for approval email');
            Log::info('View exists check: ' . (view()->exists('emails.payment-approved') ? 'Yes' : 'No'));

            // Check if view exists first
            if (!view()->exists('emails.payment-approved')) {
                Log::error('Email template not found: emails.payment-approved');
                
                // Create a simple text email as fallback
                Mail::raw("Hello {$user->name},\n\nYour payment of {$payment->amount} has been approved.\n\nSubscription activated until: {$subscription->end_date->format('F j, Y')}\n\nThank you,\nRedat Learning Hub", 
                    function ($message) use ($user) {
                        $message->to($user->email)
                                ->subject('Payment Approved - Redat Learning Hub');
                    });
                
                Log::info('Fallback text email sent to: ' . $user->email);
                return true;
            }

            // Use the view template
            Mail::send('emails.payment-approved', $mailData, function ($message) use ($user) {
                $message->to($user->email)
                        ->subject('Payment Approved - Your Subscription is Now Active | Redat Learning Hub');
                
                Log::info('Email sent via send method to: ' . $user->email);
            });

            Log::info('Payment approval email sent to: ' . $user->email);
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to send approval email: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
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
            
            Log::info('Attempting to send denial email to: ' . $user->email);
            
            $mailData = [
                'user_name' => $user->name,
                'payment_amount' => number_format($payment->amount, 2),
                'payment_currency' => $payment->currency ?? 'USD',
                'payment_method' => $payment->method ?? 'Bank Transfer',
                'payment_reference' => $payment->reference ?? 'PAY-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT),
                'payment_date' => $payment->created_at->format('F j, Y'),
                'denial_date' => now()->format('F j, Y'),
                'denial_reason' => $denialReason,
                'support_contact' => config('mail.support_email', 'support@redatlearninghub.com'),
                'retry_instructions' => 'Please review your payment details and submit a new payment request if needed.',
                'app_url' => config('app.url', 'https://redatlearninghub.com'),
            ];

            Log::info('View exists check: ' . (view()->exists('emails.payment-denied') ? 'Yes' : 'No'));

            // Check if view exists first
            if (!view()->exists('emails.payment-denied')) {
                Log::error('Email template not found: emails.payment-denied');
                
                // Create a simple text email as fallback
                Mail::raw("Hello {$user->name},\n\nYour payment of {$payment->amount} has been denied.\n\nReason: {$denialReason}\n\nPlease review and submit a new payment if needed.\n\nThank you,\nRedat Learning Hub", 
                    function ($message) use ($user) {
                        $message->to($user->email)
                                ->subject('Payment Denied - Redat Learning Hub');
                    });
                
                Log::info('Fallback text email sent to: ' . $user->email);
                return true;
            }

            // Use the view template
            Mail::send('emails.payment-denied', $mailData, function ($message) use ($user) {
                $message->to($user->email)
                        ->subject('Payment Requires Attention - Action Required | Redat Learning Hub');
            });

            Log::info('Payment denial email sent to: ' . $user->email);
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to send denial email: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }
}