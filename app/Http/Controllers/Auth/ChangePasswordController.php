<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\ValidationErrorResponse;
use App\Traits\ValidatesWithFriendlyErrors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ChangePasswordController extends Controller
{
    use ValidatesWithFriendlyErrors;

    public function change(Request $request)
    {
        $validated = $this->validateData($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (!$validated) {
            return ValidationErrorResponse::make(
                $this->getValidationErrors(),
                'Please check your input and try again'
            );
        }

        $user = Auth::user();

        // Check if current password is correct
        if (!Hash::check($request->input('current_password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
                'errors' => [
                    'current_password' => 'The current password you entered is incorrect'
                ]
            ], 422);
        }

        // Update to new password
        $user->password = Hash::make($validated['password']);
        $user->save();

        // Revoke all existing tokens (force re-login on other sessions)
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Your password has been changed successfully. Please log in again with your new password.',
        ]);
    }
}
