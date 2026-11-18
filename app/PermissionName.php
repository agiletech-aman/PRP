<?php

namespace App;

/**
 * Every permission the application checks. Each case is registered as a Gate ability
 * and synced into the `permissions` table by RolesAndPermissionsSeeder.
 */
enum PermissionName: string
{
    case ViewDashboard = 'dashboard.view';
    case ViewEmployees = 'employees.view';
    case ManageEmployees = 'employees.manage';
    case ManageDepartments = 'departments.manage';
    case ManageRatings = 'ratings.manage';
    case ManageRoles = 'roles.manage';
    case SendAppraisals = 'appraisals.send';

    public function label(): string
    {
        return match ($this) {
            self::ViewDashboard => 'View dashboard',
            self::ViewEmployees => 'View employees',
            self::ManageEmployees => 'Add, edit and delete employees',
            self::ManageDepartments => 'Manage departments',
            self::ManageRatings => 'Manage rating headings, factors & KRA',
            self::ManageRoles => 'Manage roles & permissions',
            self::SendAppraisals => 'Send appraisal mails & track all appraisals',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::ViewDashboard => 'General',
            self::ViewEmployees, self::ManageEmployees => 'Employees',
            self::ManageDepartments, self::ManageRatings => 'Appraisal setup',
            self::ManageRoles => 'Administration',
            self::SendAppraisals => 'Appraisals',
        };
    }
}
