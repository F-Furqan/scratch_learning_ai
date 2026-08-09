<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestEnvironmentIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_suite_uses_only_the_dedicated_mysql_test_database(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('scratch-test', config('database.connections.mysql.database'));
        $this->assertSame('scratch-test', DB::scalar('select database()'));
    }
}
