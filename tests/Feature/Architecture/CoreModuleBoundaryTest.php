<?php

declare(strict_types=1);

/*
 * Core must not depend on optional packages. These are the known couplings that
 * remain, each with the reason it is still in Core. Moving one into its package
 * needs a package extension point and a coordinated package release. Do not add
 * to this list: give the package a capability, contract or registration instead.
 *
 * @return array<string, string> app-relative path => reason
 */
function knownCoreToPackageCouplings(): array
{
    return [
        // By design.
        'Console/Commands/AgovenaSeedDemoCommand.php' => 'Seeds sample data for every first-party package (local demo only).',
        'Agovena/Scaffolding/ScaffoldingGenerator.php' => 'Writes the package namespaces into newly scaffolded packages.',
        // Follow-ups.
        'Agovena/Admin/DashboardMetrics.php' => 'Active services tile counts provisioning service_instances.',
        'Agovena/Admin/GettingStartedChecklist.php' => 'Provisioning, downloads and digital-delivery steps; Mollie/Stripe payment step.',
        'Agovena/Imports/ImportExecutor.php' => 'Imports provisioning service instances by class name.',
        'Agovena/Imports/ImportRollback.php' => 'Rolls back imported provisioning service instances by class name.',
        'Agovena/Recurring/Http/Livewire/Customer/SubscriptionShow.php' => 'Lists provisioning service instances of a subscription.',
        'Agovena/Recurring/SubscriptionService.php' => 'Links provisioning service instances to new subscriptions.',
        'Console/Commands/AgovenaVerifyProvidersCommand.php' => 'Refuses a live Mollie key in sandbox verification.',
        'Livewire/Admin/Products/Edit.php' => 'Lists Domains registrar and DNS providers by registry class name.',
    ];
}

test('core does not depend on optional packages outside the known couplings', function () {
    $allowed = knownCoreToPackageCouplings();
    $packageIds = 'provisioning|downloads|digital-delivery|domains|events|postnl|mollie|stripe|paypal|paddle|tebex';
    $packageTables = 'provisioning_servers|service_instances|digital_assets|digital_secret_items|postnl_shipments|domain_registrations|event_tickets';
    $checks = [
        'imports or names a package class' => '/(?<!App\\\\)(?<!App\\\\\\\\)Agovena\\\\{1,2}(?:Modules|Extensions)\\\\{1,2}/',
        'checks whether a package is enabled by its ID' => "/isEnabled\\('(?:{$packageIds})'\\)/",
        'uses a table a package owns' => "/(?:hasTable|table|exists)\\('(?:{$packageTables})'/",
    ];

    $violations = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS)) as $file) {
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(app_path()) + 1));
        if ($file->getExtension() !== 'php' || isset($allowed[$relative]) || str_contains($relative, '/migrations/')) {
            continue;
        }

        $contents = (string) file_get_contents($file->getPathname());
        foreach ($checks as $problem => $pattern) {
            if (preg_match($pattern, $contents) === 1) {
                $violations[] = "{$relative} {$problem}";
            }
        }
    }

    expect($violations)->toBe([]);
});

test('every known coupling still exists, so the list stays accurate', function () {
    foreach (array_keys(knownCoreToPackageCouplings()) as $relative) {
        expect(is_file(app_path($relative)))->toBeTrue("{$relative} no longer exists; remove it from the list");
    }
});
