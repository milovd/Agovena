<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;

test('payment method icons resolve local and provider icon sources consistently', function (): void {
    $card = view('components.ag.payment-method-icon', [
        'icon' => 'ag:payment-method/card',
        'methodId' => 'stripe:card',
        'size' => 36,
    ])->render();

    $twint = view('components.ag.payment-method-icon', [
        'icon' => null,
        'methodId' => 'stripe:twint',
        'size' => 36,
    ])->render();

    $remote = view('components.ag.payment-method-icon', [
        'icon' => 'https://stripe.com/example.svg',
        'methodId' => 'stripe:card',
        'size' => 36,
    ])->render();

    Assert::assertStringContainsString('/images/payment-methods/card.svg', $card);
    Assert::assertStringNotContainsString('ag:payment-method/card', $card);
    Assert::assertStringContainsString('/images/payment-methods/twint.svg', $twint);
    Assert::assertStringContainsString('src="https://stripe.com/example.svg"', $remote);
});

test('credit card asset is a transparent Agovena illustration', function (): void {
    $svg = file_get_contents(public_path('images/payment-methods/creditcard.svg'));

    Assert::assertIsString($svg);
    Assert::assertStringContainsString('id="card-surface"', $svg);
    Assert::assertStringNotContainsString('fill="#fff"', $svg);
    Assert::assertStringNotContainsString('fill="white"', $svg);
});

test('checkout payment icons keep their transparent surface', function (): void {
    $css = file_get_contents(base_path('themes/default/resources/css/components/_checkout.css'));

    Assert::assertIsString($css);
    Assert::assertMatchesRegularExpression(
        '/\.store-choice__icon\s*\{[^}]*padding:\s*0;[^}]*background:\s*transparent;[^}]*\}/s',
        $css,
    );
});
