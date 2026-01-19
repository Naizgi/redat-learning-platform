<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MaterialLikeController extends Controller
{
public function toggle(Material $material)
{
    $user = auth()->user();

    $material->likes()->firstOrCreate([
        'user_id' => $user->id
    ]);

    return response()->json(['liked' => true]);
}

}
