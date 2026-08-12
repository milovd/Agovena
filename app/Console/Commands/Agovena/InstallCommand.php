<?php

declare(strict_types=1);

namespace App\Console\Commands\Agovena;

use App\Agovena\Installation\InstallAgovena;
use App\Agovena\Installation\InstallationException;
use App\Agovena\Installation\InstallationRequirements;
use App\Agovena\Installation\InstallationState;
use App\Agovena\Installation\InstallRequest;
use App\Agovena\Installation\RequirementCheck;
use App\Agovena\Theme\ThemeManager;
use App\Models\Currency;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('agovena:install
    {--name= : Owner full name}
    {--email= : Owner email}
    {--password= : Owner password (never logged)}
    {--site-name= : Store / company name}
    {--locale= : Default locale code}
    {--timezone= : Default timezone}
    {--currency= : Base currency code}
    {--theme= : Theme id to activate}
')]
#[Description('Install Agovena: create owner and minimum store configuration')]
final class InstallCommand extends Command
{
    public function handle(
        InstallationState $state,
        InstallationRequirements $requirements,
        InstallAgovena $install,
        ThemeManager $themes,
    ): int {
        if ($state->installed()) {
            $this->error('Agovena is already installed.');

            return self::FAILURE;
        }

        $this->info('Agovena installation');
        $this->newLine();

        foreach ($requirements->checks() as $check) {
            $this->line($this->formatCheck($check));
        }

        $this->newLine();

        if (! $requirements->ready()) {
            $this->error('Fix the failed required checks, then run agovena:install again.');

            return self::FAILURE;
        }

        $interactive = ! $this->option('no-interaction');

        $name = $this->stringOption('name') ?? ($interactive ? (string) $this->ask('Owner name') : null);
        $email = $this->stringOption('email') ?? ($interactive ? (string) $this->ask('Owner email') : null);
        $password = $this->stringOption('password') ?? ($interactive ? (string) $this->secret('Owner password') : null);
        $siteName = $this->stringOption('site-name') ?? ($interactive ? (string) $this->ask('Store name', config('app.name', 'Agovena')) : null);
        $locale = $this->stringOption('locale') ?? ($interactive ? (string) $this->choice(
            'Default language',
            array_keys(config('agovena.locales', ['en' => 'English'])),
            config('app.locale', 'en'),
        ) : null);
        $timezone = $this->stringOption('timezone') ?? ($interactive ? (string) $this->ask('Timezone', config('app.timezone', 'UTC')) : null);

        $currencyCodes = Currency::query()->where('is_active', true)->orderBy('code')->pluck('code')->all();
        $currency = $this->stringOption('currency') ?? ($interactive ? (string) $this->choice(
            'Base currency',
            $currencyCodes !== [] ? $currencyCodes : ['EUR'],
            'EUR',
        ) : null);

        $themeIds = array_keys($themes->all());
        $defaultTheme = in_array('default', $themeIds, true) ? 'default' : ($themeIds[0] ?? null);
        $theme = $this->stringOption('theme') ?? ($interactive
            ? (string) $this->choice('Theme', $themeIds !== [] ? $themeIds : ['default'], $defaultTheme ?? 'default')
            : $defaultTheme);

        if ($name === null || $email === null || $password === null || $siteName === null || $locale === null || $timezone === null || $currency === null || $theme === null) {
            $this->error('Missing required options for non-interactive install: --name, --email, --password, --site-name, --locale, --timezone, --currency (and --theme if multiple Themes exist).');

            return self::FAILURE;
        }

        $confirmPassword = $password;
        if ($interactive && $this->stringOption('password') === null) {
            $confirmPassword = (string) $this->secret('Confirm password');
        }

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmPassword,
            'site_name' => $siteName,
            'locale' => $locale,
            'timezone' => $timezone,
            'currency' => $currency,
            'theme' => $theme,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'site_name' => ['required', 'string', 'max:255'],
            'locale' => ['required', 'string'],
            'timezone' => ['required', 'timezone:all'],
            'currency' => ['required', 'string', 'size:3'],
            'theme' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        try {
            $owner = $install(new InstallRequest(
                ownerName: $name,
                ownerEmail: $email,
                ownerPassword: $password,
                siteName: $siteName,
                locale: $locale,
                timezone: $timezone,
                currencyCode: strtoupper($currency),
                themeId: $theme,
            ));
        } catch (InstallationException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Agovena installed successfully.');
        $this->line('Owner: '.$owner->email);
        $this->line('Admin: '.url('/admin/login'));

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    private function formatCheck(RequirementCheck $check): string
    {
        $status = $check->passed ? '<info>PASS</info>' : ($check->required ? '<error>FAIL</error>' : '<comment>WARN</comment>');
        $detail = $check->detail !== null ? " — {$check->detail}" : '';

        return "{$status}  {$check->id}{$detail}";
    }
}
