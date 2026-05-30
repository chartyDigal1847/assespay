<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SsoExchangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_sso_exchange_replaces_stale_assesspay_session_with_current_portal_user(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'user' => [
                    'id' => '2',
                    'name' => 'Student User',
                    'email' => 'student@example.com',
                    'role' => 'student',
                ],
            ], 200),
        ]);

        $response = $this
            ->withSession([
                'sso_id' => '7',
                'sso_name' => 'Cashier User',
                'sso_email' => 'cashier@example.com',
                'sso_role' => 'cashier',
            ])
            ->postJson('/sso/exchange', [
                'token' => 'fresh-portal-token',
                'embedded' => true,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.id', '2')
            ->assertJsonPath('user.role', 'student')
            ->assertJsonPath('redirect', route('assesspay.student'));

        $this->assertSame('2', session('sso_id'));
        $this->assertSame('student', session('sso_role'));
        $this->assertSame('student@example.com', session('sso_email'));
    }
}
