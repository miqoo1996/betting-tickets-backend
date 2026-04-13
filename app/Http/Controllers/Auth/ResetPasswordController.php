<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\ValidationErrorResponse;
use App\Traits\ValidatesWithFriendlyErrors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ResetPasswordController extends Controller
{
    use ValidatesWithFriendlyErrors;

    public function reset(Request $request)
    {
        $validated = $this->validateData($request->all(), [
            'token' => 'required|string',
            'email' => 'required|string|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (!$validated) {
            return ValidationErrorResponse::make(
                $this->getValidationErrors(),
                'Please check your input and try again'
            );
        }

        $status = Password::reset(
            $validated + ['password_confirmation' => $request->password_confirmation, 'token' => $request->token],
            function ($user, $password) {
                $user->forceFill([
                    'password' => bcrypt($password)
                ])->setRememberToken(str()->random(60));

                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Your password has been reset successfully. You can now log in with your new password.'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'The password reset link has expired or is invalid. Please request a new one.',
                'errors' => [
                    'token' => 'Reset link is expired or invalid'
                ]
            ], 400);
        }
    }
}