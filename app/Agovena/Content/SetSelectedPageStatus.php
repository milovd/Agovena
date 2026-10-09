<?php

declare(strict_types=1);

namespace App\Agovena\Content;

use App\Models\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

final class SetSelectedPageStatus
{
    public function __construct(private readonly SavePage $savePage) {}

    /**
     * @param  list<int>  $ids
     * @return array{updated: int, unchanged: int, skipped: int, failed: int}
     */
    public function handle(array $ids, string $status): array
    {
        if (! in_array($status, ['draft', 'published'], true)) {
            throw ValidationException::withMessages(['bulkStatus' => __('admin.content.pages.bulk_invalid_status')]);
        }
        if (count($ids) > 500) {
            throw ValidationException::withMessages(['selectedPageIds' => __('admin.content.pages.bulk_limit')]);
        }

        $result = ['updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0];

        foreach (array_unique($ids) as $id) {
            try {
                $outcome = DB::transaction(function () use ($id, $status): string {
                    $page = Page::query()->lockForUpdate()->find($id);
                    if ($page === null || Gate::denies('pages.manage', $page)) {
                        return 'skipped';
                    }

                    if ($page->status === $status) {
                        return 'unchanged';
                    }

                    $saved = $this->savePage->update($id, [
                        'title' => $page->title,
                        'slug' => $page->slug,
                        'status' => $status,
                    ]);

                    return $saved === null ? 'skipped' : 'updated';
                });
                $result[$outcome]++;
            } catch (Throwable $exception) {
                report($exception);
                $result['failed']++;
            }
        }

        return $result;
    }
}
