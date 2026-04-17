<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_that_application_is_running_php_84(): void
    {
        $this->assertSame('8.4', PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION);
    }
}
