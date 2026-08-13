<?php

declare(strict_types=1);

namespace App\Agovena\Api;

final class OpenApiDocument
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Agovena Storefront API',
                'version' => 'v1',
                'description' => 'Versioned customer/storefront REST API. v1 is early but intentional; it is not a frozen public contract.',
            ],
            'servers' => [
                ['url' => '/api/v1'],
            ],
            'tags' => [
                ['name' => 'Catalog'],
                ['name' => 'Auth'],
                ['name' => 'Account'],
                ['name' => 'Cart'],
                ['name' => 'Checkout'],
                ['name' => 'Commerce'],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'token',
                    ],
                    'cartToken' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-Cart-Token',
                    ],
                ],
                'schemas' => [
                    'Error' => [
                        'type' => 'object',
                        'required' => ['message', 'code'],
                        'properties' => [
                            'message' => ['type' => 'string'],
                            'code' => [
                                'type' => 'string',
                                'enum' => [
                                    'validation_error',
                                    'unauthenticated',
                                    'unauthorized',
                                    'not_found',
                                    'capability_unavailable',
                                    'invalid_state',
                                    'rate_limited',
                                    'payment_failed',
                                    'checkout_failed',
                                ],
                            ],
                            'errors' => ['type' => 'object'],
                        ],
                    ],
                ],
            ],
            'paths' => [
                '/openapi.json' => [
                    'get' => [
                        'tags' => ['Catalog'],
                        'summary' => 'OpenAPI document',
                        'responses' => ['200' => ['description' => 'OpenAPI 3 document']],
                    ],
                ],
                '/products' => [
                    'get' => [
                        'tags' => ['Catalog'],
                        'summary' => 'List products',
                        'parameters' => [
                            ['name' => 'q', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'category', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                        ],
                        'responses' => ['200' => ['description' => 'Paginated products']],
                    ],
                ],
                '/products/{slug}' => [
                    'get' => [
                        'tags' => ['Catalog'],
                        'summary' => 'Product detail',
                        'parameters' => [
                            ['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                        ],
                        'responses' => ['200' => ['description' => 'Product'], '404' => ['description' => 'Not found']],
                    ],
                ],
                '/categories' => [
                    'get' => [
                        'tags' => ['Catalog'],
                        'summary' => 'List categories',
                        'responses' => ['200' => ['description' => 'Categories']],
                    ],
                ],
                '/categories/{slug}' => [
                    'get' => [
                        'tags' => ['Catalog'],
                        'summary' => 'Category with products',
                        'parameters' => [
                            ['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                        ],
                        'responses' => ['200' => ['description' => 'Category + products']],
                    ],
                ],
                '/search' => [
                    'get' => [
                        'tags' => ['Catalog'],
                        'summary' => 'Product search',
                        'parameters' => [
                            ['name' => 'q', 'in' => 'query', 'schema' => ['type' => 'string']],
                        ],
                        'responses' => ['200' => ['description' => 'Matches']],
                    ],
                ],
                '/auth/tokens' => [
                    'post' => [
                        'tags' => ['Auth'],
                        'summary' => 'Create a personal API token',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['email', 'password', 'name'],
                                        'properties' => [
                                            'email' => ['type' => 'string'],
                                            'password' => ['type' => 'string'],
                                            'name' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Plain token shown once'],
                            '422' => ['description' => 'Invalid credentials'],
                            '429' => ['description' => 'Rate limited'],
                        ],
                    ],
                    'delete' => [
                        'tags' => ['Auth'],
                        'summary' => 'Revoke the current token',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['200' => ['description' => 'Revoked']],
                    ],
                ],
                '/me' => [
                    'get' => [
                        'tags' => ['Account'],
                        'summary' => 'Current customer',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['200' => ['description' => 'Account']],
                    ],
                    'patch' => [
                        'tags' => ['Account'],
                        'summary' => 'Update name',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['200' => ['description' => 'Account']],
                    ],
                ],
                '/addresses' => [
                    'get' => [
                        'tags' => ['Account'],
                        'summary' => 'List addresses',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['200' => ['description' => 'Addresses']],
                    ],
                    'post' => [
                        'tags' => ['Account'],
                        'summary' => 'Create address',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['201' => ['description' => 'Address']],
                    ],
                ],
                '/cart' => [
                    'get' => [
                        'tags' => ['Cart'],
                        'summary' => 'Current cart',
                        'security' => [['cartToken' => []]],
                        'responses' => ['200' => ['description' => 'Cart']],
                    ],
                    'post' => [
                        'tags' => ['Cart'],
                        'summary' => 'Add line',
                        'security' => [['cartToken' => []]],
                        'responses' => ['200' => ['description' => 'Cart']],
                    ],
                ],
                '/checkout/requirements' => [
                    'get' => [
                        'tags' => ['Checkout'],
                        'summary' => 'Checkout requirements for the current cart',
                        'responses' => ['200' => ['description' => 'Requirements']],
                    ],
                ],
                '/checkout' => [
                    'post' => [
                        'tags' => ['Checkout'],
                        'summary' => 'Place order from the current cart',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['201' => ['description' => 'Order'], '422' => ['description' => 'Invalid state']],
                    ],
                ],
                '/orders' => [
                    'get' => [
                        'tags' => ['Commerce'],
                        'summary' => 'Order history',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['200' => ['description' => 'Paginated orders']],
                    ],
                ],
                '/orders/{order}' => [
                    'get' => [
                        'tags' => ['Commerce'],
                        'summary' => 'Order detail',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['200' => ['description' => 'Order'], '404' => ['description' => 'Not found']],
                    ],
                ],
                '/orders/{order}/pay' => [
                    'post' => [
                        'tags' => ['Checkout'],
                        'summary' => 'Start payment for an unpaid order',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['200' => ['description' => 'Attempt'], '422' => ['description' => 'Cannot pay']],
                    ],
                ],
                '/invoices' => [
                    'get' => [
                        'tags' => ['Commerce'],
                        'summary' => 'Invoices',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['200' => ['description' => 'Paginated invoices']],
                    ],
                ],
                '/invoices/{invoice}' => [
                    'get' => [
                        'tags' => ['Commerce'],
                        'summary' => 'Invoice detail',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['200' => ['description' => 'Invoice']],
                    ],
                ],
                '/credit-notes' => [
                    'get' => [
                        'tags' => ['Commerce'],
                        'summary' => 'Credit notes',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['200' => ['description' => 'Paginated credit notes']],
                    ],
                ],
                '/credit-notes/{creditNote}' => [
                    'get' => [
                        'tags' => ['Commerce'],
                        'summary' => 'Credit note detail',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['200' => ['description' => 'Credit note']],
                    ],
                ],
                '/support-tickets' => [
                    'get' => [
                        'tags' => ['Commerce'],
                        'summary' => 'Support tickets',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['200' => ['description' => 'Paginated tickets']],
                    ],
                ],
                '/subscriptions' => [
                    'get' => [
                        'tags' => ['Commerce'],
                        'summary' => 'Subscriptions (when the Subscriptions module is enabled)',
                        'security' => [['bearerAuth' => []]],
                        'responses' => [
                            '200' => ['description' => 'Paginated subscriptions'],
                            '404' => ['description' => 'Capability unavailable'],
                        ],
                    ],
                ],
                '/services' => [
                    'get' => [
                        'tags' => ['Commerce'],
                        'summary' => 'Provisioned services (when the Provisioning module is enabled)',
                        'security' => [['bearerAuth' => []]],
                        'responses' => [
                            '200' => ['description' => 'Paginated services'],
                            '404' => ['description' => 'Capability unavailable'],
                        ],
                    ],
                ],
                '/downloads' => [
                    'get' => [
                        'tags' => ['Commerce'],
                        'summary' => 'Digital entitlements (when the Digital module is enabled)',
                        'security' => [['bearerAuth' => []]],
                        'responses' => [
                            '200' => ['description' => 'Paginated downloads'],
                            '404' => ['description' => 'Capability unavailable'],
                        ],
                    ],
                ],
                '/event-tickets' => [
                    'get' => [
                        'tags' => ['Commerce'],
                        'summary' => 'Event tickets (when the Events module is enabled)',
                        'security' => [['bearerAuth' => []]],
                        'responses' => [
                            '200' => ['description' => 'Paginated tickets'],
                            '404' => ['description' => 'Capability unavailable'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
