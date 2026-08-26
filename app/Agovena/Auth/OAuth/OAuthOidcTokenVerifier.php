<?php

declare(strict_types=1);

namespace App\Agovena\Auth\OAuth;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

final class OAuthOidcTokenVerifier
{
    /** @return array<string, mixed> */
    public function verify(OAuthProviderDefinition $provider, string $idToken, string $expectedNonce): array
    {
        if (! $provider->oidc || $provider->issuer === '' || $provider->jwksEndpoint === '') {
            throw ValidationException::withMessages(['provider' => 'The provider does not expose a valid OIDC contract.']);
        }

        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw ValidationException::withMessages(['provider' => 'The OIDC token format is invalid.']);
        }

        $header = $this->decodeJson($parts[0]);
        $claims = $this->decodeJson($parts[1]);
        if (($header['alg'] ?? null) !== 'RS256' || ! is_string($header['kid'] ?? null)) {
            throw ValidationException::withMessages(['provider' => 'The OIDC token algorithm is not allowed.']);
        }

        $clientId = (string) config('services.oauth.'.$provider->id.'.client_id', '');
        if ($clientId === '') {
            throw ValidationException::withMessages(['provider' => 'The OIDC client is not configured.']);
        }

        $response = Http::timeout(10)->get($provider->jwksEndpoint);
        $keys = $response->successful() ? $response->json('keys') : null;
        if (! is_array($keys)) {
            throw ValidationException::withMessages(['provider' => 'The OIDC signing keys are unavailable.']);
        }

        $key = collect($keys)->first(static fn (mixed $candidate): bool => is_array($candidate)
            && ($candidate['kid'] ?? null) === $header['kid']
            && ($candidate['kty'] ?? null) === 'RSA'
            && is_string($candidate['n'] ?? null)
            && is_string($candidate['e'] ?? null));
        if (! is_array($key)) {
            throw ValidationException::withMessages(['provider' => 'The OIDC signing key is unknown.']);
        }

        $publicKey = $this->publicKeyFromJwk($key['n'], $key['e']);
        $signature = $this->decodeBase64Url($parts[2]);
        if ($signature === null || openssl_verify($parts[0].'.'.$parts[1], $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw ValidationException::withMessages(['provider' => 'The OIDC token signature is invalid.']);
        }

        $audience = $claims['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : [$audience];
        if (($claims['iss'] ?? null) !== $provider->issuer || ! in_array($clientId, $audiences, true)) {
            throw ValidationException::withMessages(['provider' => 'The OIDC token issuer or audience is invalid.']);
        }
        if (is_array($audience) && count($audience) > 1 && ($claims['azp'] ?? null) !== $clientId) {
            throw ValidationException::withMessages(['provider' => 'The OIDC authorized party is invalid.']);
        }
        if (($claims['nonce'] ?? null) !== $expectedNonce || ! is_string($claims['sub'] ?? null)) {
            throw ValidationException::withMessages(['provider' => 'The OIDC token nonce or subject is invalid.']);
        }
        if (! is_int($claims['exp'] ?? null) || $claims['exp'] <= time()) {
            throw ValidationException::withMessages(['provider' => 'The OIDC token has expired.']);
        }
        if (! filter_var($claims['email'] ?? null, FILTER_VALIDATE_EMAIL)
            || filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN) !== true) {
            throw ValidationException::withMessages(['provider' => 'The OIDC token email is not verified.']);
        }

        return $claims;
    }

    /** @return array<string, mixed> */
    private function decodeJson(string $segment): array
    {
        $decoded = $this->decodeBase64Url($segment);
        $payload = $decoded === null ? null : json_decode($decoded, true);

        return is_array($payload)
            ? $payload
            : throw ValidationException::withMessages(['provider' => 'The OIDC token claims are invalid.']);
    }

    private function decodeBase64Url(string $value): ?string
    {
        $remainder = strlen($value) % 4;
        $normalized = strtr($value, '-_', '+/').($remainder === 0 ? '' : str_repeat('=', 4 - $remainder));
        $decoded = base64_decode($normalized, true);

        return $decoded === false ? null : $decoded;
    }

    private function publicKeyFromJwk(string $modulus, string $exponent): string
    {
        $modulusBytes = $this->decodeBase64Url($modulus);
        $exponentBytes = $this->decodeBase64Url($exponent);
        if ($modulusBytes === null || $exponentBytes === null) {
            throw ValidationException::withMessages(['provider' => 'The OIDC signing key is malformed.']);
        }

        $rsaKey = $this->asn1Sequence($this->asn1Integer($modulusBytes).$this->asn1Integer($exponentBytes));
        $algorithm = hex2bin('300d06092a864886f70d0101010500');
        $publicKey = $this->asn1Sequence($algorithm.$this->asn1BitString($rsaKey));

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($publicKey), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private function asn1Integer(string $value): string
    {
        if ($value === '' || (ord($value[0]) & 0x80) !== 0) {
            $value = "\0".$value;
        }

        return "\x02".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1BitString(string $value): string
    {
        return "\x03".$this->asn1Length(strlen($value) + 1)."\0".$value;
    }

    private function asn1Sequence(string $value): string
    {
        return "\x30".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1Length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $encoded = '';
        while ($length > 0) {
            $encoded = chr($length & 0xFF).$encoded;
            $length >>= 8;
        }

        return chr(0x80 | strlen($encoded)).$encoded;
    }
}
