<?php

declare(strict_types=1);

namespace App\Agovena\Imports\Contracts;

use App\Agovena\Imports\ImportCandidate;

interface ImportAdapter
{
    /**
     * @param  array<string, string|null>  $row
     */
    public function map(array $row, int $line): ImportCandidate;
}
