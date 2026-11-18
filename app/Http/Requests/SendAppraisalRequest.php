<?php

namespace App\Http\Requests;

use App\Models\User;
use App\PermissionName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SendAppraisalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(PermissionName::SendAppraisals->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('is_active', true)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_ids.required' => 'Select at least one employee.',
        ];
    }

    /**
     * Every selected employee needs a department with a form, a reporting manager,
     * and no appraisal that is still in progress.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $problems = [];

                foreach ($this->selectedEmployees() as $employee) {
                    $problem = match (true) {
                        $employee->department === null => 'has no department',
                        $employee->manager === null => 'has no reporting manager',
                        ! $employee->manager->is_active => 'has an inactive reporting manager',
                        $employee->appraisals()->open()->exists() => 'already has an appraisal in progress',
                        $employee->department->appraisalFormCategories()->get()->pluck('factors')->flatten()->isEmpty() => "belongs to {$employee->department->name}, which has no rating factors yet",
                        default => null,
                    };

                    if ($problem !== null) {
                        $problems[] = "{$employee->name} {$problem}.";
                    }
                }

                foreach ($problems as $problem) {
                    $validator->errors()->add('employee_ids', $problem);
                }
            },
        ];
    }

    /**
     * @return Collection<int, User>
     */
    public function selectedEmployees(): Collection
    {
        return User::query()
            ->with(['department', 'manager'])
            ->whereIn('id', $this->input('employee_ids', []))
            ->orderBy('name')
            ->get();
    }
}
