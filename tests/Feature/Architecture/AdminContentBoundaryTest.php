<?php

declare(strict_types=1);

it('keeps image promotion and storage compensation outside the Pages Livewire adapter', function (): void {
    $adapter = file_get_contents(app_path('Livewire/Admin/Content/PageForm.php'));

    expect($adapter)->not->toContain('DB::transaction(', 'Storage::disk(', 'promoteRetainedImages(');
});
