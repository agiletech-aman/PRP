<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateEmployeeRequest extends StoreEmployeeRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Non super admins cannot edit an existing super admin's account.
     */
    public function authorize(): bool
    {
        return parent::authorize()
            && ($this->user()->isSuperAdmin() || ! $this->employee()->isSuperAdmin());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employee = $this->employee();

        return [
            ...parent::rules(),
            'employee_code' => ['required', 'string', 'max:30', Rule::unique('users', 'employee_code')->ignore($employee)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee)],
            'role_id' => [Rule::excludeIf($this->user()->is($employee)), 'required', 'integer', $this->assignableRoleRule()],
            'manager_id' => ['nullable', 'integer', 'exists:users,id', Rule::notIn([$employee->id])],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ];
    }

    public function employee(): User
    {
        return $this->route('employee');
    }
}
