<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\Discord\DiscordChannel;
use NotificationChannels\Discord\DiscordMessage;
use Revolution\Nostr\Notifications\NostrChannel;
use Revolution\Nostr\Notifications\NostrMessage;
use Revolution\Nostr\Tags\HashTag;

class ReleaseNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected string $ver,
        protected string $url,
        protected string $note,
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [
            //'mail',
            DiscordChannel::class,
            NostrChannel::class,
        ];
    }

    public function toDiscord(object $notifiable): DiscordMessage
    {
        $content = collect([
            'laravel/framework '.$this->ver,
            $this->url,
            '',
            $this->note,
        ])->join(PHP_EOL);

        return DiscordMessage::create(body: Str::truncate($content, 1800));
    }

    public function toNostr(object $notifiable): NostrMessage
    {
        $content = collect([
            'laravel/framework '.$this->ver,
            $this->url,
            '',
            $this->note,
        ])->join(PHP_EOL);

        return NostrMessage::create(
            content: $content.PHP_EOL.'#laravel',
            tags: [HashTag::make(t: 'laravel')],
        );
    }
}
