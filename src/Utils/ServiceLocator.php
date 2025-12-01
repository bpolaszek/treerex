<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Utils;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * @internal - Use your framework's service container instead
 *
 * @template T of object
 */
final readonly class ServiceLocator implements ContainerInterface
{
    /**
     * @param array<class-string<T>, T> $services
     */
    public function __construct(
        private array $services = [],
    ) {
    }

    /**
     * @param class-string<T> $id
     *
     * @return T
     */
    public function get(string $id): object
    {
        return match ($this->has($id)) {
            true => $this->services[$id],
            default => throw new class("Service `$id` not found.") extends InvalidArgumentException implements NotFoundExceptionInterface {},
        };
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
