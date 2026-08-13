<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Agovena\Installation\ApplicationSchemaStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class SchemaUpdateRequiredController
{
    public function __invoke(ApplicationSchemaStatus $schema): Response|RedirectResponse
    {
        if ($schema->isCurrent()) {
            return redirect()->route('storefront.home');
        }

        return response()->view('schema.update-required', $schema->viewData(), 503);
    }
}
