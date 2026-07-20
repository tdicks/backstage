<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppActivityNotification extends Notification
{
    use Queueable;

    /**
     * @param  array{title: string, body: string, action_url: string|null, action_label: string|null}  $content
     */
    public function __construct(
        private readonly string $typeKey,
        private readonly array $content,
        private readonly ?int $actorUserId = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['database'];
        }

        $delivery = NotificationSettings::effectiveDeliveryPreferences($notifiable, $this->typeKey);

        $channels = ['database'];

        if ($delivery['email']) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $delivery = $notifiable instanceof User
            ? NotificationSettings::effectiveDeliveryPreferences($notifiable, $this->typeKey)
            : ['popup' => false, 'email' => false, 'text' => false, 'enabled' => true];

        return [
            'type_key' => $this->typeKey,
            'title' => $this->content['title'],
            'body' => $this->content['body'],
            'action_url' => $this->content['action_url'],
            'action_label' => $this->content['action_label'] ?? 'Open',
            'popup' => $delivery['popup'],
            'email' => $delivery['email'],
            'text' => $delivery['text'],
            'actor_user_id' => $this->actorUserId,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->content['title'])
            ->view('emails.notification', [
                'title' => $this->content['title'],
                'body' => $this->content['body'],
                'actionUrl' => $this->content['action_url'],
                'actionLabel' => $this->content['action_label'] ?? 'Open in Backstage',
            ]);
    }
}
