<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppraisalSetting;
use App\Models\Role;
use App\Models\User;
use App\PermissionName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public const HR_EVALUATOR_IDS_KEY = 'hr_evaluator_user_ids';

    public const HR_REPORT_RECIPIENTS_KEY = 'hr_report_recipient_emails';

    public static function themeKey(int $userId): string
    {
        return "theme_mode_user_{$userId}";
    }

    public static function sidebarKey(int $userId): string
    {
        return "sidebar_collapsed_user_{$userId}";
    }

    public function edit(): View
    {
        $eligibleUsers = User::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereHas('role.permissions', fn ($query) => $query->where('name', PermissionName::EvaluateAppraisals->value))
                    ->orWhereHas('role', fn ($query) => $query->where('slug', Role::SUPER_ADMIN_SLUG));
            })
            ->orderBy('name')
            ->get();

        $configuredIds = AppraisalSetting::getJson(self::HR_EVALUATOR_IDS_KEY, null);

        if ($configuredIds === null) {
            $selectedIds = $eligibleUsers->pluck('id')->map(fn ($id) => (int) $id)->all();
        } else {
            $selectedIds = array_map('intval', $configuredIds);
        }

        return view('admin.settings.appraisal', [
            'eligibleUsers' => $eligibleUsers,
            'selectedIds' => $selectedIds,
            'reportRecipients' => implode(",\n", AppraisalSetting::getJson(self::HR_REPORT_RECIPIENTS_KEY, [])),
            'themeMode' => AppraisalSetting::get(self::themeKey((int) auth()->id()), 'light'),
            'sidebarCollapsed' => AppraisalSetting::get(self::sidebarKey((int) auth()->id()), '0') === '1',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'evaluator_ids' => ['nullable', 'array'],
            'evaluator_ids.*' => ['integer', 'exists:users,id'],
            'theme_mode' => ['required', 'in:light,dark'],
            'sidebar_collapsed' => ['nullable', 'in:0,1'],
            'report_recipient_emails' => ['nullable', 'string', 'max:5000'],
        ]);

        $reportRecipients = collect(preg_split('/[,\n\r]+/', (string) $request->input('report_recipient_emails')))
            ->map(fn ($email) => strtolower(trim($email)))
            ->filter()
            ->unique()
            ->values();

        foreach ($reportRecipients as $email) {
            validator(['email' => $email], ['email' => ['email:rfc']])
                ->validate();
        }

        $selectedIds = array_values(array_unique(array_map(
            'intval',
            $validated['evaluator_ids'] ?? [],
        )));

        $eligibleIds = User::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereHas('role.permissions', fn ($query) => $query->where('name', PermissionName::EvaluateAppraisals->value))
                    ->orWhereHas('role', fn ($query) => $query->where('slug', Role::SUPER_ADMIN_SLUG));
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $invalidIds = array_diff($selectedIds, $eligibleIds);

        if ($invalidIds !== []) {
            return back()->withErrors(['evaluator_ids' => 'Select only active users eligible for HR evaluation.'])->withInput();
        }

        AppraisalSetting::putJson(self::HR_EVALUATOR_IDS_KEY, $selectedIds);
        AppraisalSetting::putJson(self::HR_REPORT_RECIPIENTS_KEY, $reportRecipients->all());
        AppraisalSetting::put(self::themeKey((int) $request->user()->id), $validated['theme_mode']);
        AppraisalSetting::put(
            self::sidebarKey((int) $request->user()->id),
            $request->boolean('sidebar_collapsed') ? '1' : '0',
        );

        return back()->with('status', 'Post-manager appraisal mail recipients updated.');
    }
}
