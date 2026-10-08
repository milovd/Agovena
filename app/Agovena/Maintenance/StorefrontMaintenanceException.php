<?php

declare(strict_types=1);

namespace App\Agovena\Maintenance;

use App\Agovena\Api\ApiError;
use App\Agovena\Theme\ThemeErrorRenderer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown for a blocked request while maintenance is on. Being an exception
 * lets the same check stop Livewire updates replayed through persistent
 * middleware, where a returned response would be ignored.
 */
final class StorefrontMaintenanceException extends HttpException
{
    public function __construct(
        public readonly MaintenanceState $state,
        int $retryAfterSeconds,
    ) {
        parent::__construct(503, 'Storefront maintenance', null, [
            'Retry-After' => (string) $retryAfterSeconds,
        ]);
    }

    public function render(Request $request): Response
    {
        $wantsJson = $request->is('api', 'api/*')
            || ($request->expectsJson() && ! $request->hasHeader('X-Livewire'));

        $response = $wantsJson
            ? ApiError::json('maintenance', $this->state->message ?? __('api.maintenance'), 503)
            : $this->html();

        foreach ($this->getHeaders() as $name => $value) {
            $response->headers->set($name, $value);
        }

        // A maintenance response is not content: keep it out of caches and indexes.
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    private function html(): Response
    {
        $themed = null;
        try {
            $themed = app(ThemeErrorRenderer::class)->renderMaintenance(
                $this->state->message,
                $this->state->endsAt,
            );
        } catch (\Throwable) {
            $themed = null;
        }

        if ($themed !== null) {
            return $themed;
        }

        $text = __('errors.maintenance.heading');
        if ($this->state->message !== null) {
            $text .= "\n\n".$this->state->message;
        }

        return response($text, 503, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
