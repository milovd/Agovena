<?php

use App\Agovena\Extensions\ExtensionCategory;
use Illuminate\Support\Facades\File;

test('make-extension scaffolds a valid category instead of integration', function () {
    $id = 'contract-kit-example';
    $root = base_path('extensions/payments/'.$id);

    try {
        $this->artisan('agovena:make-extension', [
            'id' => $id,
            '--category' => 'payment_gateway',
            '--force' => true,
        ])->assertSuccessful();

        $manifest = json_decode((string) File::get($root.'/extension.json'), true);
        expect($manifest)->toBeArray()
            ->and($manifest['category'])->toBe(ExtensionCategory::PaymentGateway->value)
            ->and(File::exists($root.'/src/ContractKitExampleExtension.php'))->toBeTrue();
    } finally {
        File::deleteDirectory($root);
    }
});

test('make-extension rejects unknown categories', function () {
    $this->artisan('agovena:make-extension', [
        'id' => 'bad-category-example',
        '--category' => 'integration',
    ])->assertFailed();

    expect(File::isDirectory(base_path('extensions/bad-category-example')))->toBeFalse()
        ->and(File::isDirectory(base_path('extensions/other/bad-category-example')))->toBeFalse();
});
