<?php

declare(strict_types=1);

namespace App\Agovena\Notifications;

final readonly class NotificationTemplateDefinition
{
    /**
     * @param  list<string>  $placeholders
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $placeholders,
    ) {}
}
