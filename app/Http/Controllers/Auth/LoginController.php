<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\ValidationErrorResponse;
use App\Traits\ValidatesWithFriendlyErrors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    use ValidatesWithFriendlyErrors;

    public function login(Request $request)
    {
        $validated = $this->validateData($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
            'remember_me' => 'sometimes|boolean',
        ]);

        if (!$validated) {
            return ValidationErrorResponse::make(
                $this->getValidationErrors(),
                'Please check your input and try again'
            );
        }

        $credentials = $request->only('email', 'password');
        
        // Attempt authentication with JWT guard
        if (!$token = Auth::guard('api')->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Email or password is incorrect',
                'errors' => [
                    'email' => 'No account found with these credentials'
                ]
            ], 401);
        }

        $user = Auth::guard('api')->user();
        
        return response()->json([
            'success' => true,
            'message' => 'Welcome back! You have been logged in successfully.',
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer'
        ]);
    }
}