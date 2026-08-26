<?php

declare(strict_types=1);

namespace App\Agovena\Abuse;

use App\Agovena\Settings\SettingsRepository;
use Illuminate\Support\Facades\Http;
use Throwable;

final class ChallengeVerifierRegistry
{
    /** @var array<string, string> */
    private const ENDPOINTS = [
        'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'recaptcha' => 'https://www.google.com/recaptcha/api/siteverify',
    ];

    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function verify(string $provider, string $token, string $ip): ChallengeVerificationResult
    {
        $provider = strtolower(trim($provider));
        $endpoint = self::ENDPOINTS[$provider] ?? null;
        if ($endpoint === null) {
            return new ChallengeVerificationResult(false, $provider, 'unsupported_provider');
        }

        $secret = $this->settings->get('security', $provider.'_secret');
        if (! is_string($secret) || trim($secret) === '' || trim($token) === '') {
            return new ChallengeVerificationResult(false, $provider, 'not_configured');
        }

        try {
            $response = Http::asForm()
                ->connectTimeout(3)
                ->timeout(5)
                ->post($endpoint, [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ]);
        } catch (Throwable) {
            return new ChallengeVerificationResult(false, $provider, 'provider_unavailable');
        }

        return new ChallengeVerificationResult(
            $response->successful() && $response->json('success') === true,
            $provider,
            $response->successful() && $response->json('success') === true ? 'accepted' : 'rejected',
        );
    }
}
