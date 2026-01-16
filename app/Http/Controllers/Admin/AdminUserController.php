<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    // List all users
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                                                  ->orWhere('email', 'like', "%{$request->search}%"))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($users);
    }

    // Show single user
    public function show(User $user)
    {
        return response()->json($user);
    }

    // Create a new user
    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role'     => ['required', Rule::in(['student', 'instructor', 'admin'])],
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->role,
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user'    => $user
        ], 201);
    }

    // Update user
// In AdminUserController.php - update method
public function update(Request $request, User $user)
{
    $request->validate([
        'name'      => 'sometimes|string|max:255',
        'email'     => ['sometimes','email', Rule::unique('users')->ignore($user->id)],
        'password'  => 'sometimes|string|min:6',
        'role'      => ['sometimes', Rule::in(['student', 'instructor', 'admin'])],
        'is_active' => 'sometimes|boolean', // Add this validation
    ]);

    // Existing updates...
    if ($request->has('name')) {
        $user->name = $request->name;
    }
    if ($request->has('email')) {
        $user->email = $request->email;
    }
    if ($request->has('password')) {
        $user->password = Hash::make($request->password);
    }
    if ($request->has('role')) {
        $user->role = $request->role;
    }
    
    // Add is_active update
    if ($request->has('is_active')) {
        $user->is_active = $request->is_active;
    }

    $user->save();

    return response()->json([
        'message' => 'User updated successfully',
        'user'    => $user
    ]);
}

    // Delete user
    public function destroy(User $user)
    {
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }
}
