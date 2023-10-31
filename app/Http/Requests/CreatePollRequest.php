<?php

namespace App\Http\Requests;

use App\Models\Poll;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class CreatePollRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->role == 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:191',
            'description' => 'required|string',
            'deadline' => 'required|date_format:Y-m-d H:i:s',
            'choices' => 'required|array|min:2',
        ];
    }

    /**
     * Throws a ValidationException with a JSON response containing the validation errors.
     *
     * @param Validator $validator The validator instance.
     * @throws ValidationException The exception to be thrown.
     * @return void
     */
    protected function failedValidation(Validator $validator) 
    {
        throw new ValidationException($validator, response()->json([
            'message' => 'The given data was invalid.',
        ], 422));
    }
}
