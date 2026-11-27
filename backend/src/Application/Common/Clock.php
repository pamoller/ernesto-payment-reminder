<?php

declare(strict_types=1);

namespace App\Application\Common;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
