<?php

declare(strict_types=1);

namespace App\Agovena\Theme;

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

            View::prependLocation($theme->viewsPath);

            $themeConfig = null;
            try {
                $themeConfig = $this->themes->config($theme);
            } catch (\Throwable) {
                // Theme CSS and its static fallback tokens still render during outages.
            }

            return response()->view($theme->errorView($status), [
                'status' => $status,
                'theme' => $theme,
                'themeConfig' => $themeConfig,
            ], $status);
        } catch (\Throwable) {
            return null;
        }
    }
}
