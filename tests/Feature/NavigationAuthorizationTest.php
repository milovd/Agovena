<?php

declare(strict_types=1);

use App\Models\Menu;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('does not initialize navigation menus for staff without navigation access', function () {
    $staff = $this->createStaff(permissions: ['admin.access']);

    $this->actingAs($staff)
        ->get(route('admin.appearance.navigation'))
        ->assertForbidden();

    expect(Menu::query()->count())->toBe(0);
});
