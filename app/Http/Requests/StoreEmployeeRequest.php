<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\PermissionName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Password;

class StoreEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(PermissionName::ManageEmployees->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_code' => ['required', 'string', 'max:30', 'unique:users,employee_code'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'designation' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'role_id' => ['required', 'integer', $this->assignableRoleRule()],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'joining_date' => ['nullable', 'date'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * Only a super admin may hand out the built-in Admin role.
     */
    protected function assignableRoleRule(): Exists
    {
        $rule = Rule::exists('roles', 'id');

        if (! $this->user()->isSuperAdmin()) {
            $rule->where(fn ($query) => $query->where('slug', '!=', Role::SUPER_ADMIN_SLUG));
        }

        return $rule;
    }
}
