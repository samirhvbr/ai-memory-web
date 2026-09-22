<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `f197` — the search term is bounded, like the other three filtered actions.
 *
 * ## What was measured (2026-09-22)
 *
 * `AiMemoryController` has four actions that read user input. Three of them —
 * `pages`, `sessions`, `handoffs` — call `$request->validate(...)`. `search` did not, and
 * the twin panel in `samirhv-site` **does** (`'q' => ['nullable', 'string', 'max:200']`).
 * The divergence was one-directional: the fix existed on one side of the fork only.
 *
 * ## 🔬 Honest severity: this is consistency, not a vulnerability
 *
 * `SearchRepository::toMatch()` already caps the token COUNT at 10, so a long query never
 * became a long `MATCH` expression — unless it is one long token, which becomes one long
 * quoted prefix term. Measured on this machine:
 *
 * | input | tokens kept | MATCH expression | tokenising regex |
 * |---|---|---|---|
 * | 200 B | 1 | 203 B | 0.09 ms |
 * | 2 MB (one token) | 1 | 2 MB | 2.52 ms |
 * | 100 000 short tokens | 10 | ~70 B | 2.17 ms |
 *
 * Small, and saying so is the point: the value here is that the four actions now answer
 * the same way, so nobody has to remember which one is the exception. Claiming more than
 * the measurement supports is how a finding list stops being trusted.
 */
class SearchTermIsBoundedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Before parent::setUp(), because RefreshDatabase migrates from inside it —
        // a skip declared afterwards would arrive one connection too late.
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required for the app database.');
        }

        parent::setUp();
    }

    private function operador(): User
    {
        return User::factory()->create();
    }

    #[Test]
    public function a_term_within_the_bound_is_accepted(): void
    {
        $this->actingAs($this->operador())
            ->get('/search?q='.urlencode(str_repeat('a', 200)))
            ->assertOk()
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function a_term_over_the_bound_is_refused(): void
    {
        // 🔴 The case. Before this delivery the term went straight to the tokeniser.
        $this->actingAs($this->operador())
            ->get('/search?q='.urlencode(str_repeat('a', 201)))
            ->assertSessionHasErrors('q');
    }

    #[Test]
    public function an_empty_search_still_renders(): void
    {
        // `nullable` matters: the screen is reachable with no term at all, and that is
        // the normal way in — a bound that broke the empty case would be worse than none.
        $this->actingAs($this->operador())->get('/search')->assertOk()->assertSessionHasNoErrors();
        $this->actingAs($this->operador())->get('/search?q=')->assertOk()->assertSessionHasNoErrors();
    }

    #[Test]
    public function the_other_filtered_actions_are_still_validated(): void
    {
        // The reason this file exists is that one of four actions was the exception.
        // Pinning only `search` would let the next one drift the same way unnoticed.
        $u = $this->operador();

        $this->actingAs($u)->get('/handoffs?state=nao-existe')->assertSessionHasErrors('state');
        $this->actingAs($u)->get('/handoffs?state=open')->assertOk()->assertSessionHasNoErrors();
    }
}
