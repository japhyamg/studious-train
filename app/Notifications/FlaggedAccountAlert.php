<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FlaggedAccountAlert extends Notification
{
    use Queueable;

    public function __construct(
        protected string $message,
        protected string $url
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => $this->message,
            'url' => $this->url,
            'type' => 'flagged_account',
        ];
    }
}
