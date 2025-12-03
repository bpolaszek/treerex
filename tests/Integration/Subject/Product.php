<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Tests\Integration\Subject;

final class Product
{
    public function __construct(
        public int $stock,
        public bool $blacklisted,
        public bool $categorized,
        public bool $expired = false,
    ) {
    }
}
