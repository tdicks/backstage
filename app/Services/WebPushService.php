<?php

namespace App\Services;

use App\Models\NotificationPushSubscription;
use App\Models\User;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushService
{
    /**
     * @param  array{title: string, body: string, action_url: string|null, action_label?: string|null, type_key?: string}  $content
     */
    public function sendToUser(User $user, array $content): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = json_encode([
            'title' => $content['title'],
            'body' => $content['body'],
            'action_url' => $content['action_url'],
            'action_label' => $content['action_label'] ?? 'Open in Backstage',
            'type_key' => $content['type_key'] ?? null,
        ]);

        if ($payload === false) {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => (string) config('services.webpush.vapid.subject'),
                'publicKey' => (string) config('services.webpush.vapid.public_key'),
                'privateKey' => (string) config('services.webpush.vapid.private_key'),
            ],
        ]);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding ?: null,
                ]),
                $payload,
                ['TTL' => 300]
            );
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            if ($report->isSubscriptionExpired()) {
                $expiredEndpoint = $report->getEndpoint();

                NotificationPushSubscription::query()
                    ->where('user_id', $user->id)
                    ->where('endpoint_hash', hash('sha256', $expiredEndpoint))
                    ->delete();
            }
        }
    }

    public function isConfigured(): bool
    {
        return filled(config('services.webpush.vapid.subject'))
            && filled(config('services.webpush.vapid.public_key'))
            && filled(config('services.webpush.vapid.private_key'));
    }

    /**
     * @param  array{endpoint: string, public_key: string, auth_token: string, content_encoding: string|null}  $subscriptionData
     */
    public function validateSubscriptionPayload(array $subscriptionData): bool
    {
        try {
            Subscription::create([
                'endpoint' => $subscriptionData['endpoint'],
                'publicKey' => $subscriptionData['public_key'],
                'authToken' => $subscriptionData['auth_token'],
                'contentEncoding' => $subscriptionData['content_encoding'] ?: null,
            ]);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
