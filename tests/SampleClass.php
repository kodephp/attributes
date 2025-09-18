<?php

declare(strict_types=1);

namespace Kode\Attributes\Tests;

#[SampleAttribute('test-class', 10)]
#[SampleAttribute('another-class', 20)]
class SampleClass
{
    #[SampleAttribute('test-property')]
    private string $property;

    #[SampleAttribute('test-method', 5)]
    public function sampleMethod(): void
    {
        // Method body
    }
}