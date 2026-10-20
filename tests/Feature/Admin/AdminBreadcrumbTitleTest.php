<?php

declare(strict_types=1);

use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('the customer properties page names itself in the topbar breadcrumb', function (): void {
    $html = $this->actingAs($this->createStaff())
        ->get(route('admin.customers.properties'))
        ->assertOk()
        ->getContent();

    expect($html)->toMatch('/<span aria-current="page">\s*'.preg_quote(e(__('admin.customer_properties.title')), '/').'\s*<\/span>/');
});
