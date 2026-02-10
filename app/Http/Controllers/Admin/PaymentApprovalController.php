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

            // Get current date for subscription
            $startDate = now();
            $endDate = now()->addYear(); // 1 year subscription
            
            // Create or update subscription with all required fields
            $subscriptionData = [
                'user_id' => $payment->user_id,
                'plan_id' => null,
                'status' => 'active',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'expires_at' => $endDate, // Also set expires_at for backward compatibility
                'auto_renew' => false,
                'created_by_admin' => true,
                'admin_id' => $request->user()->id,
                'notes' => 'Subscription activated via payment approval',
                'amount' => $payment->amount,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $subscription = Subscription::updateOrCreate(
                ['user_id' => $payment->user_id],
                $subscriptionData
            );

            // Update user status
            $payment->user->update(['is_active' => true]);

            DB::commit();

            // Send approval notification email
            $emailSent = $this->sendPaymentEmail('approved', $payment, $subscription);

            return response()->json([
                'success' => true,
                'message' => 'Payment approved and subscription activated.',
                'data' => [
                    'payment_id' => $payment->id,
                    'user_id' => $payment->user_id,
                    'subscription_start' => $subscription->start_date,
                    'subscription_end' => $subscription->end_date,
                    'expires_at' => $subscription->expires_at,
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

            // Send denial notification email
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
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to deny payment. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Send payment email
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
            
            // Format currency as ETB (Birr)
            $currency = 'ETB';
            $paymentAmount = number_format($payment->amount, 2);
            
            // Prepare mail data
            $mailData = [
                'user_name' => $user->name,
                'payment_amount' => $paymentAmount,
                'payment_currency' => $currency,
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
            
            // Try to queue email first
            try {
                Log::info('Queueing payment ' . $type . ' email');
                Mail::to($user->email)->queue($mailable);
                
                Log::info('Payment ' . $type . ' email queued successfully');
                return true;
                
            } catch (\Exception $queueException) {
                Log::warning('Payment ' . $type . ' email queue failed, trying immediate send', [
                    'error' => $queueException->getMessage()
                ]);
                
                // Fallback to immediate send
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
     * Simple fallback email
     */
    private function sendSimplePaymentEmail($type, Payment $payment, $subscription = null, $denialReason = null)
    {
        try {
            $user = $payment->user;
            
            // Format currency as ETB
            $currency = 'ETB';
            $paymentAmount = number_format($payment->amount, 2);
            
            if ($type === 'approved') {
                $subject = 'Payment Approved - Redat Learning Hub';
                $endDate = $subscription ? Carbon::parse($subscription->end_date)->format('F j, Y') : 'N/A';
                $message = "Hello {$user->name},\n\n" .
                          "Your payment of {$currency} {$paymentAmount} has been approved.\n\n" .
                          "Your subscription is now active until: {$endDate}\n\n" .
                          "Thank you for choosing Redat Learning Hub!";
            } else {
                $subject = 'Payment Requires Attention - Redat Learning Hub';
                $message = "Hello {$user->name},\n\n" .
                          "Your payment of {$currency} {$paymentAmount} has been denied.\n\n" .
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

    /**
     * Test email functionality
     */
    public function testEmail(Request $request)
    {
        try {
            $testEmail = $request->input('email', 'test@example.com');
            
            Log::info('Testing payment email system');
            
            // Test with ETB currency
            $mailData = [
                'user_name' => 'Test User',
                'payment_amount' => '1,000.00',
                'payment_currency' => 'ETB',
                'payment_method' => 'Bank Transfer',
                'payment_reference' => 'TEST-123456',
                'payment_date' => now()->format('F j, Y'),
                'approval_date' => now()->format('F j, Y'),
                'subscription_start' => now()->format('F j, Y'),
                'subscription_end' => now()->addYear()->format('F j, Y'),
                'subscription_duration' => '1 Year',
                'app_url' => config('app.url', 'https://redatlearninghub.com'),
                'support_contact' => config('mail.support_email', 'support@redatlearninghub.com'),
            ];
            
            Mail::to($testEmail)->send(new PaymentApproved($mailData));
            
            if (count(Mail::failures()) > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment email test failed',
                    'failures' => Mail::failures()
                ], 500);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Payment email test completed successfully with ETB currency'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Payment email test failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Payment email test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment statistics
     */
    public function getPaymentStats()
    {
        try {
            $totalPayments = Payment::count();
            $pendingPayments = Payment::where('status', 'pending')->count();
            $approvedPayments = Payment::where('status', 'approved')->count();
            $deniedPayments = Payment::where('status', 'denied')->count();
            
            $totalAmount = Payment::where('status', 'approved')->sum('amount');
            
            return response()->json([
                'success' => true,
                'stats' => [
                    'total_payments' => $totalPayments,
                    'pending_payments' => $pendingPayments,
                    'approved_payments' => $approvedPayments,
                    'denied_payments' => $deniedPayments,
                    'total_amount' => number_format($totalAmount, 2),
                    'currency' => 'ETB'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get payment statistics: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load payment statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get payment details by ID
     */
    public function getPaymentDetails($id)
    {
        try {
            $payment = Payment::with(['user:id,name,email,phone,created_at', 'subscription'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'payment' => $payment
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get payment details: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 404);
        }
    }

    /**
     * Bulk payment approvals
     */
    public function bulkApprove(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'payment_ids' => 'required|array',
            'payment_ids.*' => 'exists:payments,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        $paymentIds = $request->payment_ids;
        $results = [
            'success' => 0,
            'failed' => 0,
            'details' => []
        ];

        DB::beginTransaction();

        try {
            foreach ($paymentIds as $paymentId) {
                try {
                    $payment = Payment::with('user')->find($paymentId);
                    
                    if (!$payment) {
                        $results['failed']++;
                        $results['details'][] = [
                            'payment_id' => $paymentId,
                            'status' => 'failed',
                            'reason' => 'Payment not found'
                        ];
                        continue;
                    }

                    // Skip if already approved
                    if ($payment->status === 'approved') {
                        $results['details'][] = [
                            'payment_id' => $paymentId,
                            'status' => 'skipped',
                            'reason' => 'Already approved'
                        ];
                        continue;
                    }

                    // Update payment
                    $payment->update([
                        'status' => 'approved',
                        'approved_by' => $request->user()->id,
                        'approved_at' => now(),
                    ]);

                    // Create or update subscription
                    $startDate = now();
                    $endDate = now()->addYear();
                    
                    $subscriptionData = [
                        'user_id' => $payment->user_id,
                        'plan_id' => null,
                        'status' => 'active',
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'expires_at' => $endDate,
                        'auto_renew' => false,
                        'created_by_admin' => true,
                        'admin_id' => $request->user()->id,
                        'notes' => 'Bulk approval via payment',
                        'amount' => $payment->amount,
                    ];

                    Subscription::updateOrCreate(
                        ['user_id' => $payment->user_id],
                        $subscriptionData
                    );

                    // Update user status
                    $payment->user->update(['is_active' => true]);

                    $results['success']++;
                    $results['details'][] = [
                        'payment_id' => $paymentId,
                        'status' => 'success',
                        'user_id' => $payment->user_id
                    ];

                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['details'][] = [
                        'payment_id' => $paymentId,
                        'status' => 'failed',
                        'reason' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            Log::info('Bulk payment approval completed', [
                'admin_id' => $request->user()->id,
                'total' => count($paymentIds),
                'success' => $results['success'],
                'failed' => $results['failed']
            ]);

            return response()->json([
                'success' => true,
                'message' => "Bulk approval completed. Success: {$results['success']}, Failed: {$results['failed']}",
                'data' => $results
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Bulk payment approval failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Bulk approval failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Search payments
     */
    public function searchPayments(Request $request)
    {
        try {
            $query = Payment::with('user:id,name,email');
            
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                      ->orWhere('method', 'like', "%{$search}%")
                      ->orWhere('status', 'like', "%{$search}%")
                      ->orWhereHas('user', function($userQuery) use ($search) {
                          $userQuery->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }
            
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            if ($request->has('method')) {
                $query->where('method', $request->method);
            }
            
            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            
            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }
            
            $payments = $query->orderBy('created_at', 'desc')
                             ->paginate($request->per_page ?? 20);

            return response()->json([
                'success' => true,
                'payments' => $payments,
                'message' => 'Payments retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Payment search failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to search payments',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}