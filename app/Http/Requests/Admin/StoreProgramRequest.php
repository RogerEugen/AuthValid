<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreProgramRequest extends FormRequest
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
        $programId = $this->route('id');

        return [
            'department_id'    => ['required', 'integer', 'exists:departments,id'],
            'name'             => ['required', 'string', 'max:255'],
            'code'             => [
                'required',
                'string',
                'max:20',
                'unique:programs,code,' . $programId,
            ],
            'level'            => ['required', 'in:basic_certificate,certificate,diploma,higher_diploma,postgraduate_diploma,bachelors,masters,phd'],
            'duration_years'   => ['required', 'numeric', 'min:0.01', 'max:10'],
            'duration_display' => ['required', 'string', 'max:50'],
            'is_active'        => ['sometimes', 'boolean'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
