<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Tests\Integration\Subject;

final class User
{
    public function __construct(
        public string $role,
    ) {
    }
}
