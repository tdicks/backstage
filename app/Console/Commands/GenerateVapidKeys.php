<?php

namespace App\Console\Commands;

use App\Services\VapidKeyGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('app:vapid-generate {--subject= : Optional VAPID subject, e.g. mailto:admin@example.com} {--json : Output raw JSON only}')]
#[Description('Generate VAPID keys for push notifications')]
class GenerateVapidKeys extends Command
{
    public function __construct(private readonly VapidKeyGenerator $vapidKeyGenerator)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $keys = $this->vapidKeyGenerator->generate();
        } catch (Throwable $exception) {
            $this->error('Failed to generate VAPID keys: '.$exception->getMessage());

            return self::FAILURE;
        }

        $subject = (string) ($this->option('subject') ?: 'mailto:admin@example.com');

        if ($this->option('json')) {
            $this->line(json_encode([
                'subject' => $subject,
                'publicKey' => $keys['publicKey'],
                'privateKey' => $keys['privateKey'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('VAPID keys generated successfully. Add these to your .env file:');
        $this->newLine();
        $this->line('WEBPUSH_VAPID_SUBJECT='.$subject);
        $this->line('WEBPUSH_VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('WEBPUSH_VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->comment('After saving .env, run: php artisan config:clear');

        return self::SUCCESS;
    }
}
