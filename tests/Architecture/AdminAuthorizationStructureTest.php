<?php

declare(strict_types=1);

it('requires every concrete Admin Livewire component to expose explicit server-side authorization evidence', function (): void {
    $root = dirname(__DIR__, 2).'/app/Livewire/Admin';
    $components = [];
    $authorizationEvidence = [
        'authorize' => '/\$this\s*->\s*authorize\s*\(/',
        'gate' => '/\bGate\s*::\s*(?:authorize|allows|denies|check)\s*\(/',
        'can' => '/(?:\$this|\$[A-Za-z_]\w*)\s*->\s*can\s*\(/',
        'policy' => '/\b(?:policy|authorizeForUser)\s*\(/',
        'middleware' => '/\b(?:middleware|\brouteMiddleware\b)\s*\(/',
    ];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($files as $file) {
        /** @var SplFileInfo $file */
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = str_replace('\\', '/', $file->getPathname());
        if (str_contains($path, '/app/Livewire/Admin/Auth/')) {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        if (! is_string($source) || ! preg_match('/\bclass\s+\w+/', $source)) {
            continue;
        }

        $tokens = token_get_all($source);
        $code = '';
        foreach ($tokens as $token) {
            $code .= is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING], true)
                ? ' '
                : (is_array($token) ? $token[1] : $token);
        }

        $matches = array_keys(array_filter(
            $authorizationEvidence,
            static fn (string $pattern): bool => preg_match($pattern, $code) === 1,
        ));

        $components[$path] = $matches;
    }

    $missing = array_keys(array_filter(
        $components,
        static fn (array $matches): bool => $matches === [],
    ));

    expect($components)->not->toBeEmpty()
        ->and($missing)->toBe([]);
});
