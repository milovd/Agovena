<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Agovena\Packages\PackageArtwork;
use App\Agovena\Theme\ThemeManager;
use Illuminate\Http\Response;

final class ThemePreviewController
{
    public function __invoke(string $id, ThemeManager $themes, PackageArtwork $artwork): Response
    {
        abort_unless(auth()->user()?->can('theme.view'), 403);

        $theme = $themes->find($id);
        abort_if($theme === null, 404);

        $path = $artwork->resolve($theme->basePath, $theme->previewReference, themePreview: true);
        abort_if($path === null, 404);

        return response((string) file_get_contents($path), 200, [
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
