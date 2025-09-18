<?php

declare(strict_types=1);

namespace Kode\Attributes\Tests;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class SampleAttribute
{
    public function __construct(
        public readonly string $name,
        public readonly int $priority = 0
    ) {
    }
}