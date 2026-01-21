<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    // Get student's payment history
    public function index(Request $request)
    {
        $payments = Payment::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'payments' => $payments,
        ]);
    }

    public function submit(Request $request)
    {
        // Determine user ID - either from authenticated user or from request
        $userId = null;
        $userEmail = null;
        
        if ($request->user()) {
            // User is authenticated (normal flow)
            $userId = $request->user()->id;
        } else {
            // User is not authenticated (registration flow)
            // Validate that user_id and email are provided
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'email' => 'required|email|exists:users,email',
            ]);
            
            $userId = $request->user_id;
            $userEmail = $request->email;
            
            // Verify the user exists and email matches
            $user = User::find($userId);
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }
            
            if ($user->email !== $userEmail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email does not match user.',
                ], 400);
            }
        }

        // Common validation rules
        $request->validate([
            'transaction_id' => 'required|unique:payments',
            'amount' => 'required|numeric|min:1',
            'proof_document' => 'required|file|max:5120|mimes:jpg,jpeg,png,pdf',
        ]);

        DB::beginTransaction();

        try {
            // Handle file upload
            $filePath = null;
            if ($request->hasFile('proof_document')) {
                $file = $request->file('proof_document');
                $fileName = 'payment_' . time() . '_' . $userId . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('payment-proofs', $fileName, 'public');
            }

            $payment = Payment::create([
                'user_id' => $userId,
                'transaction_id' => $request->transaction_id,
                'amount' => $request->amount,
                'currency' => 'ETB', // Changed from USD to ETB to match your frontend
                'status' => 'pending',
                'proof_document' => $filePath,
                'payment_method' => 'manual',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment submitted. Waiting for admin approval.',
                'payment_id' => $payment->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Clean up uploaded file if exists
            if (isset($filePath)) {
                Storage::disk('public')->delete($filePath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit payment. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}