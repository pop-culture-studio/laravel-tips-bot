<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Chat\Prompt;
use App\Notifications\ReleaseNotification;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use OpenAI\Laravel\Facades\OpenAI;
use Revolution\Nostr\Notifications\NostrRoute;

class ReleaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:release';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @throws RequestException
     */
    public function handle(): void
    {
        $response = Http::baseUrl('https://api.github.com/repos/')
                        ->get('laravel/framework/releases', [
                            'per_page' => 5,
                        ])->throw();

        $response->collect()
                 ->reverse()
                 ->each($this->release(...));
    }

    protected function release(array $release)
    {
        $date = Carbon::parse(Arr::get($release, 'published_at', 'UTC'));

        if ($date->tz(config('app.tz'))->addDay()->lessThan(now())) {
            return;
        }

        $ver = Arr::get($release, 'tag_name');
        $url = Arr::get($release, 'html_url');
        $body = Arr::get($release, 'body');

        $note = $this->chat($body);

        if (blank($note)) {
            return;
        }

        Notification::route('discord', config('services.discord.channel'))
                    ->route('nostr', NostrRoute::to(sk: config('nostr.keys.sk')))
                    ->notify(new ReleaseNotification(ver: $ver, url: $url, note: $note));
    }

    protected function chat(string $body): string
    {
        $response = OpenAI::chat()->create(
            Prompt::make(
                system: 'Act as a good programmer who knows Laravel.',
                prompt: fn () => '次のリリースノートを日本語訳。'.PHP_EOL.PHP_EOL.trim($body),
            )->toArray()
        );

        $content = trim(Arr::get($response, 'choices.0.message.content'));
        $this->info($content);

        $this->line('strlen: '.mb_strlen($content));
        $this->line('total_tokens: '.Arr::get($response, 'usage.total_tokens'));

        return $content;
    }
}