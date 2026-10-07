<?php

declare(strict_types=1);

/**
 * Inline relative @import statements the way Vite does, so tests can
 * assert on what a CSS entry actually ships.
 */
function cssWithImports(string $path): string
{
    $css = file_get_contents($path);
    if ($css === false) {
        throw new RuntimeException("CSS file [{$path}] is missing.");
    }

    return (string) preg_replace_callback(
        "/^@import\\s+'([^']+)';$/m",
        static fn (array $match): string => cssWithImports(dirname($path).DIRECTORY_SEPARATOR.$match[1]),
        $css,
    );
}

/**
 * Relative @import targets of a CSS file, in order.
 *
 * @return list<string>
 */
function cssImports(string $path): array
{
    preg_match_all("/^@import\\s+'([^']+)';$/m", (string) file_get_contents($path), $matches);

    return $matches[1];
}

/**
 * Relative ES module import specifiers of a JavaScript file, in order.
 *
 * @return list<string>
 */
function jsRelativeImports(string $path): array
{
    preg_match_all("/^import\\s+[^;]*?from\\s+'(\\.[^']+)';$/m", (string) file_get_contents($path), $matches);

    return $matches[1];
}
