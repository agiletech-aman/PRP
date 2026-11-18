<?php

namespace App\Policies;

use App\Models\Appraisal;
use App\Models\User;
use App\PermissionName;

class AppraisalPolicy
{
    /**
     * The employee, their manager and anyone who sends appraisals may open the form.
     */
    public function view(User $user, Appraisal $appraisal): bool
    {
        return $user->id === $appraisal->employee_id
            || $user->id === $appraisal->manager_id
            || $user->can(PermissionName::SendAppraisals->value);
    }

    /**
     * Only the person whose turn it is (employee first, then manager) may submit ratings.
     */
    public function rate(User $user, Appraisal $appraisal): bool
    {
        return $user->is_active && $appraisal->ratingStageFor($user) !== null;
    }

    /**
     * HR may add or change optional remarks at any stage, except on their own appraisal.
     * The super admin only reads them.
     */
    public function addHrRemarks(User $user, Appraisal $appraisal): bool
    {
        return ! $user->isSuperAdmin()
            && $user->can(PermissionName::SendAppraisals->value)
            && $user->id !== $appraisal->employee_id;
    }
}
