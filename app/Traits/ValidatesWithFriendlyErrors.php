<?php

namespace App\Traits;

use Illuminate\Support\Facades\Validator;

trait ValidatesWithFriendlyErrors
{
    /**
     * Validate request data with custom, user-friendly error messages
     *
     * @param array $data The data to validate
     * @param array $rules The validation rules
     * @param array $customMessages Optional custom error messages
     * @return array|false Array with validated data or false if validation fails
     */
    protected function validateData(array $data, array $rules, array $customMessages = [])
    {
        $messages = array_merge($this->getDefaultMessages(), $customMessages);

        $validator = Validator::make($data, $rules, $messages);

        if ($validator->fails()) {
            $this->validationErrors = $this->formatErrors($validator->errors()->toArray());
            return false;
        }

        return $validator->validated();
    }

    /**
     * Get default user-friendly validation messages
     *
     * @return array
     */
    protected function getDefaultMessages(): array
    {
        return [
            'name.required' => 'Name is required',
            'name.string' => 'Name must be a valid text',
            'name.max' => 'Name cannot exceed 255 characters',

            'email.required' => 'Email is required',
            'email.string' => 'Email must be valid text',
            'email.email' => 'Please enter a valid email address',
            'email.max' => 'Email cannot exceed 255 characters',
            'email.unique' => 'This email is already registered with us',

            'password.required' => 'Password is required',
            'password.string' => 'Password must be valid text',
            'password.min' => 'Password must be at least 8 characters long',
            'password.confirmed' => 'Passwords do not match',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number',

            'password_confirmation.required' => 'Password confirmation is required',

            'token.required' => 'Reset token is required',
            'token.string' => 'Reset token must be valid',

            'current_password.required' => 'Current password is required',
            'current_password.current_password' => 'Current password is incorrect',
        ];
    }

    /**
     * Format validation errors in a user-friendly way
     *
     * @param array $errors Raw validation errors from Laravel validator
     * @return array Formatted errors
     */
    protected function formatErrors(array $errors): array
    {
        $formatted = [];

        foreach ($errors as $field => $messages) {
            // Get the first error message for this field
            $formatted[$field] = $messages[0] ?? 'Invalid field';
        }

        return $formatted;
    }

    /**
     * Get validation errors
     *
     * @return array
     */
    protected function getValidationErrors(): array
    {
        return $this->validationErrors ?? [];
    }
}
