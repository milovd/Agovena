<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Agovena\Modules\Digital\Models\DigitalAsset;
use Agovena\Modules\Digital\Models\DigitalEntitlement;
use Agovena\Modules\DigitalDelivery\Models\DigitalSecretDelivery;
use Agovena\Modules\DigitalDelivery\Models\DigitalSecretItem;
use Agovena\Modules\Domains\Enums\DomainRegistrationStatus;
use Agovena\Modules\Domains\Models\DomainRegistration;
use Agovena\Modules\Events\Models\EventTicket;
use Agovena\Modules\Events\Models\EventTicketType;
use Agovena\Modules\Provisioning\Enums\ServiceInstanceStatus;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use App\Agovena\Demo\DemoAccountPasswords;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Packages\PackageInstaller;
use App\Agovena\Packages\PackageSource;
use App\Agovena\Physical\Enums\ShippingMethodType;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PackageKind;
use App\Enums\PackageSourceType;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Page;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCapability;
use App\Models\ProductImage;
use App\Models\ProductOption;
use App\Models\ProductOptionChoice;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class AgovenaSeedDemoCommand extends Command
{
    protected $signature = 'agovena:seed-demo
        {--force : Replace the local commerce/demo state managed by this command}
        {--skip-accounts : Skip demo account creation for catalog-only automated tests}';

    protected $description = 'Seed the official local Agovena demo dataset; refuses in production';

    /** @var list<string> */
    private const DEMO_MODULES = [
        'provisioning',
        'domains',
        'downloads',
        'digital-delivery',
        'events',
    ];

    /** @var list<string> */
    private const DEMO_EXTENSIONS = [
        'pterodactyl',
        'cloudflare-domain',
    ];

    /** @var list<string> */
    private const DEMO_CATEGORY_SLUGS = [
        'provisioning',
        'physical-products',
        'downloadable-products',
        'digital-products',
        'events',
        'game-hosting',
        'domain-services',
        'phones',
        'android',
        'audio',
    ];

    /** @var list<string> */
    private const DEMO_PRODUCT_SLUGS = [
        'minecraft-survival-server',
        'domain-registration-and-dns-management',
        'agovena-essential-tee',
        'python-automation-starter-kit',
        'agovena-pro-license',
        'agovena-launch-night',
        'nova-phone-14',
    ];

    /** @var list<string> */
    private const DEMO_USER_EMAILS = [
        'demo@agovena.com',
        'admin@agovena.com',
        'demo-seed-test@agovena.test',
    ];

    /** @var list<string> */
    private const DEMO_PAGE_SLUGS = [
        'about',
        'demo-guide',
        'demo-terms',
        'demo-privacy',
    ];

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to seed demo data in production.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->error('Demo reset is destructive in a local environment. Re-run with --force.');

            return self::FAILURE;
        }

        $passwords = null;
        $generatedPasswords = false;
        if (! $this->option('skip-accounts')) {
            $demoPassword = $this->demoPassword();
            if ($demoPassword !== null) {
                $passwords = new DemoAccountPasswords($demoPassword, $demoPassword);
            } elseif (! $this->input->isInteractive() || ! $this->hasPrivateTerminal()) {
                $this->error('Demo passwords require a private interactive terminal with normal output (not --quiet/--silent). Use a TTY, set AGOVENA_DEMO_PASSWORD locally, or pass --skip-accounts. No demo data was reset.');

                return self::FAILURE;
            } else {
                $passwords = DemoAccountPasswords::generate();
                $generatedPasswords = true;
            }
        }

        try {
            $installer = app(PackageInstaller::class);
            $this->ensureDemoModules($installer);
            $this->ensureDemoExtensions($installer);
            $this->resetLocalDemoState();

            DB::transaction(function () use ($passwords): void {
                $catalog = $this->seedCatalog();
                $customer = $this->seedAccounts($passwords);
                $orders = $this->seedOrders($customer, $catalog);
                $this->seedFulfilmentRecords($customer, $catalog, $orders);
                $this->seedPagesAndMenus();
            });
        } catch (Throwable $exception) {
            $this->error('Demo seed failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Official Agovena demo data seeded: catalog, capabilities, customer journeys, orders, invoices, pages and menus.');
        if ($generatedPasswords && $passwords !== null) {
            $this->warn('Save these one-time credentials in a password manager. Do not record this terminal session. Reseeding replaces both accounts and passwords.');
            $this->line('Demo customer (demo@agovena.com): '.$passwords->customer);
            $this->line('Demo admin (admin@agovena.com): '.$passwords->admin);
        }

        return self::SUCCESS;
    }

    private function hasPrivateTerminal(): bool
    {
        return $this->output->getOutput() instanceof ConsoleOutput
            && $this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL
            && defined('STDIN') && defined('STDOUT')
            && stream_isatty(STDIN) && stream_isatty(STDOUT);
    }

    private function demoPassword(): ?string
    {
        $value = getenv('AGOVENA_DEMO_PASSWORD');
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }

    private function ensureDemoModules(PackageInstaller $installer): void
    {
        $manager = app(ModuleManager::class);

        foreach (self::DEMO_MODULES as $moduleId) {
            if ($manager->manifest($moduleId) === null) {
                $this->installDemoPackage($installer, PackageKind::Module, $moduleId);
            }

            if ($manager->manifest($moduleId) === null) {
                throw new \RuntimeException(
                    'Required demo module is not available: '.$moduleId
                    .'. Configure AGOVENA_OPTIONAL_PACKAGES_PATH or AGOVENA_PACKAGES_MONOREPO_URL.',
                );
            }

            if (! $manager->isInstalled($moduleId)) {
                $manager->install($moduleId);
            }

            if (! $manager->isEnabled($moduleId)) {
                $manager->enable($moduleId);
            }
        }
    }

    private function ensureDemoExtensions(PackageInstaller $installer): void
    {
        $manager = app(ExtensionManager::class);
        $allowExperimental = app()->environment(['local', 'testing']);

        foreach (self::DEMO_EXTENSIONS as $extensionId) {
            $manifest = $manager->manifest($extensionId);
            if ($manifest === null && $allowExperimental) {
                $this->installDemoPackage($installer, PackageKind::Extension, $extensionId);
                $manifest = $manager->manifest($extensionId);
            }

            if ($manifest === null) {
                $this->warn('Skipping demo extension '.$extensionId.' because it is not installed in this environment.');

                continue;
            }

            if (! $manifest->productionReady && ! $allowExperimental) {
                $this->warn('Skipping non-production-ready demo extension '.$extensionId.' outside local/testing.');

                continue;
            }

            if (! $manager->isInstalled($extensionId)) {
                $manager->install($extensionId);
            }

            if (! $manager->isEnabled($extensionId)) {
                $manager->enable($extensionId);
            }
        }
    }

    private function installDemoPackage(PackageInstaller $installer, PackageKind $kind, string $packageId): void
    {
        $ref = (string) config('agovena.packages.monorepo.default_ref', 'main');
        if ($ref === '' || $ref === '*') {
            $ref = 'main';
        }

        try {
            $installer->install(new PackageSource(
                kind: $kind,
                sourceType: PackageSourceType::Monorepo,
                locator: '',
                constraint: $ref,
                composerName: $packageId,
            ), expectedAgovenaId: $packageId);
        } catch (Throwable $exception) {
            $detail = trim($exception->getMessage());
            throw new \RuntimeException(
                'Required demo '.strtolower($kind->value).' '.$packageId
                .' is not available and could not be installed from the configured optional-packages monorepo.'
                .($detail !== '' ? ' Detail: '.$detail : ''),
                previous: $exception,
            );
        }
    }

    private function resetLocalDemoState(): void
    {
        $productIds = DB::table('products')
            ->whereIn('slug', self::DEMO_PRODUCT_SLUGS)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $categoryIds = DB::table('categories')
            ->whereIn('slug', self::DEMO_CATEGORY_SLUGS)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $userIds = DB::table('users')
            ->whereIn('email', self::DEMO_USER_EMAILS)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $customerIds = DB::table('customers')
            ->where(function ($query) use ($userIds): void {
                $query->whereIn('email', self::DEMO_USER_EMAILS);
                if ($userIds !== []) {
                    $query->orWhereIn('user_id', $userIds);
                }
            })
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $orderIds = DB::table('orders')
            ->where(function ($query) use ($customerIds): void {
                $query->where('number', 'like', 'DEMO-%');
                if ($customerIds !== []) {
                    $query->orWhereIn('customer_id', $customerIds);
                }
            })
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        if ($productIds !== []) {
            $orderIds = array_values(array_unique(array_merge(
                $orderIds,
                DB::table('order_items')->whereIn('product_id', $productIds)->pluck('order_id')->all(),
            )));
        }
        $orderItemIds = $orderIds === []
            ? []
            : DB::table('order_items')->whereIn('order_id', $orderIds)->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        $deleteByIds = static function (string $table, string $column, array $ids): void {
            if ($ids !== [] && Schema::hasTable($table)) {
                DB::table($table)->whereIn($column, $ids)->delete();
            }
        };

        Schema::withoutForeignKeyConstraints(function () use ($deleteByIds, $productIds, $categoryIds, $userIds, $customerIds, $orderIds, $orderItemIds): void {
            $serviceIds = [];
            if (Schema::hasTable('service_instances') && ($orderIds !== [] || $productIds !== [])) {
                $serviceIds = DB::table('service_instances')->where(function ($query) use ($orderIds, $productIds): void {
                    if ($orderIds !== []) {
                        $query->whereIn('order_id', $orderIds);
                    }
                    if ($productIds !== []) {
                        $query->orWhereIn('product_id', $productIds);
                    }
                })->pluck('id')->map(static fn ($id): int => (int) $id)->all();
            }
            $shipmentIds = Schema::hasTable('shipments') && $orderIds !== []
                ? DB::table('shipments')->whereIn('order_id', $orderIds)->pluck('id')->map(static fn ($id): int => (int) $id)->all()
                : [];
            $invoiceIds = Schema::hasTable('invoices') && $orderIds !== []
                ? DB::table('invoices')->whereIn('order_id', $orderIds)->pluck('id')->map(static fn ($id): int => (int) $id)->all()
                : [];
            $creditNoteIds = Schema::hasTable('credit_notes') && $orderIds !== []
                ? DB::table('credit_notes')->whereIn('order_id', $orderIds)->pluck('id')->map(static fn ($id): int => (int) $id)->all()
                : [];
            $ticketIds = Schema::hasTable('tickets') && $orderIds !== []
                ? DB::table('tickets')->whereIn('order_id', $orderIds)->pluck('id')->map(static fn ($id): int => (int) $id)->all()
                : [];
            $returnRequestIds = Schema::hasTable('return_requests') && $orderIds !== []
                ? DB::table('return_requests')->whereIn('order_id', $orderIds)->pluck('id')->map(static fn ($id): int => (int) $id)->all()
                : [];
            $eventIds = Schema::hasTable('events')
                ? DB::table('events')->where('slug', 'agovena-launch-night')->pluck('id')->map(static fn ($id): int => (int) $id)->all()
                : [];

            $deleteByIds('event_tickets', 'order_id', $orderIds);
            $deleteByIds('event_tickets', 'event_id', $eventIds);
            $deleteByIds('event_ticket_types', 'event_id', $eventIds);
            $deleteByIds('event_performances', 'event_id', $eventIds);
            $deleteByIds('events', 'id', $eventIds);

            $deleteByIds('digital_secret_deliveries', 'order_id', $orderIds);
            $deleteByIds('digital_secret_items', 'product_id', $productIds);
            $deleteByIds('digital_entitlements', 'order_id', $orderIds);
            $deleteByIds('digital_assets', 'product_id', $productIds);
            $deleteByIds('domain_registrations', 'order_id', $orderIds);
            $deleteByIds('postnl_shipments', 'order_id', $orderIds);
            $deleteByIds('service_instance_runtime_secrets', 'service_instance_id', $serviceIds);
            $deleteByIds('provisioning_capacity_reservations', 'order_id', $orderIds);
            $deleteByIds('provisioning_capacity_locks', 'service_instance_id', $serviceIds);
            $deleteByIds('service_instances', 'id', $serviceIds);
            if (Schema::hasTable('provisioning_servers')) {
                DB::table('provisioning_servers')->where('name', 'Demo Pterodactyl Panel')->delete();
            }

            $deleteByIds('shipment_items', 'shipment_id', $shipmentIds);
            $deleteByIds('shipments', 'id', $shipmentIds);
            if (Schema::hasTable('shipping_methods')) {
                DB::table('shipping_methods')->where('code', 'demo-parcel')->delete();
            }
            if (Schema::hasTable('shipping_zones')) {
                DB::table('shipping_zones')->where('name', 'Demo Belgium')->delete();
            }
            $deleteByIds('return_request_items', 'return_request_id', $returnRequestIds);
            $deleteByIds('return_requests', 'id', $returnRequestIds);
            $deleteByIds('invoice_items', 'invoice_id', $invoiceIds);
            $deleteByIds('invoices', 'id', $invoiceIds);
            $deleteByIds('credit_note_items', 'credit_note_id', $creditNoteIds);
            $deleteByIds('credit_notes', 'id', $creditNoteIds);
            $deleteByIds('refunds', 'order_id', $orderIds);
            $deleteByIds('payment_webhook_events', 'order_id', $orderIds);
            $deleteByIds('payment_attempts', 'order_id', $orderIds);
            $deleteByIds('payments', 'order_id', $orderIds);
            $deleteByIds('discount_redemptions', 'order_id', $orderIds);
            $deleteByIds('product_plan_change_requests', 'order_id', $orderIds);
            $deleteByIds('product_plan_changes', 'order_id', $orderIds);
            $deleteByIds('subscription_renewals', 'order_id', $orderIds);
            $deleteByIds('subscriptions', 'order_id', $orderIds);
            $deleteByIds('ticket_messages', 'ticket_id', $ticketIds);
            $deleteByIds('tickets', 'id', $ticketIds);
            $deleteByIds('inventory_reservations', 'order_id', $orderIds);
            $deleteByIds('referral_attributions', 'order_id', $orderIds);
            $deleteByIds('order_item_runtime_secrets', 'order_item_id', $orderItemIds);
            $deleteByIds('order_address_snapshots', 'order_id', $orderIds);
            $deleteByIds('order_items', 'id', $orderItemIds);
            $deleteByIds('orders', 'id', $orderIds);

            $deleteByIds('product_option_choices', 'product_id', $productIds);
            $deleteByIds('product_options', 'product_id', $productIds);
            $deleteByIds('product_capabilities', 'product_id', $productIds);
            $deleteByIds('product_images', 'product_id', $productIds);
            $deleteByIds('product_currency_prices', 'product_id', $productIds);
            $deleteByIds('inventory_stocks', 'product_id', $productIds);
            $deleteByIds('products', 'id', $productIds);
            if (Schema::hasTable('categories') && $categoryIds !== []) {
                DB::table('categories')->whereIn('id', $categoryIds)->whereNotExists(function ($query): void {
                    $query->select(DB::raw(1))->from('products')->whereColumn('products.category_id', 'categories.id');
                })->delete();
            }

            $deleteByIds('customer_addresses', 'customer_id', $customerIds);
            $deleteByIds('customer_property_values', 'customer_id', $customerIds);
            $deleteByIds('customer_credit_entries', 'customer_id', $customerIds);
            $deleteByIds('customer_credit_accounts', 'customer_id', $customerIds);
            $deleteByIds('consent_events', 'user_id', $userIds);
            $deleteByIds('oauth_identities', 'user_id', $userIds);
            $deleteByIds('security_user_suspensions', 'user_id', $userIds);
            $deleteByIds('security_ip_rules', 'created_by', $userIds);
            $deleteByIds('notification_preferences', 'user_id', $userIds);
            $deleteByIds('push_subscriptions', 'user_id', $userIds);
            $deleteByIds('user_notifications', 'user_id', $userIds);
            $deleteByIds('notifications', 'notifiable_id', $userIds);
            if ($userIds !== []) {
                if (Schema::hasTable('model_has_permissions')) {
                    DB::table('model_has_permissions')->where('model_type', User::class)->whereIn('model_id', $userIds)->delete();
                }
                if (Schema::hasTable('model_has_roles')) {
                    DB::table('model_has_roles')->where('model_type', User::class)->whereIn('model_id', $userIds)->delete();
                }
                $deleteByIds('personal_access_tokens', 'tokenable_id', $userIds);
            }
            $deleteByIds('customers', 'id', $customerIds);
            $deleteByIds('users', 'id', $userIds);

            $demoPageIds = Schema::hasTable('pages')
                ? DB::table('pages')->whereIn('slug', self::DEMO_PAGE_SLUGS)->where(function ($query): void {
                    $query->where('body', 'like', '%official local demo dataset%')
                        ->orWhere('body', 'like', '%synthetic demo content%')
                        ->orWhere('body', 'like', '%synthetic customer and order records%');
                })->pluck('id')->map(static fn ($id): int => (int) $id)->all()
                : [];
            $deleteByIds('menu_items', 'page_id', $demoPageIds);
            $deleteByIds('pages', 'id', $demoPageIds);
            if (Schema::hasTable('menu_items') && Schema::hasTable('menus')) {
                $demoMenuIds = DB::table('menu_items')->whereIn('label', ['Demo Guide', 'Demo Terms', 'Demo Privacy'])->orWhere('url', '/categories/provisioning')->orWhere('url', '/categories/events')->pluck('menu_id')->map(static fn ($id): int => (int) $id)->all();
                $deleteByIds('menu_items', 'menu_id', $demoMenuIds);
                $deleteByIds('menus', 'id', $demoMenuIds);
            }
        });

        // Public demo images are immutable. Deleting an in-flight image can leave a pending-deletion file on Windows.
        Storage::disk('local')->delete('demo/downloads/agovena_automation_starter.py');
    }

    /** @return array{categories: array<string, Category>, products: array<string, Product>} */
    private function seedCatalog(): array
    {
        $categories = [];
        $categoryDefinitions = [
            ['key' => 'provisioning', 'name' => 'Provisioning', 'slug' => 'provisioning', 'description' => 'Provisioned services that connect a purchase to an external service lifecycle.', 'asset' => 'minecraft-survival-server'],
            ['key' => 'physical', 'name' => 'Physical Products', 'slug' => 'physical-products', 'description' => 'Agovena merchandise prepared for physical fulfilment and delivery tracking.', 'asset' => 'agovena-essential-tee'],
            ['key' => 'downloads', 'name' => 'Downloadable Products', 'slug' => 'downloadable-products', 'description' => 'Files delivered through a controlled download entitlement.', 'asset' => 'python-automation-starter-kit'],
            ['key' => 'digital', 'name' => 'Digital Products', 'slug' => 'digital-products', 'description' => 'Digital value such as licenses and other fulfilment secrets.', 'asset' => 'agovena-pro-license'],
            ['key' => 'events', 'name' => 'Events', 'slug' => 'events', 'description' => 'Ticketed experiences with performances, ticket types and customer tickets.', 'asset' => 'agovena-launch-night'],
        ];

        foreach ($categoryDefinitions as $definition) {
            $categories[$definition['key']] = Category::query()->create([
                'name' => $definition['name'],
                'slug' => $definition['slug'],
                'description' => $definition['description'],
                'image_path' => $this->storeAsset($definition['asset']),
                'is_active' => true,
            ]);
        }

        $categories['game-hosting'] = Category::query()->create([
            'parent_id' => $categories['provisioning']->id,
            'name' => 'Game Hosting',
            'slug' => 'game-hosting',
            'description' => 'Game server plans that can be provisioned through a compatible provider.',
            'image_path' => $this->storeAsset('minecraft-survival-server'),
            'is_active' => true,
        ]);
        $categories['domain-services'] = Category::query()->create([
            'parent_id' => $categories['provisioning']->id,
            'name' => 'Domain Services',
            'slug' => 'domain-services',
            'description' => 'Domain registration and DNS zone management services.',
            'image_path' => $this->storeAsset('domain-registration-and-dns-management'),
            'is_active' => true,
        ]);

        $definitions = [
            [
                'key' => 'minecraft',
                'name' => 'Minecraft Survival Server',
                'slug' => 'minecraft-survival-server',
                'sku' => 'AGV-MC-SURVIVAL',
                'subtitle' => 'A ready-to-configure Minecraft service plan for the Pterodactyl demo flow.',
                'description' => 'A synthetic Minecraft hosting plan that demonstrates product options, provider mapping, service provisioning and the customer Go to server action. No real panel or server is contacted by the demo. NOT AN OFFICIAL MINECRAFT SERVICE. NOT APPROVED BY OR ASSOCIATED WITH MOJANG OR MICROSOFT.',
                'price' => 1499,
                'category' => 'game-hosting',
                'asset' => 'minecraft-survival-server',
                'specifications' => [
                    ['label' => 'Service type', 'value' => 'Provisioned game hosting'],
                    ['label' => 'Provider mapping', 'value' => 'Pterodactyl demo provider'],
                    ['label' => 'Memory', 'value' => '8 GB'],
                    ['label' => 'Runtime', 'value' => 'Java Minecraft'],
                ],
                'capabilities' => [
                    ['key' => 'provisionable', 'config' => [
                        'provider_key' => 'pterodactyl',
                        'plan_key' => 'minecraft-survival-8gb',
                        'server_template_key' => 'minecraft-java',
                        'panel_url' => 'https://panel.demo.invalid',
                        'external_ref' => 'demo-minecraft-server-001',
                        'mode' => 'demo',
                        'calls_enabled' => false,
                    ]],
                ],
                'options' => [
                    ['key' => 'memory', 'label' => 'Memory', 'type' => 'select', 'required' => true, 'choices' => [['value' => '4gb', 'label' => '4 GB', 'price' => -300], ['value' => '8gb', 'label' => '8 GB', 'price' => 0], ['value' => '16gb', 'label' => '16 GB', 'price' => 700]]],
                    ['key' => 'region', 'label' => 'Server region', 'type' => 'radio', 'required' => true, 'choices' => [['value' => 'eu-central', 'label' => 'EU Central', 'price' => 0], ['value' => 'us-east', 'label' => 'US East', 'price' => 0]]],
                ],
            ],
            [
                'key' => 'domain',
                'name' => 'Domain Registration and DNS Management',
                'slug' => 'domain-registration-and-dns-management',
                'sku' => 'AGV-DOMAIN-DNS',
                'subtitle' => 'A safe domain workflow with registration and DNS zone management states.',
                'description' => 'A synthetic domain service using the reserved demo domain demo.agovena.test. The record demonstrates registrar and DNS provider mapping without contacting Cloudflare or registering a real domain.',
                'price' => 1299,
                'category' => 'domain-services',
                'asset' => 'domain-registration-and-dns-management',
                'specifications' => [
                    ['label' => 'Registration', 'value' => 'Domain registration demo'],
                    ['label' => 'DNS', 'value' => 'DNS zone management demo'],
                    ['label' => 'Registrar mapping', 'value' => 'Cloudflare Domains demo'],
                    ['label' => 'Domain', 'value' => 'demo.agovena.test'],
                ],
                'capabilities' => [
                    ['key' => 'domain_registration', 'config' => [
                        'provider_key' => 'demo-registrar',
                        'registrar_key' => 'demo-registrar',
                        'dns_provider_key' => 'demo-dns',
                        'domain_name' => 'demo.agovena.test',
                        'allowed_tlds' => ['test', 'invalid'],
                        'auto_renew' => true,
                        'mode' => 'demo',
                        'calls_enabled' => false,
                    ]],
                ],
                'options' => [
                    ['key' => 'domain_name', 'label' => 'Domain name', 'type' => 'text', 'required' => true, 'constraints' => ['minlength' => 4]],
                ],
            ],
            [
                'key' => 'physical',
                'name' => 'Agovena Merch',
                'slug' => 'agovena-essential-tee',
                'sku' => 'AGV-MERCH-TEE',
                'subtitle' => 'Official Agovena merchandise for the physical fulfilment demo.',
                'description' => 'A synthetic Agovena merchandise product that demonstrates product options, payment, invoice, shipment creation and a dummy carrier tracking link.',
                'price' => 2499,
                'category' => 'physical',
                'asset' => 'agovena-essential-tee',
                'specifications' => [
                    ['label' => 'Product type', 'value' => 'Physical merchandise'],
                    ['label' => 'Material', 'value' => 'Organic cotton'],
                    ['label' => 'Fulfilment', 'value' => 'Demo shipment with tracking'],
                ],
                'capabilities' => [],
                'options' => [
                    ['key' => 'size', 'label' => 'Size', 'type' => 'select', 'required' => true, 'choices' => [['value' => 's', 'label' => 'Small', 'price' => 0], ['value' => 'm', 'label' => 'Medium', 'price' => 0], ['value' => 'l', 'label' => 'Large', 'price' => 0]]],
                    ['key' => 'color', 'label' => 'Color', 'type' => 'radio', 'required' => true, 'choices' => [['value' => 'ink', 'label' => 'Ink black', 'price' => 0], ['value' => 'cloud', 'label' => 'Cloud white', 'price' => 0]]],
                ],
            ],
            [
                'key' => 'download',
                'name' => 'Python Automation Starter Kit',
                'slug' => 'python-automation-starter-kit',
                'sku' => 'AGV-DL-PYTHON',
                'subtitle' => 'A practical Python script delivered as a controlled download entitlement.',
                'description' => 'A synthetic downloadable product containing a small, safe Python automation script. The demo shows a paid order, entitlement, download limit and customer download page.',
                'price' => 899,
                'category' => 'downloads',
                'asset' => 'python-automation-starter-kit',
                'specifications' => [
                    ['label' => 'Product type', 'value' => 'Downloadable file'],
                    ['label' => 'File', 'value' => 'agovena_automation_starter.py'],
                    ['label' => 'Delivery', 'value' => 'Customer download entitlement'],
                ],
                'capabilities' => [
                    ['key' => 'digital', 'config' => ['delivery' => 'download', 'mode' => 'demo']],
                ],
                'options' => [
                    ['key' => 'edition', 'label' => 'Edition', 'type' => 'radio', 'required' => true, 'choices' => [['value' => 'standard', 'label' => 'Standard script', 'price' => 0], ['value' => 'annotated', 'label' => 'Annotated script', 'price' => 300]]],
                ],
            ],
            [
                'key' => 'license',
                'name' => 'Agovena Pro License',
                'slug' => 'agovena-pro-license',
                'sku' => 'AGV-LIC-PRO',
                'subtitle' => 'A synthetic license delivered through the digital delivery module.',
                'description' => 'A clearly marked demo license product that demonstrates encrypted digital delivery, masked list output and customer license visibility. It is not valid for any real software.',
                'price' => 4999,
                'category' => 'digital',
                'asset' => 'agovena-pro-license',
                'specifications' => [
                    ['label' => 'Product type', 'value' => 'Digital license'],
                    ['label' => 'Delivery', 'value' => 'Encrypted demo secret delivery'],
                    ['label' => 'Validity', 'value' => 'Demo only, not a real license'],
                ],
                'capabilities' => [
                    ['key' => 'digital_secret', 'config' => ['secret_type' => 'license', 'fulfillment_mode' => 'pool', 'mode' => 'demo']],
                ],
                'options' => [
                    ['key' => 'seats', 'label' => 'Seats', 'type' => 'select', 'required' => true, 'choices' => [['value' => '1', 'label' => '1 seat', 'price' => 0], ['value' => '5', 'label' => '5 seats', 'price' => 2000]]],
                    ['key' => 'support', 'label' => 'Include priority support', 'type' => 'toggle', 'required' => false, 'price' => 900],
                ],
            ],
            [
                'key' => 'event',
                'name' => 'Concert Demo',
                'slug' => 'agovena-launch-night',
                'sku' => 'AGV-EVENT-DEMO',
                'subtitle' => 'A fictional concert example for the events and ticketing demo.',
                'description' => 'A synthetic concert ticket that demonstrates event dates, performance capacity, ticket types, order fulfilment and customer ticket download. This is not a real event.',
                'price' => 2999,
                'category' => 'events',
                'asset' => 'agovena-launch-night',
                'specifications' => [
                    ['label' => 'Event type', 'value' => 'Demo concert'],
                    ['label' => 'Venue', 'value' => 'Demo Venue'],
                    ['label' => 'Ticket delivery', 'value' => 'Customer account ticket'],
                ],
                'capabilities' => [
                    ['key' => 'event_ticket', 'config' => ['event_slug' => 'agovena-launch-night', 'mode' => 'demo']],
                ],
                'options' => [
                    ['key' => 'ticket_tier', 'label' => 'Ticket tier', 'type' => 'radio', 'required' => true, 'choices' => [['value' => 'standard', 'label' => 'Standard entry', 'price' => 0], ['value' => 'front-row', 'label' => 'Front row', 'price' => 1200]]],
                ],
            ],
        ];

        $products = [];
        foreach ($definitions as $definition) {
            $product = Product::query()->create([
                'name' => $definition['name'],
                'subtitle' => $definition['subtitle'],
                'slug' => $definition['slug'],
                'sku' => $definition['sku'],
                'description' => $definition['description'],
                'specifications' => $definition['specifications'],
                'show_details' => true,
                'show_specifications' => true,
                'status' => ProductStatus::Active,
                'price_amount' => $definition['price'],
                'currency' => 'EUR',
                'image_path' => $this->storeAsset($definition['asset']),
                'category_id' => $categories[$definition['category']]->id,
            ]);
            $products[$definition['key']] = $product;

            ProductImage::query()->create([
                'product_id' => $product->id,
                'path' => $product->image_path,
                'sort' => 0,
            ]);

            foreach ($definition['capabilities'] as $capability) {
                ProductCapability::query()->create([
                    'product_id' => $product->id,
                    'capability' => $capability['key'],
                    'config' => $capability['config'],
                ]);
            }

            foreach ($definition['options'] as $sort => $option) {
                $this->createProductOption($product, $option, $sort);
            }
        }

        return ['categories' => $categories, 'products' => $products];
    }

    /** @param array<string, mixed> $definition */
    private function createProductOption(Product $product, array $definition, int $sort): void
    {
        $option = ProductOption::query()->create([
            'product_id' => $product->id,
            'key' => $definition['key'],
            'label' => $definition['label'],
            'type' => $definition['type'],
            'is_required' => (bool) ($definition['required'] ?? false),
            'is_active' => true,
            'sort' => $sort,
            'price_adjustment_amount' => (int) ($definition['price'] ?? 0),
            'constraints' => $definition['constraints'] ?? null,
        ]);

        foreach ($definition['choices'] ?? [] as $choiceSort => $choice) {
            ProductOptionChoice::query()->create([
                'product_option_id' => $option->id,
                'value' => $choice['value'],
                'label' => $choice['label'],
                'price_adjustment_amount' => (int) ($choice['price'] ?? 0),
                'sort' => $choiceSort,
                'is_active' => true,
            ]);
        }
    }

    private function seedAccounts(?DemoAccountPasswords $passwords): Customer
    {
        if ($passwords === null) {
            $testUser = new User;
            $testUser->forceFill([
                'name' => 'Demo Seed Test Customer',
                'email' => 'demo-seed-test@agovena.test',
                'password' => Str::random(64),
                'email_verified_at' => now(),
            ])->save();

            return $testUser->ensureCustomer();
        }

        $demoUser = new User;
        $demoUser->forceFill([
            'name' => 'Demo Customer',
            'email' => 'demo@agovena.com',
            'password' => $passwords->customer,
            'email_verified_at' => now(),
        ])->save();
        $demoUser->ensureCustomer();

        $adminUser = new User;
        $adminUser->forceFill([
            'name' => 'Demo Admin',
            'email' => 'admin@agovena.com',
            'password' => $passwords->admin,
            'email_verified_at' => now(),
        ])->save();
        $adminUser->ensureCustomer();
        $adminUser->syncPermissions(Permission::query()->where('guard_name', User::GUARD)->get());

        return $demoUser->customer()->firstOrFail();
    }

    /** @param array{categories: array<string, Category>, products: array<string, Product>} $catalog @return array<string, array{order: Order, item: OrderItem}> */
    private function seedOrders(Customer $customer, array $catalog): array
    {
        $snapshots = [
            'minecraft' => [
                ['key' => 'memory', 'label' => 'Memory', 'value' => '8gb', 'display' => '8 GB'],
                ['key' => 'region', 'label' => 'Server region', 'value' => 'eu-central', 'display' => 'EU Central'],
            ],
            'domain' => [
                ['key' => 'domain_name', 'label' => 'Domain name', 'value' => 'demo.agovena.test', 'display' => 'demo.agovena.test'],
                ['key' => 'dns_management', 'label' => 'Include DNS zone management', 'value' => true, 'display' => 'Yes'],
            ],
            'physical' => [
                ['key' => 'size', 'label' => 'Size', 'value' => 'm', 'display' => 'Medium'],
                ['key' => 'color', 'label' => 'Color', 'value' => 'ink', 'display' => 'Ink black'],
            ],
            'download' => [
                ['key' => 'edition', 'label' => 'Edition', 'value' => 'annotated', 'display' => 'Annotated script'],
            ],
            'license' => [
                ['key' => 'seats', 'label' => 'Seats', 'value' => '5', 'display' => '5 seats'],
                ['key' => 'support', 'label' => 'Include priority support', 'value' => true, 'display' => 'Yes'],
            ],
            'event' => [
                ['key' => 'ticket_tier', 'label' => 'Ticket tier', 'value' => 'front-row', 'display' => 'Front row'],
            ],
        ];

        $orders = [];
        $orderNumber = 1001;
        foreach ($snapshots as $key => $options) {
            $orders[$key] = $this->createOrder(
                $customer,
                $catalog['products'][$key],
                $options,
                'DEMO-'.$orderNumber++,
                OrderStatus::Paid->value,
                PaymentStatus::Paid->value,
                InvoiceStatus::Paid->value,
            );
        }

        $orders['failed'] = $this->createOrder(
            $customer,
            $catalog['products']['minecraft'],
            $snapshots['minecraft'],
            'DEMO-FAILED-1007',
            OrderStatus::Cancelled->value,
            PaymentStatus::Failed->value,
            InvoiceStatus::Issued->value,
        );
        $orders['unpaid'] = $this->createOrder(
            $customer,
            $catalog['products']['domain'],
            $snapshots['domain'],
            'DEMO-UNPAID-1008',
            OrderStatus::Pending->value,
            PaymentStatus::Pending->value,
            InvoiceStatus::Issued->value,
        );

        return $orders;
    }

    /** @param list<array<string, mixed>> $options @return array{order: Order, item: OrderItem} */
    private function createOrder(
        Customer $customer,
        Product $product,
        array $options,
        string $number,
        string $orderStatus,
        string $paymentStatus,
        string $invoiceStatus,
    ): array {
        $shippingAmount = $product->slug === 'agovena-essential-tee' ? 499 : 0;
        $total = $product->price_amount + $shippingAmount;
        $now = now();

        $order = Order::query()->create([
            'number' => $number,
            'status' => $orderStatus,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_id' => $customer->id,
            'billing_name' => $customer->name,
            'billing_line1' => 'Demo Street 1',
            'billing_city' => 'Brussels',
            'billing_postal_code' => '1000',
            'billing_country' => 'BE',
            'billing_phone' => '+32 2 000 00 00',
            'shipping_name' => $customer->name,
            'shipping_line1' => 'Demo Street 1',
            'shipping_city' => 'Brussels',
            'shipping_postal_code' => '1000',
            'shipping_country' => 'BE',
            'shipping_phone' => '+32 2 000 00 00',
            'shipping_same_as_billing' => true,
            'subtotal_amount' => $product->price_amount,
            'shipping_amount' => $shippingAmount,
            'shipping_method_label' => $shippingAmount > 0 ? 'Demo parcel delivery' : null,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'payment_fee_amount' => 0,
            'credit_amount' => 0,
            'total_amount' => $total,
            'currency' => 'EUR',
            'due_at' => $invoiceStatus === InvoiceStatus::Issued->value ? $now->copy()->addDays(14) : null,
            'custom_properties_snapshot' => ['demo' => true],
        ]);

        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'label' => $product->name,
            'quantity' => 1,
            'unit_amount' => $product->price_amount,
            'line_total_amount' => $product->price_amount,
            'currency' => 'EUR',
            'options_snapshot' => $options,
        ]);

        Payment::query()->create([
            'order_id' => $order->id,
            'amount' => $total,
            'currency' => 'EUR',
            'method' => 'manual',
            'status' => $paymentStatus,
            'paid_at' => $paymentStatus === PaymentStatus::Paid->value ? $now : null,
            'reference' => 'DEMO-'.$paymentStatus.'-'.$number,
        ]);

        $invoice = Invoice::query()->create([
            'number' => 'INV-'.$number,
            'status' => $invoiceStatus,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'billing_name' => $customer->name,
            'billing_line1' => 'Demo Street 1',
            'billing_city' => 'Brussels',
            'billing_postal_code' => '1000',
            'billing_country' => 'BE',
            'merchant_name' => 'Agovena Demo Store',
            'merchant_address' => 'Demo environment only',
            'issued_at' => $now->toDateString(),
            'due_at' => $invoiceStatus === InvoiceStatus::Issued->value ? $now->copy()->addDays(14)->toDateString() : null,
            'subtotal_amount' => $product->price_amount,
            'tax_amount' => 0,
            'total_amount' => $total,
            'currency' => 'EUR',
            'paid_at' => $invoiceStatus === InvoiceStatus::Paid->value ? $now : null,
        ]);

        DB::table('invoice_items')->insert([
            'invoice_id' => $invoice->id,
            'label' => $product->name,
            'quantity' => 1,
            'unit_amount' => $product->price_amount,
            'line_total_amount' => $product->price_amount,
            'currency' => 'EUR',
            'kind' => 'product',
            'options_snapshot' => json_encode($options, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['order' => $order, 'item' => $item];
    }

    /** @param array{categories: array<string, Category>, products: array<string, Product>} $catalog @param array<string, array{order: Order, item: OrderItem}> $orders */
    private function seedFulfilmentRecords(Customer $customer, array $catalog, array $orders): void
    {
        $this->seedPhysicalShipment($orders['physical']);
        $this->seedProvisioningService($customer, $catalog['products']['minecraft'], $orders['minecraft']);
        $this->seedDomainRegistration($customer, $catalog['products']['domain'], $orders['domain']);
        $this->seedDownloadEntitlement($customer, $catalog['products']['download'], $orders['download']);
        $this->seedLicenseDelivery($customer, $catalog['products']['license'], $orders['license']);
        $this->seedEventTicket($customer, $catalog['products']['event'], $orders['event']);
    }

    /** @param array{order: Order, item: OrderItem} $orderData */
    private function seedPhysicalShipment(array $orderData): void
    {
        if (! Schema::hasTable('shipments')) {
            return;
        }

        $now = now();
        $zoneId = DB::table('shipping_zones')->insertGetId([
            'name' => 'Demo Belgium',
            'countries' => json_encode(['BE'], JSON_THROW_ON_ERROR),
            'is_active' => true,
            'sort' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $methodId = DB::table('shipping_methods')->insertGetId([
            'name' => 'Demo parcel delivery',
            'code' => 'demo-parcel',
            'type' => ShippingMethodType::Zone->value,
            'zone_id' => $zoneId,
            'config' => json_encode(['amount' => 499], JSON_THROW_ON_ERROR),
            'currency' => 'EUR',
            'is_active' => true,
            'sort' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $shipmentId = DB::table('shipments')->insertGetId([
            'order_id' => $orderData['order']->id,
            'status' => 'delivered',
            'shipping_method_id' => $methodId,
            'shipping_method_label' => 'Demo parcel delivery',
            'shipping_amount' => 499,
            'currency' => 'EUR',
            'carrier_name' => 'Demo Parcel Carrier',
            'tracking_number' => 'DEMO-TRACK-0001',
            'tracking_url' => 'https://tracking.demo.invalid/DEMO-TRACK-0001',
            'shipped_at' => $now->copy()->subDays(3),
            'delivered_at' => $now->copy()->subDay(),
            'notes' => 'Synthetic tracking data. No carrier request was made.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('shipment_items')->insert([
            'shipment_id' => $shipmentId,
            'order_item_id' => $orderData['item']->id,
            'quantity' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array{order: Order, item: OrderItem} $orderData */
    private function seedProvisioningService(Customer $customer, Product $product, array $orderData): void
    {
        if (! Schema::hasTable('service_instances')) {
            return;
        }

        $now = now();
        $serverId = DB::table('provisioning_servers')->insertGetId([
            'name' => 'Demo Pterodactyl Panel',
            'provider_key' => 'pterodactyl',
            'settings' => json_encode([
                'panel_url' => 'https://panel.demo.invalid',
                'mode' => 'demo',
                'calls_enabled' => false,
                'note' => 'Placeholder server only. No API credential is configured.',
            ], JSON_THROW_ON_ERROR),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        ServiceInstance::query()->create([
            'number' => 'SVC-DEMO-MC-001',
            'order_id' => $orderData['order']->id,
            'order_item_id' => $orderData['item']->id,
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'customer_email' => $customer->email,
            'customer_name' => $customer->name,
            'status' => ServiceInstanceStatus::Active,
            'provider_key' => 'pterodactyl',
            'provisioning_server_id' => $serverId,
            'external_ref' => 'demo-minecraft-server-001',
            'meta' => [
                'mode' => 'demo',
                'panel_url' => 'https://panel.demo.invalid',
                'go_to_server_url' => 'https://panel.demo.invalid/server/demo-minecraft-server-001',
                'server_name' => 'Agovena Demo Survival',
                'calls_enabled' => false,
            ],
            'server_settings_snapshot' => [
                'panel_url' => 'https://panel.demo.invalid',
                'node' => 'demo-node-1',
            ],
            'provider_settings_snapshot' => [
                'provider_key' => 'pterodactyl',
                'mode' => 'demo',
                'calls_enabled' => false,
            ],
            'provisioning_at' => $now->copy()->subDays(2),
            'activated_at' => $now->copy()->subDay(),
        ]);
    }

    /** @param array{order: Order, item: OrderItem} $orderData */
    private function seedDomainRegistration(Customer $customer, Product $product, array $orderData): void
    {
        if (! Schema::hasTable('domain_registrations')) {
            return;
        }

        $now = now();
        DomainRegistration::query()->create([
            'number' => 'DOM-DEMO-001',
            'order_id' => $orderData['order']->id,
            'order_item_id' => $orderData['item']->id,
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'customer_email' => $customer->email,
            'customer_name' => $customer->name,
            'unit_index' => 1,
            'domain_name' => 'demo.agovena.test',
            'status' => DomainRegistrationStatus::Active,
            'provider_key' => 'demo-registrar',
            'registrar_key' => 'demo-registrar',
            'dns_provider_key' => 'demo-dns',
            'provider_reference' => 'demo-domain-reference-001',
            'auto_renew' => true,
            'meta' => [
                'mode' => 'demo',
                'calls_enabled' => false,
                'dns_zone' => [
                    'zone_reference' => 'demo-zone-001',
                    'nameservers' => ['ns1.demo.invalid', 'ns2.demo.invalid'],
                    'status' => 'ready',
                    'records' => [
                        ['type' => 'A', 'name' => '@', 'content' => '192.0.2.10', 'ttl' => 300],
                        ['type' => 'CNAME', 'name' => 'www', 'content' => 'demo.agovena.invalid', 'ttl' => 300],
                    ],
                ],
                'dns_records' => [
                    ['id' => 'demo-record-a', 'type' => 'A', 'name' => '@', 'content' => '192.0.2.10', 'ttl' => 300, 'proxied' => false],
                    ['id' => 'demo-record-cname', 'type' => 'CNAME', 'name' => 'www', 'content' => 'demo.agovena.invalid', 'ttl' => 300, 'proxied' => false],
                ],
            ],
            'registered_at' => $now->copy()->subDay(),
            'expires_at' => $now->copy()->addYear(),
        ]);
    }

    /** @param array{order: Order, item: OrderItem} $orderData */
    private function seedDownloadEntitlement(Customer $customer, Product $product, array $orderData): void
    {
        if (! Schema::hasTable('digital_assets')) {
            return;
        }

        Storage::disk('local')->put('demo/downloads/agovena_automation_starter.py', $this->demoPythonScript());
        $asset = DigitalAsset::query()->create([
            'product_id' => $product->id,
            'label' => 'Agovena Automation Starter Python script',
            'disk' => 'local',
            'path' => 'demo/downloads/agovena_automation_starter.py',
            'filename' => 'agovena_automation_starter.py',
            'download_limit' => 5,
            'is_active' => true,
        ]);

        DigitalEntitlement::query()->create([
            'order_id' => $orderData['order']->id,
            'order_item_id' => $orderData['item']->id,
            'product_id' => $product->id,
            'digital_asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'customer_email' => $customer->email,
            'token' => hash('sha256', 'agovena-demo-download-'.$orderData['order']->number),
            'download_limit' => 5,
            'download_count' => 1,
            'granted_at' => now()->subDay(),
        ]);
    }

    /** @param array{order: Order, item: OrderItem} $orderData */
    private function seedLicenseDelivery(Customer $customer, Product $product, array $orderData): void
    {
        if (! Schema::hasTable('digital_secret_items')) {
            return;
        }

        $demoLicense = 'DEMO-LICENSE-NOT-A-REAL-KEY-001';
        $item = new DigitalSecretItem;
        $item->forceFill([
            'product_id' => $product->id,
            'value_fingerprint' => DigitalSecretItem::fingerprint($demoLicense),
            'label' => 'Demo license for Agovena Pro License',
            'status' => DigitalSecretItem::STATUS_ALLOCATED,
            'allocated_at' => now()->subDay(),
        ]);
        $item->setPlainValue($demoLicense);
        $item->save();

        $delivery = new DigitalSecretDelivery;
        $delivery->forceFill([
            'order_id' => $orderData['order']->id,
            'order_item_id' => $orderData['item']->id,
            'product_id' => $product->id,
            'digital_secret_item_id' => $item->id,
            'customer_id' => $customer->id,
            'customer_email' => $customer->email,
            'source' => DigitalSecretDelivery::SOURCE_POOL,
            'status' => DigitalSecretDelivery::STATUS_DELIVERED,
            'granted_at' => now()->subDay(),
        ]);
        $delivery->setPlainValue($demoLicense);
        $delivery->save();
    }

    /** @param array{order: Order, item: OrderItem} $orderData */
    private function seedEventTicket(Customer $customer, Product $product, array $orderData): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        $now = now();
        $eventId = DB::table('events')->insertGetId([
            'name' => 'Concert Demo',
            'slug' => 'agovena-launch-night',
            'description' => 'A fictional concert example for the Agovena demo catalog. This is not a real event.',
            'status' => 'published',
            'venue' => 'Demo Venue',
            'starts_at' => $now->copy()->addMonth()->setTime(19, 30),
            'ends_at' => $now->copy()->addMonth()->setTime(23, 30),
            'sales_starts_at' => $now->copy()->subMonth(),
            'sales_ends_at' => $now->copy()->addWeeks(3),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $performanceId = DB::table('event_performances')->insertGetId([
            'event_id' => $eventId,
            'starts_at' => $now->copy()->addMonth()->setTime(20, 0),
            'ends_at' => $now->copy()->addMonth()->setTime(23, 0),
            'capacity' => 600,
            'venue' => 'The Foundry, Brussels',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $ticketType = EventTicketType::query()->create([
            'event_id' => $eventId,
            'performance_id' => $performanceId,
            'product_id' => $product->id,
            'name' => 'Front row',
            'capacity' => 120,
        ]);

        EventTicket::query()->create([
            'number' => 'TKT-DEMO-0001',
            'token' => hash('sha256', 'agovena-demo-ticket-'.$orderData['order']->number),
            'event_id' => $eventId,
            'performance_id' => $performanceId,
            'ticket_type_id' => $ticketType->id,
            'product_id' => $product->id,
            'order_id' => $orderData['order']->id,
            'order_item_id' => $orderData['item']->id,
            'customer_id' => $customer->id,
            'customer_email' => $customer->email,
            'customer_name' => $customer->name,
            'status' => 'issued',
        ]);
    }

    private function seedPagesAndMenus(): void
    {
        $about = Page::query()->firstOrCreate(['slug' => 'about'], [
            'title' => 'About Agovena',
            'body' => "Agovena is an open-source, self-hosted and modular commerce platform.\n\nThis storefront is the official local demo dataset. It demonstrates physical products, downloads, digital delivery, provisioning, domains and events without contacting real providers.",
            'status' => 'published',
        ]);
        $guide = Page::query()->firstOrCreate(['slug' => 'demo-guide'], [
            'title' => 'Demo Guide',
            'body' => "Use the demo customer account to inspect paid orders and customer services.\n\nThe dataset includes a synthetic Pterodactyl service, a reserved .test domain, a download entitlement, a demo license, a physical shipment and an event ticket. All external links use reserved demo domains and no external call is made.",
            'status' => 'published',
        ]);
        $terms = Page::query()->firstOrCreate(['slug' => 'demo-terms'], [
            'title' => 'Demo Terms',
            'body' => 'This is synthetic demo content. It is not a real offer, invoice, license, domain registration, shipment, event booking or provider connection.',
            'status' => 'published',
        ]);
        $privacy = Page::query()->firstOrCreate(['slug' => 'demo-privacy'], [
            'title' => 'Demo Privacy',
            'body' => 'This local dataset contains synthetic customer and order records created only by the explicit demo seed command.',
            'status' => 'published',
        ]);

        $header = Menu::query()->firstOrCreate(['handle' => 'header'], ['name' => 'Header']);
        $footer = Menu::query()->firstOrCreate(['handle' => 'footer'], ['name' => 'Footer']);
        $legal = Menu::query()->firstOrCreate(['handle' => 'footer_legal'], ['name' => 'Footer legal']);

        foreach ([
            ['label' => 'Services', 'url' => '/categories/provisioning'],
            ['label' => 'Domains', 'url' => '/domains'],
        ] as $legacyItem) {
            MenuItem::query()
                ->where('menu_id', $header->id)
                ->where('label', $legacyItem['label'])
                ->where('type', 'url')
                ->where('url', $legacyItem['url'])
                ->delete();
        }

        $legacyAbout = MenuItem::query()
            ->where('menu_id', $header->id)
            ->where('label', 'About')
            ->where('type', 'page')
            ->where('page_id', $about->id)
            ->first();
        if ($legacyAbout !== null && ! MenuItem::query()
            ->where('menu_id', $header->id)
            ->where('label', 'About Us')
            ->where('type', 'page')
            ->where('page_id', $about->id)
            ->exists()) {
            $legacyAbout->update([
                'label' => 'About Us',
                'sort' => 1,
            ]);
        }

        foreach ([
            ['label' => 'Products', 'type' => 'url', 'url' => '/#catalog', 'sort' => 0],
            ['label' => 'About Us', 'type' => 'page', 'page_id' => $about->id, 'sort' => 1],
        ] as $item) {
            MenuItem::query()->firstOrCreate(
                ['menu_id' => $header->id, 'label' => $item['label']],
                $item + ['menu_id' => $header->id],
            );
        }
        if ($footer->wasRecentlyCreated) {
            MenuItem::query()->create(['menu_id' => $footer->id, 'label' => 'Demo Guide', 'type' => 'page', 'page_id' => $guide->id, 'sort' => 0]);
        }
        if ($legal->wasRecentlyCreated) {
            MenuItem::query()->create(['menu_id' => $legal->id, 'label' => 'Demo Terms', 'type' => 'page', 'page_id' => $terms->id, 'sort' => 0]);
            MenuItem::query()->create(['menu_id' => $legal->id, 'label' => 'Demo Privacy', 'type' => 'page', 'page_id' => $privacy->id, 'sort' => 1]);
        }
    }

    private function storeAsset(string $slug): string
    {
        $source = resource_path('images/demo/'.$slug.'.jpg');
        if (! File::isFile($source)) {
            throw new \RuntimeException('Demo asset is missing: '.$source);
        }

        $hash = hash_file('sha256', $source);
        if (! is_string($hash)) {
            throw new \RuntimeException('Demo asset could not be read: '.$source);
        }

        $relative = 'demo/'.$slug.'-'.substr($hash, 0, 12).'.jpg';
        $disk = Storage::disk('public');
        if (! $disk->exists($relative) && ! $disk->put($relative, File::get($source))) {
            throw new \RuntimeException('Demo asset could not be stored: '.$relative);
        }

        return $relative;
    }

    private function demoPythonScript(): string
    {
        return <<<'PY'
"""Agovena demo download: a safe local automation starter."""

from datetime import datetime, timezone


def build_status_message(service_name: str) -> str:
    timestamp = datetime.now(timezone.utc).isoformat()
    return f"{service_name}: demo status generated at {timestamp}"


if __name__ == "__main__":
    print(build_status_message("Agovena demo service"))
PY;
    }
}
