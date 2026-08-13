<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PackageKind;
use App\Enums\PackageSourceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Merchant-installed Module or Extension package (Composer/VCS/path). Bundled first-party packages may have no row.
 *
 * @property int $id
 * @property PackageKind $kind
 * @property string $agovena_id
 * @property string|null $composer_name
 * @property PackageSourceType $source_type
 * @property string|null $source_locator
 * @property string $version_constraint
 * @property string|null $installed_version
 * @property string|null $available_version
 * @property string|null $install_path
 * @property bool $is_bundled
 */
#[Fillable([
    'kind',
    'agovena_id',
    'composer_name',
    'source_type',
    'source_locator',
    'version_constraint',
    'installed_version',
    'available_version',
    'install_path',
    'is_bundled',
])]
class AgovenaPackage extends Model
{
    protected $table = 'agovena_packages';

    protected function casts(): array
    {
        return [
            'kind' => PackageKind::class,
            'source_type' => PackageSourceType::class,
            'is_bundled' => 'boolean',
        ];
    }
}
