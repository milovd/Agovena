<?php

declare(strict_types=1);

namespace App\Agovena\Content;

use App\Agovena\Audit\AuditLogger;
use App\Models\Page;
use Illuminate\Support\Facades\DB;

final class SavePage
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array{title: string, slug: string, body?: string|null, status: string} $data */
    public function create(array $data): Page
    {
        return DB::transaction(function () use ($data): Page {
            $page = Page::query()->create($data);
            $this->audit->log('page.created', $page, after: $page->only(['title', 'slug', 'status']));

            return $page;
        });
    }

    /** @param array{title: string, slug: string, body?: string|null, status: string} $data */
    public function update(int $id, array $data): ?Page
    {
        return DB::transaction(function () use ($id, $data): ?Page {
            $page = Page::query()->find($id);
            if ($page === null) {
                return null;
            }

            $before = $page->only(['title', 'slug', 'status']);
            $page->fill($data);

            if ($page->isDirty()) {
                $page->save();
                $this->audit->logChange('page.updated', $page, $before, $page->only(['title', 'slug', 'status']));
            }

            return $page;
        });
    }
}
