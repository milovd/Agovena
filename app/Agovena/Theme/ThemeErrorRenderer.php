<?php

declare(strict_types=1);

namespace App\Agovena\Theme;

use Carbon\CarbonInterface;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ThemeErrorRenderer
{
    public function __construct(
        private readonly ThemeManager $themes,
    ) {}

    public function render(\Throwable $exception, Request $request): ?Response
    {
        if (
            $request->expectsJson()
            || $exception instanceof AuthenticationException
            || $exception instanceof ValidationException
        ) {
            return null;
        }

        $status = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : 500;

        return $this->renderStatus($status);
    }

    public function renderStatus(int $status): ?Response
    {
        if ($status < 400 || $status > 599) {
            return null;
        }

        try {
            $theme = $this->themes->errorTheme($status);
            if ($theme === null) {
                return null;
            }

            return $this->renderThemeView($theme, $theme->errorView($status), $status);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Storefront maintenance page (HTTP 503). Falls back to the Theme 503 page
     * when no Theme ships `errors.maintenance`. The message is plain text.
     */
    public function renderMaintenance(?string $message, ?CarbonInterface $endsAt): ?Response
    {
        try {
            $data = [
                'maintenanceMessage' => $message,
                'maintenanceEndsAt' => $endsAt,
            ];

            $theme = $this->themes->maintenanceTheme();
            if ($theme !== null) {
                return $this->renderThemeView($theme, Theme::MAINTENANCE_VIEW, 503, $data);
            }

            $theme = $this->themes->errorTheme(503);
            if ($theme === null) {
                return null;
            }

            return $this->renderThemeView($theme, $theme->errorView(503), 503, $data);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderThemeView(Theme $theme, string $view, int $status, array $data = []): Response
    {
        View::prependLocation($theme->viewsPath);

        $themeConfig = null;
        try {
            $themeConfig = $this->themes->config($theme);
        } catch (\Throwable) {
            // Theme CSS and its static fallback tokens still render during outages.
        }

        return response()->view($view, [
            ...$data,
            'status' => $status,
            'theme' => $theme,
            'themeConfig' => $themeConfig,
        ], $status);
    }
}
