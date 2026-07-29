<?php

namespace App\Services;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Generates the notification content based on the event type (and, in the
 * future, the customer's language -- for now only 'es', see the limitation
 * in .env.example). Uses Blade views under resources/views/notifications/,
 * one per event type, with a generic fallback.
 */
class TemplateEngine
{
    /**
     * @param array<string, mixed> $detail
     *
     * @return array{subject: string, body: string}
     */
    public function render(string $action, array $detail, string $locale = 'es'): array
    {
        $view = 'notifications.' . Str::kebab($action);

        if (! View::exists($view)) {
            $view = 'notifications.default';
        }

        $subject = config("services.notifications.subject_map.{$action}", 'BP Notification');
        $body = trim(View::make($view, ['detail' => $detail, 'action' => $action])->render());

        return ['subject' => $subject, 'body' => $body];
    }
}
