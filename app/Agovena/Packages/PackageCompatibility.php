<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;

/**
 * Single source of truth for whether a Module or Extension may run on this Core.
 *
 * Two rules apply:
 *  1. The package's "agovena" constraint must accept the Core version. While Core
 *     is 0.x, a minor release (0.1.0, 0.2.0) may change the package API and a patch
 *     release (0.0.2) must not, so packages declare ">=0.0.1 <0.1.0" style ranges.
 *  2. Core may require a minimum version of a package it knows about
 *     (agovena.packages.minimum_versions), for example before removing something
 *     older package versions still rely on.
 */
final class PackageCompatibility
{
    public function platformVersion(): string
    {
        return (string) config('agovena.version');
    }

    /**
     * Human-readable reason the package cannot run on this Core, or null when it can.
     */
    public function problem(string $packageId, string $packageVersion, string $constraint): ?string
    {
        if (! $this->acceptsPlatform($constraint)) {
            return __('admin.packages.incompatible', [
                'constraint' => $constraint,
                'platform' => $this->platformVersion(),
            ]);
        }

        $minimum = $this->minimumVersion($packageId);
        if ($minimum !== null && ! $this->meetsMinimum($packageVersion, $minimum)) {
            return __('admin.packages.update_required', [
                'version' => $packageVersion,
                'minimum' => $minimum,
                'platform' => $this->platformVersion(),
            ]);
        }

        return null;
    }

    public function acceptsPlatform(string $constraint): bool
    {
        if ($constraint === '*' || $constraint === '') {
            return true;
        }

        try {
            return Semver::satisfies($this->platformVersion(), $constraint);
        } catch (\UnexpectedValueException) {
            return false;
        }
    }

    public function minimumVersion(string $packageId): ?string
    {
        $minimum = config('agovena.packages.minimum_versions.'.$packageId);

        return is_string($minimum) && $minimum !== '' ? $minimum : null;
    }

    /**
     * The constraint new packages should declare: this Core version up to the next minor release.
     */
    public function recommendedConstraint(): string
    {
        $normalized = (new VersionParser)->normalize($this->platformVersion());
        [$major, $minor] = array_map('intval', array_slice(explode('.', $normalized), 0, 2));
        $lowest = $major.'.'.$minor.'.0';
        $next = $major === 0 ? '0.'.($minor + 1).'.0' : ($major + 1).'.0.0';

        return '>='.$lowest.' <'.$next;
    }

    private function meetsMinimum(string $version, string $minimum): bool
    {
        try {
            return Comparator::greaterThanOrEqualTo($version, $minimum);
        } catch (\UnexpectedValueException) {
            return false;
        }
    }
}
