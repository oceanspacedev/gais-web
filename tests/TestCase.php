<?php

namespace Tests;

use App\Models\CustomAssetAttribute;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Id atribut identitas di-cache statis; tiap tes bisa memakai database lain (SQLite memori / MySQL).
        CustomAssetAttribute::forgetKeyIds();
    }
}
