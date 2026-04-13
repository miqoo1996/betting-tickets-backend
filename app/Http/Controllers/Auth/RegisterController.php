<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\ValidationErrorResponse;
use App\Models\User;
use App\Traits\ValidatesWithFriendlyErrors;
use Illuminate\Http\Request;
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

        // Create token with appropriate expiry based on remember_me flag
        $rememberMe = $request->input('remember_me', false);
        
        if ($rememberMe) {
            // Token expires in 30 days if remember me is checked
            $expiredAt = now()->addDays(30);
        } else {
            // Token expires in 1 day if remember me is not checked  
            $expiredAt = now()->addDay();
        }
        
        $tokenResult = $user->createToken('API Token');
        $token = $tokenResult->plainTextToken;
        
        // Update the token's expiry in database
        $tokenResult->accessToken->expires_at = $expiredAt;
        $tokenResult->accessToken->save();

        return response()->json([
            'success' => true,
            'message' => 'Registration successful! You can now log in.',
            'user' => $user,
            'token' => $token,
            'expires_at' => $expiredAt
        ], 201);
    }
}