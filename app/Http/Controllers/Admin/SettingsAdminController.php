<?php
// app/Http/Controllers/Admin/SettingsAdminController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsAdminController extends Controller
{
    public function getPaymentSetting()
    {
        return response()->json([
            'success'          => true,
            'payment_required' => Setting::paymentRequired(),
        ]);
    }

    public function setPaymentSetting(Request $request)
    {
        $request->validate([
            'payment_required' => 'required|boolean',
        ]);

        Setting::setBool('payment_required', (bool) $request->payment_required);
        $now = Setting::paymentRequired();

        return response()->json([
            'success'          => true,
            'payment_required' => $now,
            'message'          => $now
                ? 'Payment verification is now ON. New registrations must pay.'
                : 'Payment verification is now OFF. New registrations are activated immediately.',
        ]);
    }
}