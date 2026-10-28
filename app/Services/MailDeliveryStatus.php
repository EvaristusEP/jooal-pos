<?php

namespace App\Services;

class MailDeliveryStatus
{
    public function current(): array
    {
        $mailer = (string) config('mail.default');
        $settings = (array) config('mail.mailers.'.$mailer, []);
        $transport = (string) ($settings['transport'] ?? $mailer);
        $host = trim((string) ($settings['host'] ?? ''));
        $username = trim((string) ($settings['username'] ?? ''));
        $password = (string) ($settings['password'] ?? '');
        $fromAddress = trim((string) config('mail.from.address', ''));
        $port = (int) ($settings['port'] ?? 0);
        $configured = $transport === 'smtp'
            && $host !== ''
            && strtolower($host) !== 'example.com'
            && $port > 0 && $port <= 65535
            && $username !== '' && $password !== ''
            && filter_var($fromAddress, FILTER_VALIDATE_EMAIL) !== false
            && substr(strtolower($fromAddress), -12) !== '@example.com';

        return [
            'mailer' => $mailer,
            'transport' => $transport,
            'configured' => $configured,
            'message' => $configured
                ? 'Konfigurasi SMTP tampak lengkap. Keterjangkauan server belum diuji; status terkirim hanya diberikan setelah transport menerima email.'
                : 'Pengiriman email belum siap. Atur mailer SMTP dan alamat pengirim melalui konfigurasi environment pada proses aplikasi, lalu mulai ulang server. Kredensial tidak ditampilkan di halaman ini.',
        ];
    }
}
