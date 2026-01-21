<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PaymentApprovalController extends Controller
{
    // Get all pending payments with user data
    public function getPendingPayments()
    {
        try {
            $payments = Payment::with('user:id,name,email')
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'payments' => $payments
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch pending payments: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load payments',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
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
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
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
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
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
            
            // Check if the email template exists
            $templateExists = view()->exists('emails.payment-approved');
            Log::info('Template emails.payment-approved exists: ' . ($templateExists ? 'YES' : 'NO'));
            
            // Ensure dates are Carbon instances
            $subscriptionStart = $subscription->start_date instanceof \Carbon\Carbon 
                ? $subscription->start_date 
                : Carbon::parse($subscription->start_date);
                
            $subscriptionEnd = $subscription->end_date instanceof \Carbon\Carbon 
                ? $subscription->end_date 
                : Carbon::parse($subscription->end_date);
            
            $mailData = [
                'user_name' => $user->name,
                'payment_amount' => number_format($payment->amount, 2),
                'payment_currency' => $payment->currency ?? 'USD',
                'payment_method' => $payment->method ?? 'Bank Transfer',
                'payment_reference' => $payment->reference ?? 'PAY-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT),
                'payment_date' => $payment->created_at->format('F j, Y'),
                'approval_date' => now()->format('F j, Y'),
                'subscription_start' => $subscriptionStart->format('F j, Y'),
                'subscription_end' => $subscriptionEnd->format('F j, Y'),
                'subscription_duration' => '1 Year',
                'app_url' => config('app.url', 'https://redatlearninghub.com'),
            ];

            Log::info('Mail data prepared: ' . json_encode($mailData));

            // Send email using the template
            Mail::send('emails.payment-approved', $mailData, function ($message) use ($user) {
                $message->to($user->email)
                        ->subject('Payment Approved - Your Subscription is Now Active | Redat Learning Hub');
                
                Log::info('Mail function called for: ' . $user->email);
            });

            // Check if mail was actually sent
            if (count(Mail::failures()) > 0) {
                Log::error('Failed to send approval email to: ' . $user->email);
                Log::error('Mail failures: ' . json_encode(Mail::failures()));
                return false;
            }

            Log::info('Payment approval email successfully sent to: ' . $user->email);
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
            
            // Check if the email template exists
            $templateExists = view()->exists('emails.payment-denied');
            Log::info('Template emails.payment-denied exists: ' . ($templateExists ? 'YES' : 'NO'));
            
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

            Log::info('Mail data prepared: ' . json_encode($mailData));

            // Send email using the template
            Mail::send('emails.payment-denied', $mailData, function ($message) use ($user) {
                $message->to($user->email)
                        ->subject('Payment Requires Attention - Action Required | Redat Learning Hub');
            });

            // Check if mail was actually sent
            if (count(Mail::failures()) > 0) {
                Log::error('Failed to send denial email to: ' . $user->email);
                Log::error('Mail failures: ' . json_encode(Mail::failures()));
                return false;
            }

            Log::info('Payment denial email successfully sent to: ' . $user->email);
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to send denial email: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Simplified version that will definitely work
     */
    private function sendSimpleApprovalEmail(Payment $payment, Subscription $subscription)
    {
        try {
            $user = $payment->user;
            
            $message = "Hello {$user->name},\n\n" .
                      "Your payment of {$payment->amount} {$payment->currency} has been approved!\n\n" .
                      "Your subscription is now active until: " . 
                      ($subscription->end_date instanceof \Carbon\Carbon 
                        ? $subscription->end_date->format('F j, Y') 
                        : date('F j, Y', strtotime($subscription->end_date))) . "\n\n" .
                      "Thank you for choosing Redat Learning Hub!";
            
            Mail::raw($message, function ($message) use ($user) {
                $message->to($user->email)
                        ->subject('Payment Approved - Redat Learning Hub');
            });
            
            Log::info('Simple approval email sent to: ' . $user->email);
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to send simple approval email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Simplified version that will definitely work
     */
    private function sendSimpleDenialEmail(Payment $payment, $denialReason)
    {
        try {
            $user = $payment->user;
            
            $message = "Hello {$user->name},\n\n" .
                      "Your payment of {$payment->amount} {$payment->currency} has been denied.\n\n" .
                      "Reason: {$denialReason}\n\n" .
                      "Please submit a new payment request if needed.\n\n" .
                      "Thank you,\nRedat Learning Hub";
            
            Mail::raw($message, function ($message) use ($user) {
                $message->to($user->email)
                        ->subject('Payment Denied - Redat Learning Hub');
            });
            
            Log::info('Simple denial email sent to: ' . $user->email);
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to send simple denial email: ' . $e->getMessage());
            return false;
        }
    }
}