<?php

namespace App\Support;

use App\Models\AppraisalSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SmtpSettings
{
    private const HOST_KEY = 'smtp_host';

    private const PORT_KEY = 'smtp_port';

    private const ENCRYPTION_KEY = 'smtp_encryption';

    private const USERNAME_KEY = 'smtp_username';

    private const PASSWORD_KEY = 'smtp_password';

    private const FROM_ADDRESS_KEY = 'smtp_from_address';

    private const FROM_NAME_KEY = 'smtp_from_name';

    private const KEYS = [
        self::HOST_KEY,
        self::PORT_KEY,
        self::ENCRYPTION_KEY,
        self::USERNAME_KEY,
        self::PASSWORD_KEY,
        self::FROM_ADDRESS_KEY,
        self::FROM_NAME_KEY,
    ];

    /**
     * @return array{host: string, port: string, encryption: string, username: string, passwordConfigured: bool, fromAddress: string, fromName: string}
     */
    public static function formValues(): array
    {
        $settings = self::storedValues();

        return [
            'host' => (string) ($settings[self::HOST_KEY] ?? ''),
            'port' => (string) ($settings[self::PORT_KEY] ?? '587'),
            'encryption' => (string) ($settings[self::ENCRYPTION_KEY] ?? 'tls'),
            'username' => (string) ($settings[self::USERNAME_KEY] ?? ''),
            'passwordConfigured' => (string) ($settings[self::PASSWORD_KEY] ?? '') !== '',
            'fromAddress' => (string) ($settings[self::FROM_ADDRESS_KEY] ?? ''),
            'fromName' => (string) ($settings[self::FROM_NAME_KEY] ?? ''),
        ];
    }

    public static function hasStoredPassword(): bool
    {
        $settings = self::storedValues();

        return (string) ($settings[self::PASSWORD_KEY] ?? '') !== '';
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function save(array $settings): void
    {
        DB::transaction(function () use ($settings): void {
            AppraisalSetting::put(self::HOST_KEY, $settings['smtp_host']);
            AppraisalSetting::put(self::PORT_KEY, $settings['smtp_port']);
            AppraisalSetting::put(self::ENCRYPTION_KEY, $settings['smtp_encryption']);
            AppraisalSetting::put(self::USERNAME_KEY, $settings['smtp_username'] ?? '');
            AppraisalSetting::put(self::FROM_ADDRESS_KEY, $settings['smtp_from_address']);
            AppraisalSetting::put(self::FROM_NAME_KEY, $settings['smtp_from_name']);

            if (($settings['smtp_username'] ?? '') === '') {
                AppraisalSetting::put(self::PASSWORD_KEY, '');
            } elseif (($settings['smtp_password'] ?? '') !== '') {
                AppraisalSetting::put(self::PASSWORD_KEY, Crypt::encryptString($settings['smtp_password']));
            }
        });
    }

    public static function applySaved(): void
    {
        $settings = self::storedValues();
        $host = (string) ($settings[self::HOST_KEY] ?? '');

        if ($host === '') {
            return;
        }

        $username = (string) ($settings[self::USERNAME_KEY] ?? '');
        $encryptedPassword = (string) ($settings[self::PASSWORD_KEY] ?? '');
        $password = $username !== '' && $encryptedPassword !== ''
            ? Crypt::decryptString($encryptedPassword)
            : null;

        self::applyConfiguration([
            'smtp_host' => $host,
            'smtp_port' => (int) ($settings[self::PORT_KEY] ?? '587'),
            'smtp_encryption' => (string) ($settings[self::ENCRYPTION_KEY] ?? 'tls'),
            'smtp_username' => $username,
            'smtp_from_address' => (string) ($settings[self::FROM_ADDRESS_KEY] ?? ''),
            'smtp_from_name' => (string) ($settings[self::FROM_NAME_KEY] ?? ''),
        ], $password);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function applyInput(array $settings): void
    {
        $username = (string) ($settings['smtp_username'] ?? '');
        $password = null;

        if ($username !== '') {
            $password = ($settings['smtp_password'] ?? '') !== ''
                ? $settings['smtp_password']
                : self::storedPassword();
        }

        self::applyConfiguration($settings, $password);
    }

    private static function storedPassword(): ?string
    {
        $settings = self::storedValues();
        $encryptedPassword = (string) ($settings[self::PASSWORD_KEY] ?? '');

        return $encryptedPassword === '' ? null : Crypt::decryptString($encryptedPassword);
    }

    /**
     * @return array<string, string>
     */
    private static function storedValues(): array
    {
        return AppraisalSetting::query()
            ->whereIn('key', self::KEYS)
            ->pluck('value', 'key')
            ->map(fn ($value) => (string) $value)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private static function applyConfiguration(array $settings, ?string $password): void
    {
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'scheme' => $settings['smtp_encryption'] === 'ssl' ? 'smtps' : 'smtp',
                'url' => null,
                'host' => $settings['smtp_host'],
                'port' => (int) $settings['smtp_port'],
                'username' => ($settings['smtp_username'] ?? '') !== '' ? $settings['smtp_username'] : null,
                'password' => $password,
                'timeout' => 10,
                'local_domain' => $appHost ?: 'localhost',
            ],
            'mail.from' => [
                'address' => $settings['smtp_from_address'],
                'name' => $settings['smtp_from_name'],
            ],
        ]);

        Mail::purge('smtp');
    }
}
