<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApplicationBootstrapTest extends TestCase
{
    public function test_home_page_renders_the_base_layout_with_local_bootstrap(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('FoodOrder');
        $response->assertSee('vendor/bootstrap/css/bootstrap.min.css', false);
        $this->assertFileExists(public_path('vendor/bootstrap/css/bootstrap.min.css'));
    }

    public function test_health_endpoint_responds(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_tests_run_against_an_isolated_in_memory_sqlite_database(): void
    {
        $this->assertTrue($this->app->environment('testing'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_sqlite_connection_works_with_foreign_keys_enabled(): void
    {
        $this->assertNotEmpty(DB::selectOne('select sqlite_version() as version')->version);
        $this->assertSame(1, (int) DB::selectOne('pragma foreign_keys')->foreign_keys);
    }
}
