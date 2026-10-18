<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Packages\PackageArtwork;
use App\Agovena\Packages\PackageCatalog;
use Illuminate\Http\Response;

final class PackageArtworkController
{
    public function __invoke(
        string $kind,
        string $id,
        ModuleManager $modules,
        ExtensionManager $extensions,
        PackageArtwork $artwork,
        PackageCatalog $catalog,
    ): Response {
        abort_unless(in_array($kind, ['module', 'extension'], true), 404);
        abort_unless(auth()->user()?->can($kind === 'module' ? 'modules.view' : 'extensions.view'), 403);

        $manifest = $kind === 'module' ? $modules->manifest($id) : $extensions->manifest($id);
        if ($manifest === null) {
            $rows = $kind === 'module' ? $catalog->modules() : $catalog->extensions();
            foreach ($rows as $row) {
                if ($row['id'] === $id) {
                    $manifest = $row['manifest'];

                    break;
                }
            }
        }
        abort_if($manifest === null, 404);

        $path = $artwork->resolve($manifest->path, $manifest->logo);
        abort_if($path === null, 404);

        return response((string) file_get_contents($path), 200, [
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
