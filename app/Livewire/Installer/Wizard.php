<?php

declare(strict_types=1);

namespace App\Livewire\Installer;

use App\Agovena\Installation\InstallAgovena;
use App\Agovena\Installation\InstallationException;
use App\Agovena\Installation\InstallationRequirements;
use App\Agovena\Installation\InstallationState;
use App\Agovena\Installation\InstallRequest;
use App\Agovena\Store\StorePresetCatalog;
use App\Agovena\Theme\ThemeManager;
use App\Models\Currency;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

final class Wizard extends Component
{
    use WithFileUploads;

    public const STEPS = [
        'welcome',
        'owner',
        'store',
        'catalog',
        'regional',
        'branding',
        'theme',
        'complete',
    ];

    public string $step = 'welcome';

    public string $ownerName = '';

    public string $ownerEmail = '';

    public string $ownerPassword = '';

    public string $ownerPasswordConfirmation = '';

    public string $siteName = '';

    public string $locale = 'en';

    public string $timezone = 'UTC';

    public string $currency = 'EUR';

    public string $themeId = 'default';

    /** @var list<string> */
    public array $presetIds = [];

    public bool $useLogoAsFavicon = true;

    /** @var TemporaryUploadedFile|null */
    public $logo = null;

    /** @var TemporaryUploadedFile|null */
    public $favicon = null;

    public string $installError = '';

    public function mount(InstallationState $state, ThemeManager $themes): void
    {
        abort_if($state->installed(), 404);

        $this->siteName = (string) config('app.name', 'Agovena');
        $this->locale = (string) config('app.locale', 'en');
        $this->timezone = (string) config('app.timezone', 'UTC');

        $themeIds = array_keys($themes->all());
        $this->themeId = in_array('default', $themeIds, true)
            ? 'default'
            : (string) ($themeIds[0] ?? 'default');
    }

    public function next(InstallationRequirements $requirements): void
    {
        $this->installError = '';

        match ($this->step) {
            'welcome' => $this->advanceFromWelcome($requirements),
            'owner' => $this->advanceFromOwner(),
            'store' => $this->advanceFromStore(),
            'catalog' => $this->advanceFromCatalog(),
            'regional' => $this->advanceFromRegional(),
            'branding' => $this->advanceFromBranding(),
            'theme' => null,
            default => null,
        };
    }

    public function back(): void
    {
        $this->installError = '';
        $index = array_search($this->step, self::STEPS, true);
        if (! is_int($index) || $index <= 0 || $this->step === 'complete') {
            return;
        }

        $this->step = self::STEPS[$index - 1];
    }

    public function skipCatalog(): void
    {
        $this->presetIds = [];
        $this->step = 'regional';
    }

    public function skipBranding(): void
    {
        $this->logo = null;
        $this->favicon = null;
        $this->step = 'theme';
    }

    public function install(InstallAgovena $install): void
    {
        $this->installError = '';

        $this->validate([
            'themeId' => ['required', 'string', 'max:64'],
        ]);

        $logoPath = null;
        $faviconPath = null;

        if ($this->logo instanceof TemporaryUploadedFile) {
            $logoPath = $this->logo->store('branding', 'public');
        }

        if (! $this->useLogoAsFavicon && $this->favicon instanceof TemporaryUploadedFile) {
            $faviconPath = $this->favicon->store('branding', 'public');
        } elseif ($this->useLogoAsFavicon && $logoPath !== null) {
            $faviconPath = $logoPath;
        }

        try {
            $install(new InstallRequest(
                ownerName: $this->ownerName,
                ownerEmail: $this->ownerEmail,
                ownerPassword: $this->ownerPassword,
                siteName: $this->siteName,
                locale: $this->locale,
                timezone: $this->timezone,
                currencyCode: $this->currency,
                themeId: $this->themeId,
                logoPath: $logoPath,
                faviconPath: $faviconPath,
                presetIds: $this->presetIds,
            ));
        } catch (InstallationException $e) {
            if ($logoPath !== null) {
                Storage::disk('public')->delete($logoPath);
            }
            if ($faviconPath !== null && $faviconPath !== $logoPath) {
                Storage::disk('public')->delete($faviconPath);
            }
            $this->installError = $e->getMessage();

            return;
        }

        $this->ownerPassword = '';
        $this->ownerPasswordConfirmation = '';
        $this->logo = null;
        $this->favicon = null;
        $this->step = 'complete';
    }

    public function render(InstallationRequirements $requirements, ThemeManager $themes, StorePresetCatalog $catalog)
    {
        $locales = config('agovena.locales', ['en' => 'English']);
        $currencies = Currency::query()->where('is_active', true)->orderBy('code')->get();
        $themeList = array_values($themes->all());

        return view('livewire.installer.wizard', [
            'checks' => $requirements->checks(),
            'ready' => $requirements->ready(),
            'locales' => $locales,
            'currencies' => $currencies,
            'themes' => $themeList,
            'catalog' => $catalog->all(),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'appUrl' => (string) config('app.url'),
            'stepIndex' => (int) array_search($this->step, self::STEPS, true),
            'stepCount' => count(self::STEPS) - 1, // exclude complete from progress denominator
            'progressSteps' => array_slice(self::STEPS, 0, -1),
        ])->layout('layouts.installer', [
            'title' => __('installer.title'),
        ]);
    }

    private function advanceFromWelcome(InstallationRequirements $requirements): void
    {
        if (! $requirements->ready()) {
            $this->addError('welcome', __('installer.errors.requirements'));

            return;
        }

        $this->step = 'owner';
    }

    private function advanceFromOwner(): void
    {
        $this->validate([
            'ownerName' => ['required', 'string', 'max:255'],
            'ownerEmail' => ['required', 'email', 'max:255'],
            'ownerPassword' => ['required', 'string', Password::defaults()],
            'ownerPasswordConfirmation' => ['required', 'same:ownerPassword'],
        ], [], [
            'ownerName' => __('installer.fields.owner_name'),
            'ownerEmail' => __('installer.fields.owner_email'),
            'ownerPassword' => __('installer.fields.owner_password'),
            'ownerPasswordConfirmation' => __('installer.fields.owner_password_confirmation'),
        ]);

        $this->step = 'store';
    }

    private function advanceFromStore(): void
    {
        $this->validate([
            'siteName' => ['required', 'string', 'max:255'],
        ], [], [
            'siteName' => __('installer.fields.site_name'),
        ]);

        $this->step = 'catalog';
    }

    private function advanceFromCatalog(): void
    {
        $allowed = [];
        foreach (app(StorePresetCatalog::class)->all() as $preset) {
            $allowed[] = $preset->id;
        }

        $this->presetIds = array_values(array_filter(
            $this->presetIds,
            static fn (string $id): bool => in_array($id, $allowed, true),
        ));

        $this->step = 'regional';
    }

    private function advanceFromRegional(): void
    {
        $localeKeys = array_keys(config('agovena.locales', ['en' => 'English']));

        $this->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', $localeKeys)],
            'timezone' => ['required', 'timezone:all'],
            'currency' => ['required', 'string', 'size:3', 'exists:currencies,code'],
        ], [], [
            'locale' => __('installer.fields.locale'),
            'timezone' => __('installer.fields.timezone'),
            'currency' => __('installer.fields.currency'),
        ]);

        $this->step = 'branding';
    }

    private function advanceFromBranding(): void
    {
        $this->validate([
            'logo' => ['nullable', 'image', 'max:2048'],
            'favicon' => ['nullable', 'image', 'max:2048'],
        ]);

        $this->step = 'theme';
    }
}
