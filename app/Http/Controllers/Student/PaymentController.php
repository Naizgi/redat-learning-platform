<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Payment;
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
                $fileName = 'payment_' . time() . '_' . $request->user()->id . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('payment-proofs', $fileName, 'public');
            }

            $payment = Payment::create([
                'user_id' => $request->user()->id,
                'transaction_id' => $request->transaction_id,
                'amount' => $request->amount,
                'currency' => 'USD', // Default currency
                'status' => 'pending',
                'proof_document' => $filePath,
                'payment_method' => 'manual', // Default payment method
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