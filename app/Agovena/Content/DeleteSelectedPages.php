<?php

declare(strict_types=1);

namespace App\Agovena\Content;

use App\Models\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Bulk counterpart of DeletePage: each page is authorized, deleted and audited on its own. */
final class DeleteSelectedPages
{
    public const MAX_SELECTION = 500;

    public function __construct(private readonly DeletePage $deletePage) {}

    /**
     * @param  list<int>  $ids
     * @return array{deleted: int, skipped: int, failed: int}
     */
    public function handle(array $ids): array
    {
        if (count($ids) > self::MAX_SELECTION) {
            throw ValidationException::withMessages(['selectedPageIds' => __('admin.content.pages.bulk_limit')]);
        }

        $result = ['deleted' => 0, 'skipped' => 0, 'failed' => 0];
        foreach (array_unique($ids) as $id) {
            try {
                $outcome = DB::transaction(function () use ($id): string {
                    $page = Page::query()->lockForUpdate()->find($id);
                    if ($page === null || Gate::denies('pages.manage', $page)) {
                        return 'skipped';
                    }
                    $this->deletePage->handle($page->id);

                    return 'deleted';
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
