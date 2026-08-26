<?php

declare(strict_types=1);

namespace App\Agovena\Notifications;

use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

final class RendersNotificationMail
{
    public function __construct(
        private readonly NotificationTemplateCatalog $catalog,
        private readonly NotificationChannelPolicy $policy,
    ) {}

    public function isEnabled(string $key, ?object $notifiable = null): bool
    {
        return $this->policy->allows($key, 'mail', $notifiable instanceof User ? $notifiable : null);
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
        $format = $custom && in_array($stored->mail_format, ['plain', 'markdown', 'html'], true)
            ? (string) $stored->mail_format
            : 'plain';

        if ($custom) {
            $subject = $this->interpolate((string) $stored->subject, $safe, false);
            $body = $this->interpolate((string) $stored->body, $safe, $format !== 'plain');
        } else {
            $subject = (string) __('notifications.'.$key.'.subject', $this->langVars($safe));
            $body = $this->defaultBody($key, $safe);
        }

        $actionUrl = $this->safeUrl($safe['action_url'] ?? '');
        $actionLabel = trim((string) ($safe['action_label'] ?? '')) ?: (string) __('notifications.'.$key.'.action');
        $message = (new MailMessage)->subject($subject);

        if ($format === 'plain') {
            foreach (preg_split("/\r\n|\r|\n/", $body) ?: [] as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $message->line($line);
                }
            }

            if ($actionUrl !== '') {
                $message->action($actionLabel, $actionUrl);
            }

            return $message;
        }

        $bodyHtml = $format === 'markdown'
            ? $this->markdownToHtml($body)
            : $this->sanitizeHtml($body);

        return $message->view('mail.notification', [
            'bodyHtml' => $bodyHtml,
            'actionLabel' => $actionLabel,
            'actionUrl' => $actionUrl,
        ]);
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
        return $vars;
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function interpolate(string $text, array $vars, bool $escapeValues): string
    {
        $stripped = (string) preg_replace('/@php\b.*?@endphp/s', '', $text);
        $stripped = (string) preg_replace('/\{!!.*?!!\}/s', '', $stripped);
        $replaced = (string) preg_replace_callback(
            '/\{\{.*?\}\}/s',
            static function (array $match) use ($vars, $escapeValues): string {
                if (preg_match('/^\{\{\s*([a-z0-9_]+)\s*\}\}$/i', $match[0], $inner) !== 1) {
                    return '';
                }

                $value = $vars[strtolower($inner[1])] ?? '';

                return $escapeValues ? e($value) : $value;
            },
            $stripped,
        );

        return trim((string) preg_replace('/@\w+/', '', $replaced));
    }

    private function toPlaceholderSyntax(string $text): string
    {
        return (string) preg_replace('/(?<!:):([a-z_]+)/', '{{$1}}', $text);
    }

    private function safeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : '';
    }

    private function markdownToHtml(string $markdown): string
    {
        return $this->sanitizeHtml(Str::markdown($markdown));
    }

    private function sanitizeHtml(string $html): string
    {
        if (! class_exists(\DOMDocument::class)) {
            return strip_tags($html, '<p><br><strong><em><ul><ol><li><h1><h2><h3><a><img><table><tbody><tr><td><th>');
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->loadHTML('<!doctype html><html><body>'.$html.'</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $allowedTags = ['a', 'blockquote', 'br', 'div', 'em', 'h1', 'h2', 'h3', 'img', 'li', 'ol', 'p', 'strong', 'table', 'tbody', 'td', 'th', 'tr', 'ul'];
        $allowedAttributes = [
            'a' => ['href'],
            'img' => ['src', 'alt', 'width', 'height'],
        ];
        $nodes = [];
        foreach ($document->getElementsByTagName('*') as $node) {
            $nodes[] = $node;
        }

        foreach (array_reverse($nodes) as $node) {
            if (in_array($node->nodeName, ['html', 'body'], true)) {
                continue;
            }

            if (! in_array($node->nodeName, $allowedTags, true)) {
                $node->parentNode?->removeChild($node);

                continue;
            }

            $allowed = $allowedAttributes[$node->nodeName] ?? [];
            if (! $node->hasAttributes()) {
                continue;
            }

            $attributes = [];
            foreach ($node->attributes as $attribute) {
                $attributes[] = $attribute->name;
            }
            foreach ($attributes as $attribute) {
                if (! in_array($attribute, $allowed, true)) {
                    $node->removeAttribute($attribute);

                    continue;
                }

                $value = $node->getAttribute($attribute);
                if (in_array($attribute, ['href', 'src'], true) && $this->safeUrl($value) === '') {
                    $node->removeAttribute($attribute);
                }
            }
        }

        $body = $document->getElementsByTagName('body')->item(0);
        if (! $body instanceof \DOMElement) {
            return '';
        }

        $result = '';
        foreach ($body->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return trim($result);
    }
}
