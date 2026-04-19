<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\ValidationErrorResponse;
use App\Models\User;
use App\Traits\ValidatesWithFriendlyErrors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    use ValidatesWithFriendlyErrors;

    public function register(Request $request)
    {
        $validated = $this->validateData($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'remember_me' => 'sometimes|boolean',
        ]);

        if (!$validated) {
            return ValidationErrorResponse::make(
                $this->getValidationErrors(),
                'Please check your input and try again'
            );
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Generate JWT token for the newly created user
        $token = Auth::guard('api')->fromUser($user);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful! You can now log in.',
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer'
        ], 201);
    }
}
