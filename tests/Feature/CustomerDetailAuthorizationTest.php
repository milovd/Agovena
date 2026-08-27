<?php

declare(strict_types=1);

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\AdminRoleAssignmentPolicy;
use App\Agovena\Credits\CustomerCreditLedger;
use App\Agovena\Customer\Properties\CustomerPropertyService;
use App\Livewire\Admin\Customers\Show as AdminCustomerShow;
use App\Models\Customer;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('rechecks customer view permission on mounted component interactions', function (): void {
    $staff = $this->createStaff([], ['customers.view']);
    $customer = Customer::factory()->create();
    $this->actingAs($staff);
    $component = new AdminCustomerShow;
    $component->mount($customer, app(CustomerPropertyService::class));

    $staff->roles()->firstOrFail()->revokePermissionTo('customers.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(fn () => $component->selectPanel('commerce'))
        ->toThrow(AuthorizationException::class);

    expect(fn () => $component->render(
        app(AdminRegistrar::class),
        app(AdminRoleAssignmentPolicy::class),
        app(CustomerCreditLedger::class),
        app(CustomerPropertyService::class),
    ))->toThrow(AuthorizationException::class);
});
