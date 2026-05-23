<?php

namespace Tests\Feature;

use App\Services\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_are_seeded_by_migration(): void
    {
        $this->assertFalse(AppSettings::bool('allow_registration'));
        $this->assertSame(5120, AppSettings::int('default_quota_mb'));
        $this->assertSame(51200, AppSettings::int('max_quota_mb'));
        $this->assertFalse(AppSettings::bool('enable_noliae_ai'));
    }

    public function test_set_persists_and_invalidates_cache(): void
    {
        AppSettings::set('allow_registration', '1');
        $this->assertTrue(AppSettings::bool('allow_registration'));

        AppSettings::set('default_quota_mb', '10240');
        $this->assertSame(10240, AppSettings::int('default_quota_mb'));
    }

    public function test_registration_route_is_disabled_by_default(): void
    {
        $this->post('/register', [
            'local'    => 'bob',
            'password' => 'longenoughpassword',
        ])->assertStatus(403);
    }

    public function test_registration_route_works_when_enabled(): void
    {
        AppSettings::set('allow_registration', '1');
        // Manually seed a domain so register() can resolve it
        \Illuminate\Support\Facades\DB::table('mail_domains')->insert([
            'name'       => config('mail.primary_domain', 'example.com'),
            'active'     => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->post('/register', [
            'local'    => 'alice',
            'password' => 'longenoughpassword',
        ])->assertRedirect('/webmail');
    }
}
