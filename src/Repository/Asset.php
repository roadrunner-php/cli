<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Repository;

abstract class Asset implements AssetInterface
{
    public function __construct(
        protected string $name,
        protected string $uri,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUri(): string
    {
        return $this->uri;
    }
}
