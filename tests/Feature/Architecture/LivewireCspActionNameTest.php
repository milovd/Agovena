<?php

declare(strict_types=1);

/*
 * Livewire runs in CSP-safe mode here. Its expression parser rejects JavaScript keywords as
 * method names, so wire:click="delete(1)" only logs a console warning and never reaches the
 * server, while Livewire::test()->call('delete') still passes. Component actions therefore
 * need non-keyword names such as deleteDiscount().
 */

/** @return list<string> */
function bladeViewsForExpressionScan(bool $packages = false): array
{
    $roots = [resource_path('views'), base_path('themes')];
    if ($packages) {
        $roots = [optionalPackagesRoot().DIRECTORY_SEPARATOR.'modules', optionalPackagesRoot().DIRECTORY_SEPARATOR.'extensions'];
    }

    $files = [];
    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }
        foreach (new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)), '/\.blade\.php$/') as $file) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

/** @return list<string> */
function keywordActionsIn(array $files): array
{
    $keywords = 'break|case|catch|class|const|continue|debugger|default|delete|do|else|enum|export|extends|finally|for|function|if|import|in|instanceof|new|return|super|switch|this|throw|try|typeof|var|void|while|with|yield|let|static|await';
    $offenders = [];

    foreach ($files as $path) {
        $blade = (string) file_get_contents($path);
        preg_match_all('/\s(?:wire:[\w.-]+|x-on:[\w.-]+|@[a-z][\w.-]*|x-init|x-data)\s*=\s*"([^"]*)"/', $blade, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[1] as [$expression, $offset]) {
            if (preg_match('/(?:^|[\s;(&|!?:,]|\$wire\.)('.$keywords.')\s*\(/', $expression, $keyword) === 1) {
                $offenders[] = basename($path).':'.(substr_count(substr($blade, 0, $offset), "\n") + 1).' '.$keyword[1].'()';
            }
        }
    }

    return $offenders;
}

test('Core and theme actions never call a JavaScript keyword as a method', function (): void {
    $files = bladeViewsForExpressionScan();

    expect(implode("\n", $files))->toContain('discounts')->toContain('addresses-panel')
        ->and(keywordActionsIn($files))->toBe([]);
});

test('optional package actions never call a JavaScript keyword as a method', function (): void {
    $files = bladeViewsForExpressionScan(packages: true);

    expect(keywordActionsIn($files))->toBe([]);
});
