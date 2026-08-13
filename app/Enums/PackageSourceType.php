<?php

declare(strict_types=1);

namespace App\Enums;

enum PackageSourceType: string
{
    case Bundled = 'bundled';
    case Path = 'path';
    case Composer = 'composer';
    case Vcs = 'vcs';
}
