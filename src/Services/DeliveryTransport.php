<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class DeliveryTransport
{
    public function send(string $email, Mailable $mailable): void
    {
        Mail::mailer($this->mailer())->to($email)->send($mailable);
    }

    public function provider(): string
    {
        return Str::lower((string) (config('filagora-newsletters.delivery.provider') ?? config('mail.default')));
    }

    public function mailer(): string
    {
        return $this->provider();
    }

    public function isProvider(string $provider): bool
    {
        return $this->provider() === Str::lower($provider);
    }
}
