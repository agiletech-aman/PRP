<?php

namespace App;

/**
 * Lifecycle of an appraisal: the employee rates themselves first, then their manager rates them.
 */
enum AppraisalStatus: string
{
    case Pending = 'pending';
    case EmployeeSubmitted = 'employee_submitted';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting employee',
            self::EmployeeSubmitted => 'Awaiting manager',
            self::Completed => 'Completed',
        };
    }

    /**
     * CSS pill modifier used wherever the status is displayed.
     */
    public function pillClass(): string
    {
        return match ($this) {
            self::Pending => 'pill-warn',
            self::EmployeeSubmitted => 'pill-blue',
            self::Completed => 'pill-green',
        };
    }
}
