<?php

declare(strict_types=1);

namespace App\Agovena\Content;

use App\Agovena\Audit\AuditLogger;
use App\Models\Page;
use Illuminate\Support\Facades\DB;

final class DeletePage
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $page = Page::query()->find($id);
            if ($page === null) {
                return;
            }

            $before = $page->only(['title', 'slug', 'status']);
            $page->delete();
            $this->audit->log('page.deleted', $page, before: $before);
        });
    }
}
