<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MaterialCommentController extends Controller
{
   public function store(Request $request, Material $material)
{
    $request->validate(['comment' => 'required']);

    $material->comments()->create([
        'user_id' => auth()->id(),
        'comment' => $request->comment,
    ]);

    return response()->json(['message' => 'Comment added']);
}

}
