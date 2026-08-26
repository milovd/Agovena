<?php

declare(strict_types=1);

namespace App\Agovena\Imports;

use App\Agovena\Imports\Contracts\ImportAdapter;
use InvalidArgumentException;

final readonly class SourceProfileImportAdapter implements ImportAdapter
{
    /**
     * @param  array<string, list<string>>  $aliases
     */
    public function __construct(
        private string $source,
        private string $entity,
        private array $aliases,
    ) {}

    public function map(array $row, int $line): ImportCandidate
    {
        $payload = [];
        foreach ($this->aliases as $field => $aliases) {
            foreach ($aliases as $alias) {
                $value = $row[$alias] ?? null;
                if ($value !== null && trim((string) $value) !== '') {
                    $payload[$field] = trim((string) $value);
                    break;
                }
            }
        }

        $externalId = (string) ($payload['external_id'] ?? '');
        unset($payload['external_id']);
        if ($externalId === '') {
            throw new InvalidArgumentException("Missing external identifier on line {$line}.");
        }

        if ($this->entity === 'customer' && (! isset($payload['email']) || ! isset($payload['name']))) {
            throw new InvalidArgumentException("Customer email and name are required on line {$line}.");
        }

        return new ImportCandidate($this->entity, $this->source.':'.$externalId, $payload);
    }
}
