<?php

namespace Tests;

use App\Support\DemoData;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Los datos de la demo se construyen una vez por proceso: cada prueba parte de cero
        DemoData::flush();
    }
}
