<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SmtpTestMail;
use App\Models\AppraisalSetting;
use App\Support\SmtpSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

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
            'smtpSettings' => SmtpSettings::formValues(),
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

    public function updateSmtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:save,test'],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_encryption' => ['required', 'in:tls,ssl'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:4096'],
            'smtp_from_address' => ['required', 'email:rfc', 'max:255'],
            'smtp_from_name' => ['required', 'string', 'max:255'],
            'test_recipient' => ['required_if:action,test', 'nullable', 'email:rfc', 'max:255'],
        ]);

        if (($validated['smtp_username'] ?? '') !== '' && ($validated['smtp_password'] ?? '') === '' && ! SmtpSettings::hasStoredPassword()) {
            return back()
                ->withErrors(['smtp_password' => 'Enter the SMTP password for this username.'])
                ->withInput($request->except('smtp_password'));
        }

        if (($validated['smtp_username'] ?? '') === '' && ($validated['smtp_password'] ?? '') !== '') {
            return back()
                ->withErrors(['smtp_username' => 'Enter an SMTP username when providing a password.'])
                ->withInput($request->except('smtp_password'));
        }

        if ($validated['action'] === 'save') {
            SmtpSettings::save($validated);

            return back()->with('status', 'SMTP settings saved.');
        }

        SmtpSettings::applyInput($validated);

        try {
            Mail::to($validated['test_recipient'])->send(new SmtpTestMail);
        } catch (TransportExceptionInterface|\InvalidArgumentException $exception) {
            report($exception);

            return back()
                ->withErrors(['smtp' => 'The test email could not be sent. Check the SMTP host, port, encryption, and credentials.'])
                ->withInput($request->except('smtp_password'));
        }

        return back()->with('status', 'SMTP test email sent successfully.');
    }
}
