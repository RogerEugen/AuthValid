<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
            'current_password' => ['required', 'string'],
            'new_password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[A-Z]/', // we nee the one uppercase letter
                'regex:/[0-9]/', //we need the one  number some how 
            ]
        ];
    }

    public function messages():array
    {
        return[
            'new_password.min'=>'new password must be at least 8 characters',
            'new_password.confirmed'=>'new password and confirm password must match',
            'new_password.regex'=>'new password must contain at least one uppercase letter and one number'
        ];
    }
}
