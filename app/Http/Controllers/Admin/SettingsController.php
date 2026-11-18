<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppraisalSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
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
        return view('admin.settings.appraisal', [
            'reportRecipients' => implode(",\n", AppraisalSetting::getJson(self::HR_REPORT_RECIPIENTS_KEY, [])),
            'themeMode' => AppraisalSetting::get(self::themeKey((int) auth()->id()), 'light'),
            'sidebarCollapsed' => AppraisalSetting::get(self::sidebarKey((int) auth()->id()), '0') === '1',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
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

        AppraisalSetting::putJson(self::HR_REPORT_RECIPIENTS_KEY, $reportRecipients->all());
        AppraisalSetting::put(self::themeKey((int) $request->user()->id), $validated['theme_mode']);
        AppraisalSetting::put(
            self::sidebarKey((int) $request->user()->id),
            $request->boolean('sidebar_collapsed') ? '1' : '0',
        );

        return back()->with('status', 'Settings updated.');
    }
}
