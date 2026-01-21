<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Mail\PaymentApproved;
use App\Mail\PaymentDenied;

class PaymentApprovalController extends Controller
{
    
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

    // Approve payment - SIMPLIFIED VERSION
    public function approve($id, Request $request)
    {
        DB::beginTransaction();
        
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

            DB::commit();

            // Send email EXACTLY like registration
            $emailSent = $this->sendPaymentEmail('approved', $payment, $subscription);

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
            DB::rollBack();
            
            Log::error('Payment approval failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve payment. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    // Deny payment - SIMPLIFIED VERSION
    public function deny($id, Request $request)
    {
        DB::beginTransaction();
        
        try {
            $payment = Payment::with('user')->findOrFail($id);

            $denialReason = $request->input('reason', 'No reason provided');
            
            $payment->update([
                'status' => 'denied',
                'denied_by' => $request->user()->id,
                'denied_at' => now(),
                'denial_reason' => $denialReason,
            ]);

            DB::commit();

            // Send email EXACTLY like registration
            $emailSent = $this->sendPaymentEmail('denied', $payment, null, $denialReason);

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
            DB::rollBack();
            
            Log::error('Payment denial failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to deny payment. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Send payment email - EXACTLY like registration email sending
     */
    private function sendPaymentEmail($type, Payment $payment, $subscription = null, $denialReason = null)
    {
        try {
            $user = $payment->user;
            
            Log::info('Attempting to send payment ' . $type . ' email', [
                'to' => $user->email,
                'user_id' => $user->id,
                'payment_id' => $payment->id
            ]);
            
            // Prepare mail data
            $mailData = [
                'user_name' => $user->name,
                'payment_amount' => number_format($payment->amount, 2),
                'payment_currency' => $payment->currency ?? 'USD',
                'payment_method' => $payment->method ?? 'Bank Transfer',
                'payment_reference' => $payment->reference ?? 'PAY-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT),
                'payment_date' => $payment->created_at->format('F j, Y'),
                'app_url' => config('app.url', 'https://redatlearninghub.com'),
                'support_contact' => config('mail.support_email', 'support@redatlearninghub.com'),
            ];
            
            // Add type-specific data
            if ($type === 'approved' && $subscription) {
                $mailData['approval_date'] = now()->format('F j, Y');
                $mailData['subscription_start'] = Carbon::parse($subscription->start_date)->format('F j, Y');
                $mailData['subscription_end'] = Carbon::parse($subscription->end_date)->format('F j, Y');
                $mailData['subscription_duration'] = '1 Year';
                
                $subject = 'Payment Approved - Redat Learning Hub';
                $mailable = new PaymentApproved($mailData);
            } else {
                $mailData['denial_date'] = now()->format('F j, Y');
                $mailData['denial_reason'] = $denialReason;
                $mailData['retry_instructions'] = 'Please review your payment details and submit a new payment request if needed.';
                
                $subject = 'Payment Requires Attention - Redat Learning Hub';
                $mailable = new PaymentDenied($mailData);
            }
            
            // Set subject
            $mailable->subject($subject);
            
            // Try to queue email first (EXACTLY like registration)
            try {
                Log::info('Queueing payment ' . $type . ' email');
                Mail::to($user->email)->queue($mailable);
                
                Log::info('Payment ' . $type . ' email queued successfully');
                return true;
                
            } catch (\Exception $queueException) {
                Log::warning('Payment ' . $type . ' email queue failed, trying immediate send', [
                    'error' => $queueException->getMessage()
                ]);
                
                // Fallback to immediate send (EXACTLY like registration)
                try {
                    Log::info('Attempting immediate payment ' . $type . ' email send');
                    Mail::to($user->email)->send($mailable);
                    
                    Log::info('Payment ' . $type . ' email sent immediately');
                    return true;
                    
                } catch (\Exception $sendException) {
                    Log::error('Both queue and immediate send failed for payment ' . $type, [
                        'queue_error' => $queueException->getMessage(),
                        'send_error' => $sendException->getMessage()
                    ]);
                    
                    // Final fallback: simple text email
                    return $this->sendSimplePaymentEmail($type, $payment, $subscription, $denialReason);
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Complete payment ' . $type . ' email process failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Final fallback
            try {
                return $this->sendSimplePaymentEmail($type, $payment, $subscription, $denialReason);
            } catch (\Exception $simpleException) {
                Log::error('Even simple payment ' . $type . ' email failed', [
                    'error' => $simpleException->getMessage()
                ]);
                return false;
            }
        }
    }

    /**
     * Simple fallback email (like password reset)
     */
    private function sendSimplePaymentEmail($type, Payment $payment, $subscription = null, $denialReason = null)
    {
        try {
            $user = $payment->user;
            
            if ($type === 'approved') {
                $subject = 'Payment Approved - Redat Learning Hub';
                $message = "Hello {$user->name},\n\n" .
                          "Your payment of {$payment->amount} {$payment->currency} has been approved.\n\n" .
                          "Your subscription is now active until: " . 
                          Carbon::parse($subscription->end_date)->format('F j, Y') . "\n\n" .
                          "Thank you for choosing Redat Learning Hub!";
            } else {
                $subject = 'Payment Denied - Redat Learning Hub';
                $message = "Hello {$user->name},\n\n" .
                          "Your payment of {$payment->amount} {$payment->currency} has been denied.\n\n" .
                          "Reason: {$denialReason}\n\n" .
                          "Please submit a new payment request if needed.\n\n" .
                          "Thank you,\nRedat Learning Hub";
            }
            
            Mail::raw($message, function ($message) use ($user, $subject) {
                $message->to($user->email)
                        ->subject($subject);
            });
            
            Log::info('Simple payment ' . $type . ' email sent to: ' . $user->email);
            return true;
            
        } catch (\Exception $e) {
            Log::error('Simple payment ' . $type . ' email also failed: ' . $e->getMessage());
            return false;
        }
    }
}