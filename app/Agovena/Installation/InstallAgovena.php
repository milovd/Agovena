<?php

declare(strict_types=1);

namespace App\Agovena\Installation;

use App\Agovena\Money\CurrencyCatalog;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Staff\CreateOwnerStaff;
use App\Agovena\Theme\ThemeManager;
use App\Models\Currency;
use App\Models\StaffUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Throwable;

final class InstallAgovena
{
    public function __construct(
        private readonly InstallationState $state,
        private readonly InstallationRequirements $requirements,
        private readonly CreateOwnerStaff $createOwner,
        private readonly SettingsRepository $settings,
        private readonly ThemeManager $themes,
        private readonly CurrencyCatalog $currencies,
    ) {}

    public function __invoke(InstallRequest $request, bool $enforceRequirements = true): StaffUser
    {
        $this->state->assertNotInstalled();

        if ($enforceRequirements && ! $this->requirements->ready()) {
            $labels = array_map(
                static fn (RequirementCheck $c): string => $c->id,
                $this->requirements->failures(),
            );
            throw InstallationException::requirementsNotMet(implode(', ', $labels));
        }

        $this->validateRequest($request);

        try {
            return DB::transaction(function () use ($request): StaffUser {
                $this->state->assertNotInstalled();

                $owner = ($this->createOwner)(
                    $request->ownerName,
                    $request->ownerEmail,
                    $request->ownerPassword,
                    refuseIfOwnerExists: true,
                );

                $this->ensureCurrency($request->currencyCode);

                $this->settings->setMany('general', [
                    'site_name' => $request->siteName,
                    'locale' => $request->locale,
                    'timezone' => $request->timezone,
                    'base_currency' => strtoupper($request->currencyCode),
                ]);

                if ($request->logoPath !== null && $request->logoPath !== '') {
                    $this->settings->set('branding', 'logo_path', $request->logoPath);
                }

                if ($request->faviconPath !== null && $request->faviconPath !== '') {
                    $this->settings->set('branding', 'favicon_path', $request->faviconPath);
                } elseif ($request->logoPath !== null && $request->logoPath !== '') {
                    $this->settings->set('branding', 'favicon_path', $request->logoPath);
                }

                $this->themes->activate($request->themeId);

                $this->state->markInstalled();

                return $owner;
            });
        } catch (InstallationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw $e;
        }
    }

    private function validateRequest(InstallRequest $request): void
    {
        $locales = array_keys(config('agovena.locales', ['en' => 'English']));

        $validator = Validator::make([
            'owner_name' => $request->ownerName,
            'owner_email' => $request->ownerEmail,
            'owner_password' => $request->ownerPassword,
            'site_name' => $request->siteName,
            'locale' => $request->locale,
            'timezone' => $request->timezone,
            'currency' => $request->currencyCode,
            'theme' => $request->themeId,
        ], [
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255'],
            'owner_password' => ['required', 'string', Password::defaults()],
            'site_name' => ['required', 'string', 'max:255'],
            'locale' => ['required', 'string', 'in:'.implode(',', $locales)],
            'timezone' => ['required', 'timezone:all'],
            'currency' => ['required', 'string', 'size:3'],
            'theme' => ['required', 'string', 'max:64'],
        ]);

        if ($validator->fails()) {
            throw InstallationException::requirementsNotMet($validator->errors()->first());
        }

        if (! isset(config('agovena.locales', [])[$request->locale])) {
            throw InstallationException::invalidLocale($request->locale);
        }

        if ($this->themes->find($request->themeId) === null) {
            throw InstallationException::invalidTheme($request->themeId);
        }
    }

    private function ensureCurrency(string $code): void
    {
        $code = strtoupper($code);

        $currency = Currency::query()->where('code', $code)->first();
        if ($currency === null) {
            throw InstallationException::invalidCurrency($code);
        }

        if (! $currency->is_active) {
            $currency->is_active = true;
            $currency->save();
        }

        $this->currencies->forget($code);
    }
}
