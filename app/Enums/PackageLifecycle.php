<?php

declare(strict_types=1);

namespace App\Enums;

enum PackageLifecycle: string
{
    case Available = 'available';
    case Installed = 'installed';
    case Enabled = 'enabled';
    case Disabled = 'disabled';
    case UpdateAvailable = 'update_available';
    case Incompatible = 'incompatible';

    public function labelKey(): string
    {
        return 'admin.packages.status.'.$this->value;
    }
}
