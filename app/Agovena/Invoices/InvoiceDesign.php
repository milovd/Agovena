<?php

declare(strict_types=1);

namespace App\Agovena\Invoices;

/**
 * How invoices and credit notes look: template, brand color and which optional details are printed.
 * Presentation only. Financial content always comes from the issued document snapshot.
 */
final readonly class InvoiceDesign
{
    public const TEMPLATES = ['clean', 'business', 'bold', 'banner', 'angle'];

    public const DEFAULT_TEMPLATE = 'clean';

    public const DEFAULT_COLOR = '#1F3A5F';

    public const COLOR_PRESETS = ['#1F3A5F', '#155EEF', '#0F766E', '#4D7C0F', '#B45309', '#B91C1C', '#6D28D9', '#111827'];

    /** Setting keys that toggle optional details, with their defaults. */
    public const TOGGLES = [
        'show_logo' => true,
        'show_seller_address' => true,
        'show_vat_number' => true,
        'show_company_number' => true,
        'show_contact' => true,
        'show_buyer_properties' => true,
        'show_payment_details' => true,
        'show_notes' => true,
    ];

    /** Free text setting keys with their maximum length. */
    public const TEXTS = [
        'contact_email' => 255,
        'contact_phone' => 60,
        'website' => 255,
        'payment_details' => 1000,
        'notes' => 1000,
        'footer_text' => 255,
    ];

    /**
     * @param  array<string, bool>  $show
     */
    public function __construct(
        public string $template,
        public string $color,
        public array $show,
        public ?string $logo,
        public ?string $contactEmail,
        public ?string $contactPhone,
        public ?string $website,
        public ?string $paymentDetails,
        public ?string $notes,
        public ?string $footerText,
    ) {}

    /**
     * Stored or draft setting values, normalized. Unknown or invalid values fall back to the defaults,
     * so a broken setting never breaks a document.
     *
     * @param  array<string, mixed>  $values
     */
    public static function fromValues(array $values, ?string $logo): self
    {
        $values = self::normalize($values);

        return new self(
            template: $values['template'],
            color: $values['accent_color'],
            show: array_intersect_key($values, self::TOGGLES),
            logo: $logo,
            contactEmail: self::text($values['contact_email']),
            contactPhone: self::text($values['contact_phone']),
            website: self::text($values['website']),
            paymentDetails: self::text($values['payment_details']),
            notes: self::text($values['notes']),
            footerText: self::text($values['footer_text']),
        );
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'template' => self::DEFAULT_TEMPLATE,
            'accent_color' => self::DEFAULT_COLOR,
            ...self::TOGGLES,
            ...array_fill_keys(array_keys(self::TEXTS), ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function normalize(array $values): array
    {
        $normalized = self::defaults();

        $template = $values['template'] ?? null;
        if (is_string($template) && in_array($template, self::TEMPLATES, true)) {
            $normalized['template'] = $template;
        }

        $color = $values['accent_color'] ?? null;
        if (is_string($color) && preg_match('/\A#[0-9a-fA-F]{6}\z/', trim($color)) === 1) {
            $normalized['accent_color'] = strtoupper(trim($color));
        }

        foreach (array_keys(self::TOGGLES) as $key) {
            if (array_key_exists($key, $values)) {
                $normalized[$key] = filter_var($values[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        foreach (self::TEXTS as $key => $max) {
            $value = $values[$key] ?? '';
            $normalized[$key] = is_scalar($value) ? mb_substr(trim((string) $value), 0, $max) : '';
        }

        return $normalized;
    }

    /**
     * Validation rules for the Admin form, keyed for a `design.*` Livewire array.
     *
     * @return array<string, list<string>>
     */
    public static function rules(string $prefix = 'design.'): array
    {
        $rules = [
            $prefix.'template' => ['required', 'string', 'in:'.implode(',', self::TEMPLATES)],
            $prefix.'accent_color' => ['required', 'string', 'regex:/\A#[0-9a-fA-F]{6}\z/'],
            $prefix.'contact_email' => ['nullable', 'email', 'max:255'],
            $prefix.'website' => ['nullable', 'string', 'max:255', 'regex:/\A(https?:\/\/)?[^\s<>"]+\z/'],
        ];

        foreach (array_keys(self::TOGGLES) as $key) {
            $rules[$prefix.$key] = ['boolean'];
        }

        foreach (self::TEXTS as $key => $max) {
            $rules[$prefix.$key] ??= ['nullable', 'string', 'max:'.$max];
        }

        return $rules;
    }

    /** Same design without the notes, for templates that print the notes elsewhere. */
    public function withoutNotes(): self
    {
        return new self(
            template: $this->template,
            color: $this->color,
            show: $this->show,
            logo: $this->logo,
            contactEmail: $this->contactEmail,
            contactPhone: $this->contactPhone,
            website: $this->website,
            paymentDetails: $this->paymentDetails,
            notes: null,
            footerText: $this->footerText,
        );
    }

    public function shows(string $detail): bool
    {
        return $this->show['show_'.$detail] ?? false;
    }

    /** Text color that stays readable on the brand color. */
    public function ink(): string
    {
        [$r, $g, $b] = $this->rgb($this->color);
        $luminance = (0.2126 * $this->linear($r)) + (0.7152 * $this->linear($g)) + (0.0722 * $this->linear($b));

        return $luminance > 0.42 ? '#111827' : '#FFFFFF';
    }

    /** The brand color mixed with white; 0.0 is white, 1.0 is the color itself. */
    public function tint(float $strength): string
    {
        return $this->mix($this->color, [255, 255, 255], $strength);
    }

    /** The brand color mixed with black; 0.0 is the color itself, 1.0 is black. */
    public function shade(float $amount): string
    {
        return $this->mix($this->color, [0, 0, 0], 1 - $amount);
    }

    /** Brand color as an accent on white: darkened when it is too light to read. */
    public function accentText(): string
    {
        return $this->ink() === '#FFFFFF' ? $this->color : $this->shade(0.45);
    }

    /** Website without the scheme, for printing. */
    public function websiteLabel(): ?string
    {
        return $this->website === null ? null : (string) preg_replace('#\Ahttps?://#i', '', rtrim($this->website, '/'));
    }

    /** @return list<array{key: string, value: string}> */
    public function contactLines(): array
    {
        if (! $this->shows('contact')) {
            return [];
        }

        return array_values(array_filter([
            $this->contactEmail === null ? null : ['key' => 'email', 'value' => $this->contactEmail],
            $this->contactPhone === null ? null : ['key' => 'phone', 'value' => $this->contactPhone],
            $this->websiteLabel() === null ? null : ['key' => 'website', 'value' => (string) $this->websiteLabel()],
        ]));
    }

    /** Small SVG decoration as a data URI; the colors are this design's, never user markup. */
    public function svg(string $markup, int $width, int $height): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" preserveAspectRatio="none">'.$markup.'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @return array{int, int, int} */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    private function linear(int $channel): float
    {
        $value = $channel / 255;

        return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }

    /** @param  array{int, int, int}  $base */
    private function mix(string $hex, array $base, float $strength): string
    {
        $strength = max(0.0, min(1.0, $strength));
        $color = $this->rgb($hex);
        $mixed = array_map(
            static fn (int $channel, int $baseChannel): int => (int) round($baseChannel + (($channel - $baseChannel) * $strength)),
            $color,
            $base,
        );

        return sprintf('#%02X%02X%02X', ...$mixed);
    }
}
