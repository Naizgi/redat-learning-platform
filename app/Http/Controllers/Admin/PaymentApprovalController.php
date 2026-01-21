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

            // Send approval notification email - USING THE SAME PATTERN AS REGISTRATION
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
            DB::rollBack();
            
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

            // Send denial notification email - USING THE SAME PATTERN AS REGISTRATION
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
            DB::rollBack();
            
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
     * Send payment approval email - USING REGISTRATION PATTERN
     */
    private function sendApprovalEmail(Payment $payment, Subscription $subscription)
    {
        try {
            $user = $payment->user;
            
            Log::info('Attempting to send payment approval email', [
                'to' => $user->email,
                'user_id' => $user->id,
                'payment_id' => $payment->id
            ]);
            
            // Prepare mail data (similar to how SendOtpMail works)
            $mailData = [
                'user_name' => $user->name,
                'payment_amount' => number_format($payment->amount, 2),
                'payment_currency' => $payment->currency ?? 'USD',
                'payment_method' => $payment->method ?? 'Bank Transfer',
                'payment_reference' => $payment->reference ?? 'PAY-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT),
                'payment_date' => $payment->created_at->format('F j, Y'),
                'approval_date' => now()->format('F j, Y'),
                'subscription_start' => Carbon::parse($subscription->start_date)->format('F j, Y'),
                'subscription_end' => Carbon::parse($subscription->end_date)->format('F j, Y'),
                'subscription_duration' => '1 Year',
                'app_url' => config('app.url', 'https://redatlearninghub.com'),
            ];
            
            Log::info('Payment approval mail data prepared', [
                'user_email' => $user->email,
                'data_keys' => array_keys($mailData)
            ]);
            
            // Check if Mailable class exists
            if (class_exists(\App\Mail\PaymentApproved::class)) {
                Log::info('Using PaymentApproved Mailable class');
                
                // Try to queue email first (like registration)
                try {
                    Mail::to($user->email)
                        ->queue(new \App\Mail\PaymentApproved($mailData));
                    
                    Log::info('Payment approval email queued successfully');
                    return true;
                    
                } catch (\Exception $queueException) {
                    Log::warning('Payment approval email queue failed, trying immediate send', [
                        'error' => $queueException->getMessage()
                    ]);
                    
                    // Fallback to immediate send
                    try {
                        Mail::to($user->email)
                            ->send(new \App\Mail\PaymentApproved($mailData));
                        
                        Log::info('Payment approval email sent immediately');
                        return true;
                        
                    } catch (\Exception $sendException) {
                        Log::error('Both queue and immediate send failed for payment approval', [
                            'queue_error' => $queueException->getMessage(),
                            'send_error' => $sendException->getMessage()
                        ]);
                        
                        // Fallback to simple email
                        return $this->sendSimpleApprovalEmail($payment, $subscription);
                    }
                }
            } else {
                Log::warning('PaymentApproved Mailable class not found, using template');
                
                // Use template directly
                try {
                    Mail::send('emails.payment-approved', $mailData, function ($message) use ($user) {
                        $message->to($user->email)
                                ->subject('Payment Approved - Your Subscription is Now Active | Redat Learning Hub');
                    });
                    
                    if (count(Mail::failures()) > 0) {
                        Log::error('Payment approval email template send failed', [
                            'failures' => Mail::failures()
                        ]);
                        return $this->sendSimpleApprovalEmail($payment, $subscription);
                    }
                    
                    Log::info('Payment approval email sent via template');
                    return true;
                    
                } catch (\Exception $templateException) {
                    Log::error('Payment approval template email failed', [
                        'error' => $templateException->getMessage()
                    ]);
                    
                    return $this->sendSimpleApprovalEmail($payment, $subscription);
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Complete payment approval email process failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Final fallback
            try {
                return $this->sendSimpleApprovalEmail($payment, $subscription);
            } catch (\Exception $simpleException) {
                Log::error('Even simple approval email failed', [
                    'error' => $simpleException->getMessage()
                ]);
                return false;
            }
        }
    }

    /**
     * Send payment denial email - USING REGISTRATION PATTERN
     */
    private function sendDenialEmail(Payment $payment, $denialReason)
    {
        try {
            $user = $payment->user;
            
            Log::info('Attempting to send payment denial email', [
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
                'denial_date' => now()->format('F j, Y'),
                'denial_reason' => $denialReason,
                'support_contact' => config('mail.support_email', 'support@redatlearninghub.com'),
                'retry_instructions' => 'Please review your payment details and submit a new payment request if needed.',
                'app_url' => config('app.url', 'https://redatlearninghub.com'),
            ];
            
            Log::info('Payment denial mail data prepared', [
                'user_email' => $user->email
            ]);
            
            // Check if Mailable class exists
            if (class_exists(\App\Mail\PaymentDenied::class)) {
                Log::info('Using PaymentDenied Mailable class');
                
                // Try to queue email first
                try {
                    Mail::to($user->email)
                        ->queue(new \App\Mail\PaymentDenied($mailData, $denialReason));
                    
                    Log::info('Payment denial email queued successfully');
                    return true;
                    
                } catch (\Exception $queueException) {
                    Log::warning('Payment denial email queue failed, trying immediate send', [
                        'error' => $queueException->getMessage()
                    ]);
                    
                    // Fallback to immediate send
                    try {
                        Mail::to($user->email)
                            ->send(new \App\Mail\PaymentDenied($mailData, $denialReason));
                        
                        Log::info('Payment denial email sent immediately');
                        return true;
                        
                    } catch (\Exception $sendException) {
                        Log::error('Both queue and immediate send failed for payment denial', [
                            'queue_error' => $queueException->getMessage(),
                            'send_error' => $sendException->getMessage()
                        ]);
                        
                        return $this->sendSimpleDenialEmail($payment, $denialReason);
                    }
                }
            } else {
                Log::warning('PaymentDenied Mailable class not found, using template');
                
                // Use template directly
                try {
                    Mail::send('emails.payment-denied', $mailData, function ($message) use ($user) {
                        $message->to($user->email)
                                ->subject('Payment Requires Attention - Action Required | Redat Learning Hub');
                    });
                    
                    if (count(Mail::failures()) > 0) {
                        Log::error('Payment denial email template send failed', [
                            'failures' => Mail::failures()
                        ]);
                        return $this->sendSimpleDenialEmail($payment, $denialReason);
                    }
                    
                    Log::info('Payment denial email sent via template');
                    return true;
                    
                } catch (\Exception $templateException) {
                    Log::error('Payment denial template email failed', [
                        'error' => $templateException->getMessage()
                    ]);
                    
                    return $this->sendSimpleDenialEmail($payment, $denialReason);
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Complete payment denial email process failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Final fallback
            try {
                return $this->sendSimpleDenialEmail($payment, $denialReason);
            } catch (\Exception $simpleException) {
                Log::error('Even simple denial email failed', [
                    'error' => $simpleException->getMessage()
                ]);
                return false;
            }
        }
    }

    /**
     * Simple fallback approval email (like password reset)
     */
    private function sendSimpleApprovalEmail(Payment $payment, Subscription $subscription)
    {
        try {
            $user = $payment->user;
            
            $message = "Hello {$user->name},\n\n" .
                      "Your payment of {$payment->amount} {$payment->currency} has been approved!\n\n" .
                      "Your subscription is now active until: " . 
                      Carbon::parse($subscription->end_date)->format('F j, Y') . "\n\n" .
                      "Thank you for choosing Redat Learning Hub!";
            
            Mail::raw($message, function ($message) use ($user) {
                $message->to($user->email)
                        ->subject('Payment Approved - Redat Learning Hub');
            });
            
            Log::info('Simple approval email sent to: ' . $user->email);
            return true;
            
        } catch (\Exception $e) {
            Log::error('Simple approval email also failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Simple fallback denial email (like password reset)
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
            Log::error('Simple denial email also failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test email endpoint
     */
    public function testEmail(Request $request)
    {
        try {
            $testEmail = $request->input('email', 'your-email@gmail.com');
            
            Log::info('Testing email system for payment notifications');
            
            // Test basic email (like password reset)
            Mail::raw('Test payment notification email from Redat Learning Hub', function ($message) use ($testEmail) {
                $message->to($testEmail)
                        ->subject('Test Payment Email - Redat Learning Hub');
            });
            
            if (count(Mail::failures()) > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Basic email test failed',
                    'failures' => Mail::failures()
                ], 500);
            }
            
            Log::info('Basic email test passed');
            
            return response()->json([
                'success' => true,
                'message' => 'Email test completed successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Payment email test failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Email test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}