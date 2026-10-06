<?php

namespace App\Health;

final readonly class CheckResult
{
    public function __construct(
        public bool $ok,
        public ?string $error = null,
    ) {}
}
