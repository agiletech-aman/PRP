<?php

namespace App\Http\Requests;

use App\Models\Appraisal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubmitAppraisalRatingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('rate', $this->appraisal());
    }

    /**
     * A rating from 1 to the maximum is required for every factor on the form.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'ratings' => ['required', 'array'],
            'comments' => ['nullable', 'string', 'max:2000'],
        ];

        foreach ($this->appraisal()->ratings as $rating) {
            $rules["ratings.{$rating->id}"] = ['required', 'integer', 'between:1,'.Appraisal::MAX_RATING];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ratings.*.required' => 'Please rate this factor.',
            'ratings.*.between' => 'Pick a rating from 1 to '.Appraisal::MAX_RATING.'.',
        ];
    }

    public function appraisal(): Appraisal
    {
        return $this->route('appraisal');
    }
}
