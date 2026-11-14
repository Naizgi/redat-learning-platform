<?php
// app/Http/Controllers/Api/SettingsController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;

class SettingsController extends Controller
{
    public function public()
    {
        return response()->json([
            'success'  => true,
            'settings' => [
                'payment_required' => Setting::paymentRequired(),
            ],
        ]);
    }
}