<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
  public function rules(): array
{
    return [
        'person_id' => 'required|exists:people,id|unique:students,person_id',
        'student_code' => 'required|string|max:50|unique:students,student_code',
        'admission_date' => 'nullable|date',
        'status' => 'required|in:active,inactive',
    ];
}
}
