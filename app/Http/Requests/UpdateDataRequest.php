<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class UpdateDataRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // We'll validate app key in the controller
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            // App key validation
            'X-App-Key' => 'required|string|regex:/^[a-zA-Z0-9._-]+$/|min:32|max:64',
            // Data ID validation for update operations
            'data_id' => 'required|string|regex:/^[a-zA-Z0-9._-]+$/|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'X-App-Key.required' => 'App key is required',
            'X-App-Key.regex' => 'Invalid app key format',
            'X-App-Key.min' => 'App key must be at least 32 characters',
            'X-App-Key.max' => 'App key must not exceed 64 characters',
            'data_id.required' => 'Data ID is required for update operations',
            'data_id.regex' => 'Data ID contains invalid characters',
            'data_id.max' => 'Data ID must not exceed 255 characters',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
            'error_code' => 'VALIDATION_FAILED'
        ], 422));
    }
}