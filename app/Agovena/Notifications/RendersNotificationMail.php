<?php

declare(strict_types=1);

namespace App\Agovena\Notifications;

use App\Models\NotificationTemplate;
use Illuminate\Notifications\Messages\MailMessage;

final class RendersNotificationMail
{
    public function __construct(private readonly NotificationTemplateCatalog $catalog) {}

    public function isEnabled(string $key): bool
    {
        $row = NotificationTemplate::query()->where('key', $key)->first();

        return $row === null || $row->enabled;
    }

    /**
     * @param  array<string, mixed>  $vars
     */
    public function mail(string $key, array $vars): MailMessage
    {
        $definition = $this->catalog->find($key);
        $allowed = $definition === null ? array_keys($vars) : $definition->placeholders;
        $safe = $this->scalarVars($vars, $allowed);

        $stored = NotificationTemplate::query()->where('key', $key)->first();
        $custom = $stored instanceof NotificationTemplate
            && filled($stored->subject)
            && filled($stored->body);

        if ($custom) {
            $subject = $this->interpolate((string) $stored->subject, $safe);
            $body = $this->interpolate((string) $stored->body, $safe);
        } else {
            $subject = (string) __('notifications.'.$key.'.subject', $this->langVars($safe));
            $body = $this->defaultBody($key, $safe);
        }

        $message = (new MailMessage)->subject($subject);
        foreach (preg_split("/\r\n|\r|\n/", $body) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $message->line($line);
            }
        }

        $actionUrl = trim((string) ($safe['action_url'] ?? ''));
        if ($actionUrl !== '') {
            $label = trim((string) ($safe['action_label'] ?? ''));
            if ($label === '') {
                $label = (string) __('notifications.'.$key.'.action');
            }
            $message->action($label, $actionUrl);
        }

        return $message;
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function editableDefaults(string $key): array
    {
        return [
            'subject' => $this->toPlaceholderSyntax((string) __('notifications.'.$key.'.subject')),
            'body' => $this->toPlaceholderSyntax($this->defaultBodyTemplate($key)),
        ];
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function defaultBody(string $key, array $vars): string
    {
        $langVars = $this->langVars($vars);
        $greeting = (string) __('notifications.greeting', $langVars);
        $lineKey = 'notifications.'.$key.'.line';
        $line = __($lineKey, $langVars);
        if ($line === $lineKey) {
            $line = (string) ($vars['detail'] ?? '');
        }
        $total = $this->omitsTotal($key)
            ? ''
            : (string) __('notifications.total', $langVars);

        $parts = array_values(array_filter([$greeting, is_string($line) ? $line : '', $total]));

        return implode("\n\n", $parts);
    }

    private function defaultBodyTemplate(string $key): string
    {
        $greeting = (string) __('notifications.greeting');
        $lineKey = 'notifications.'.$key.'.line';
        $line = __($lineKey);
        if ($line === $lineKey) {
            $line = ':detail';
        }
        $total = $this->omitsTotal($key)
            ? ''
            : (string) __('notifications.total');

        $parts = array_values(array_filter([$greeting, is_string($line) ? $line : '', $total]));

        return implode("\n\n", $parts);
    }

    private function omitsTotal(string $key): bool
    {
        return in_array($key, [
            'ticket_replied',
            'subscription_cancelled',
            'shipment_sent',
            'subscription_renewal',
            'subscription_renewal_paid',
            'subscription_renewal_failed',
            'subscription_past_due',
            'plan_change_applied',
            'service_activated',
            'service_suspended',
            'digital_entitlement_granted',
            'digital_secret_delivered',
            'event_ticket_issued',
        ], true);
    }

    /**
     * @param  list<string>  $allowed
     * @param  array<string, mixed>  $vars
     * @return array<string, string>
     */
    private function scalarVars(array $vars, array $allowed): array
    {
        $out = [];
        foreach ($allowed as $key) {
            $value = $vars[$key] ?? '';
            if (! is_scalar($value)) {
                $out[$key] = '';

                continue;
            }
            $out[$key] = str_replace(["\r", "\n"], ' ', (string) $value);
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $vars
     * @return array<string, string>
     */
    private function langVars(array $vars): array
    {
        $mapped = $vars;
        if (isset($vars['subject'])) {
            $mapped['subject'] = $vars['subject'];
        }

        return $mapped;
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function interpolate(string $text, array $vars): string
    {
        $stripped = (string) preg_replace('/@php\b.*?@endphp/s', '', $text);
        $stripped = (string) preg_replace('/\{!!.*?!!\}/s', '', $stripped);
        $replaced = (string) preg_replace_callback(
            '/\{\{.*?\}\}/s',
            static function (array $match) use ($vars): string {
                if (preg_match('/^\{\{\s*([a-z0-9_]+)\s*\}\}$/i', $match[0], $inner) !== 1) {
                    return '';
                }

                return $vars[strtolower($inner[1])] ?? '';
            },
            $stripped,
        );

        return trim((string) preg_replace('/@\w+/', '', $replaced));
    }

    private function toPlaceholderSyntax(string $text): string
    {
        return (string) preg_replace('/(?<!:):([a-z_]+)/', '{{$1}}', $text);
    }
}
