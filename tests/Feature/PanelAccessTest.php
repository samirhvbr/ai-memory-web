<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The panel's front door. Two things are being locked down here:
 *
 *  1. nothing is readable without a session — this app renders, in plain text,
 *     everything the agents remember about every project on the host;
 *  2. with ai-memory unreachable (which is what phpunit.xml configures: an empty
 *     AI_MEMORY_SQLITE_PATH), every screen answers 200 with the notice. Never a
 *     500. That is the contract in docs/read-only.md §4.
 */
class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Before parent::setUp(), because RefreshDatabase migrates from inside
        // it — a skip declared afterwards would arrive one connection too late.
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required for the app database.');
        }

        parent::setUp();
    }

    public static function panelRoutes(): array
    {
        return [
            'dashboard' => ['/'],
            'projects' => ['/projects'],
            'workspaces' => ['/workspaces'],
            'pages' => ['/pages'],
            'sessions' => ['/sessions'],
            'observations' => ['/observations'],
            'handoffs' => ['/handoffs'],
            'search' => ['/search'],
        ];
    }

    #[DataProvider('panelRoutes')]
    public function test_a_guest_is_sent_to_the_login_page(string $path): void
    {
        $this->get($path)->assertRedirect('/login');
    }

    public function test_the_live_endpoint_is_behind_auth_too(): void
    {
        $this->get('/live')->assertRedirect('/login');
    }

    public function test_there_is_no_registration_route(): void
    {
        $this->get('/register')->assertNotFound();
    }

    #[DataProvider('panelRoutes')]
    public function test_every_screen_degrades_to_the_notice_instead_of_a_500(string $path): void
    {
        $this->actingAs($this->operator());

        $this->get($path)
            ->assertOk()
            ->assertSee('ai-memory is not reachable on this host');
    }

    public function test_the_live_endpoint_answers_unavailable_rather_than_failing(): void
    {
        $this->actingAs($this->operator());

        $this->getJson('/live')->assertOk()->assertExactJson(['available' => false]);
    }

    public function test_a_malformed_hex_id_never_reaches_a_query(): void
    {
        $this->actingAs($this->operator());

        // ai-memory ids are 32 hex chars; the route constraint rejects anything
        // else before the controller — and before the SQL — ever sees it.
        $this->get('/pages/not-a-hex-id')->assertNotFound();
        $this->get('/sessions/'.str_repeat('z', 32))->assertNotFound();
    }

    private function operator(): User
    {
        return User::create([
            'name' => 'Operator',
            'email' => 'operator@example.test',
            'password' => 'a-very-long-password',
        ]);
    }
}
