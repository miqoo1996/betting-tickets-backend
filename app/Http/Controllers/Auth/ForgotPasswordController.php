<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\ValidationErrorResponse;
use App\Traits\ValidatesWithFriendlyErrors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    use ValidatesWithFriendlyErrors;

    public function forgotPassword(Request $request)
    {
        $validated = $this->validateData($request->all(), [
            'email' => 'required|string|email',
        ]);

        if (!$validated) {
            return ValidationErrorResponse::make(
                $this->getValidationErrors(),
                'Please check your input and try again'
            );
        }

        $status = Password::sendResetLink(
            $validated
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'success' => true,
                'message' => 'Check your email for password reset instructions. The link will expire in 60 minutes.'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'We could not find an account associated with that email address.',
                'errors' => [
                    'email' => 'Email not found in our system'
                ]
            ], 422);
        }
    }
}