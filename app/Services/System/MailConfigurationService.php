<?php

namespace App\Services\System;

use App\Models\Setting;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MailConfigurationService
{
    public function apply(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $mailer = (string) Setting::getValue('mail_mailer', config('mail.default', 'log'));
        $allowedMailers = ['smtp', 'log', 'array'];

        if (! in_array($mailer, $allowedMailers, true)) {
            $mailer = 'log';
        }

        config([
            'mail.default' => $mailer,
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.scheme' => Setting::getValue('mail_scheme') ?: null,
            'mail.mailers.smtp.host' => Setting::getValue('mail_host', config('mail.mailers.smtp.host')),
            'mail.mailers.smtp.port' => (int) Setting::getValue('mail_port', config('mail.mailers.smtp.port', 587)),
            'mail.mailers.smtp.username' => Setting::getValue('mail_username') ?: null,
            'mail.mailers.smtp.password' => Setting::getValue('mail_password') ?: null,
            'mail.mailers.smtp.timeout' => (int) Setting::getValue('mail_timeout', 20),
            'mail.from.address' => Setting::getValue('mail_from_address', config('mail.from.address')),
            'mail.from.name' => Setting::getValue('mail_from_name', config('app.name')),
        ]);

        try {
            app(MailManager::class)->purge('smtp');
        } catch (Throwable) {
            // The mail manager may not be resolved yet during early application boot.
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $mailer = (string) Setting::getValue('mail_mailer', config('mail.default', 'log'));
        $smtpConfigured = filled(Setting::getValue('mail_host'))
            && filled(Setting::getValue('mail_port'))
            && filled(Setting::getValue('mail_from_address'));

        return [
            'mailer' => $mailer,
            'smtp_configured' => $smtpConfigured,
            'from_address' => Setting::getValue('mail_from_address', config('mail.from.address')),
            'from_name' => Setting::getValue('mail_from_name', config('app.name')),
        ];
    }
}
