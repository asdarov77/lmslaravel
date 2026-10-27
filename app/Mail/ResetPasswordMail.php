<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Письмо со ссылкой восстановления пароля.
 *
 * Ссылка ведёт на страницу приложения с токеном в query: токен не
 * должен попадать в адресную строку как часть пути, иначе он остаётся
 * в истории браузера и на прокси в access-log.
 */
class ResetPasswordMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $fio,
        public readonly string $token,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Восстановление пароля',
        );
    }

    public function content(): Content
    {
        $url = rtrim((string) config('app.url'), '/')
            . '/password/reset?token=' . urlencode($this->token)
            . '&fio=' . urlencode($this->fio);

        return new Content(
            view: 'emails.reset-password',
            with: [
                'fio' => $this->fio,
                'url' => $url,
                'ttl' => 60,
            ],
        );
    }
}