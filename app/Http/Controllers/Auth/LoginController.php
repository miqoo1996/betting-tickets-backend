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

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Email or password is incorrect',
                'errors' => [
                    'email' => 'No account found with these credentials'
                ]
            ], 401);
        }

        $user = Auth::user();
        
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
            'message' => 'Welcome back! You have been logged in successfully.',
            'user' => $user,
            'token' => $token,
            'expires_at' => $expiredAt
        ]);
    }
}