<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\SeedsAiMemoryIndex;
use Tests\TestCase;

/**
 * Every screen, rendered against a seeded ai-memory index.
 *
 * PanelAccessTest already covers the two states that need no data: a guest is
 * turned away, and an unreachable index degrades to the notice. What was never
 * covered is the state the panel actually exists for — an index with rows in
 * it. Each test below asserts a string that could only have come from the
 * fixture, so a screen that renders its chrome and drops its content fails.
 *
 * The five detail screens carry the cases that break on empty data: a page with
 * a version history, a session with observations, a session with no end, a
 * handoff whose JSON arrays are populated, and a project with counts.
 */
class AiMemoryScreensTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAiMemoryIndex;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required to build the ai-memory fixture.');
        }

        parent::setUp();

        $this->seedAiMemoryIndex();
        $this->actingAs($this->operator());
    }

    protected function tearDown(): void
    {
        $this->cleanUpAiMemoryIndex();

        parent::tearDown();
    }

    // ── The eight listings ─────────────────────────────────────────────────

    public function test_the_dashboard_ranks_the_seeded_projects(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('ai-memory is not reachable on this host')
            ->assertSee('fixture-alpha');
    }

    public function test_the_projects_screen_lists_projects_with_their_repo_path(): void
    {
        $this->get('/projects')
            ->assertOk()
            ->assertSee('fixture-alpha')
            ->assertSee('fixture-beta')
            ->assertSee('/srv/fixture/alpha');
    }

    public function test_the_workspaces_screen_lists_both_workspaces(): void
    {
        $this->get('/workspaces')
            ->assertOk()
            ->assertSee('fixture-default')
            ->assertSee('fixture-practice');
    }

    public function test_the_pages_screen_lists_current_versions_pinned_first(): void
    {
        $this->get('/pages')
            ->assertOk()
            ->assertSee('Fixture Pinned Page')
            ->assertSee('notes/alpha-pinned.md')
            // Superseded versions are not current, so they must not be listed.
            ->assertDontSee('Fixture History Page v1');
    }

    public function test_the_sessions_screen_lists_both_agent_kinds(): void
    {
        $this->get('/sessions')
            ->assertOk()
            ->assertSee('claude-code')
            ->assertSee('codex')
            ->assertSee('/srv/fixture/alpha');
    }

    public function test_the_observations_screen_lists_seeded_facts(): void
    {
        $this->get('/observations')
            ->assertOk()
            ->assertSee('Fixture Observation One')
            ->assertSee('decision');
    }

    public function test_the_handoffs_screen_lists_the_batons(): void
    {
        $this->get('/handoffs')
            ->assertOk()
            ->assertSee('claude-code')
            ->assertSee('open');
    }

    public function test_the_search_screen_finds_a_word_from_a_page_body(): void
    {
        // `zorbulax` exists only in the current version of the history page, so
        // a hit proves the FTS join and the is_latest filter both work.
        $this->get('/search?q=zorbulax')
            ->assertOk()
            ->assertSee('Fixture History Page v3');
    }

    public function test_search_reports_no_result_without_failing(): void
    {
        $this->get('/search?q=nothingmatchesthisterm')
            ->assertOk()
            ->assertSee('No result for');
    }

    // ── The five detail screens ────────────────────────────────────────────

    public function test_the_project_screen_shows_the_project_and_its_workspace(): void
    {
        $this->get('/projects/'.$this->projectAlphaHex())
            ->assertOk()
            ->assertSee('fixture-alpha')
            ->assertSee('fixture-default')
            ->assertSee('Fixture Pinned Page');
    }

    public function test_the_page_screen_renders_the_body_and_the_whole_version_history(): void
    {
        // Three rows share (workspace, project, path); the history list is what
        // an empty fixture can never exercise.
        $this->get('/pages/'.$this->pageCurrentHex())
            ->assertOk()
            ->assertSee('Fixture History Page v3')
            ->assertSee('zorbulax')
            ->assertSee('3 versions');
    }

    public function test_an_older_page_version_says_it_is_not_the_current_one(): void
    {
        $this->get('/pages/'.$this->pageOldestHex())
            ->assertOk()
            ->assertSee('Fixture History Page v1')
            ->assertSee('You are reading an older version of this page', false);
    }

    public function test_a_page_with_an_empty_body_says_so_instead_of_rendering_nothing(): void
    {
        $this->get('/pages/'.$this->pageEmptyBodyHex())
            ->assertOk()
            ->assertSee('Fixture Empty Body Page')
            ->assertSee('This page has no body');
    }

    public function test_the_session_screen_lists_the_observations_of_that_session(): void
    {
        $this->get('/sessions/'.$this->sessionClosedHex())
            ->assertOk()
            ->assertSee('fixture-alpha')
            ->assertSee('Fixture Observation One')
            ->assertSee('Fixture Observation Five');
    }

    public function test_a_session_with_no_end_still_renders(): void
    {
        $this->get('/sessions/'.$this->sessionOpenHex())
            ->assertOk()
            ->assertSee('fixture-beta')
            ->assertSee('Fixture Observation Nine');
    }

    public function test_the_observation_screen_shows_the_fact_and_its_agent(): void
    {
        $this->get('/observations/'.$this->observationHex())
            ->assertOk()
            ->assertSee('Fixture Observation One')
            ->assertSee('The body of the first fixture observation.')
            ->assertSee('claude-code');
    }

    public function test_the_handoff_screen_decodes_its_json_arrays(): void
    {
        // open_questions / next_steps / files_touched are JSON columns the
        // listing only counts and the detail screen decodes.
        $this->get('/handoffs/'.$this->handoffOpenHex())
            ->assertOk()
            ->assertSee('Fixture handoff summary for the open baton.')
            ->assertSee('Fixture open question one')
            ->assertSee('Fixture next step three')
            ->assertSee('src/fixture/two.php');
    }

    public function test_an_accepted_handoff_shows_who_took_the_baton(): void
    {
        $this->get('/handoffs/'.$this->handoffAcceptedHex())
            ->assertOk()
            ->assertSee('accepted')
            ->assertSee('fixture-claude-code');
    }

    // ── The JSON endpoint behind the dashboard's live mode ─────────────────

    public function test_the_live_endpoint_answers_with_the_seeded_totals(): void
    {
        $this->getJson('/live')
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('counts.workspaces', 2)
            ->assertJsonPath('counts.projects', 3)
            ->assertJsonPath('counts.pages', 6)
            ->assertJsonPath('counts.sessions', 4)
            ->assertJsonPath('counts.observations', 12)
            ->assertJsonPath('counts.embeddings', 2)
            ->assertJsonPath('counts.handoffs_open', 1)
            ->assertJsonPath('counts.proposals_pending', 1);
    }

    // ── Coverage guard ─────────────────────────────────────────────────────

    public function test_there_are_thirteen_screens_and_this_file_knows_it(): void
    {
        // The README said "Nine screens" long after there were thirteen, and
        // the queue inherited the number. If a screen is added, this fails and
        // whoever adds it has to come back here and cover it.
        $screens = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'ai-memory.'))
            ->filter(fn ($route) => in_array('GET', $route->methods(), true))
            ->reject(fn ($route) => $route->getName() === 'ai-memory.live')
            ->count();

        $this->assertSame(13, $screens, 'The panel has 13 HTML screens (8 listings + 5 detail).');
    }

    private function operator(): User
    {
        return User::create([
            'name' => 'Operator',
            'email' => 'screens-operator@example.test',
            'password' => 'a-very-long-password',
        ]);
    }
}
