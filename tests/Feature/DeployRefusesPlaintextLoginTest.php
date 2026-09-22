<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `f196` — the deploy refuses a login form served over plaintext HTTP.
 *
 * ## What was measured (2026-09-22)
 *
 * `tools/deploy_mem.sh` smoke-tests the login page after reloading PHP-FPM. When no TLS
 * vhost exists it asked `:80` instead, and the `200` branch **printed a warning and fell
 * through**:
 *
 *     200) echo "login page: 200 — but this is PLAINTEXT HTTP and the panel has a"
 *          echo "            login form. Get the certificate in place." ;;
 *
 * Only the `*)` branch called `die`. With `set -euo pipefail` a fall-through is still
 * exit 0, so the script printed `Done.` — a successful deploy — right after publishing a
 * login form in the clear.
 *
 * ⚠️ **And that is the ordinary case, not the edge case.** On a host whose `:80` vhost
 * serves the app, which is the Apache default almost everywhere, 200 is exactly what the
 * probe gets. The 403 the comment describes depends on a deliberately closed `:80`.
 *
 * The operator's password is this panel's entire perimeter: it renders, in plain text,
 * everything the agents remember about every project on the host.
 *
 * ## Why this test SHELLS OUT instead of grepping the script
 *
 * A ruler that greps for the word `die` passes the moment someone writes `die` in a
 * comment, and fails the moment someone renames the function. This one **runs** the
 * verdict with the real interpreter and reads the real exit code, which is the only thing
 * the deploy actually depends on.
 */
class DeployRefusesPlaintextLoginTest extends TestCase
{
    /** Runs `smoke_verdict <code>` in the real script, with the real shell. */
    private function verdict(string $code, array $env = []): array
    {
        $script = dirname(__DIR__, 2).'/tools/deploy_mem.sh';
        $this->assertFileExists($script);

        $prefixo = 'AIMWEB_SOURCE_ONLY=1';
        foreach ($env as $k => $v) {
            $prefixo .= ' '.$k.'='.escapeshellarg((string) $v);
        }

        $cmd = sprintf(
            '%s bash -c %s 2>&1',
            $prefixo,
            escapeshellarg('source '.$script.'; smoke_verdict '.escapeshellarg($code)),
        );

        $saida = [];
        $rc = 0;
        exec($cmd, $saida, $rc);

        return ['rc' => $rc, 'out' => implode("\n", $saida)];
    }

    #[Test]
    public function plaintext_200_FAILS_the_deploy(): void
    {
        // 🔴 The case itself. Before 2026-09-22 this exited 0 with a warning.
        $r = $this->verdict('200');

        $this->assertNotSame(0, $r['rc'], "a 200 over plaintext HTTP must fail the deploy:\n".$r['out']);
        $this->assertStringContainsStringIgnoringCase('plaintext', $r['out']);
    }

    #[Test]
    public function the_refusal_names_its_own_escape_hatch(): void
    {
        // A refusal that does not say how to proceed gets worked around by
        // commenting out the check, which is worse than never having had it.
        $this->assertStringContainsString('AIMWEB_ALLOW_PLAINTEXT=1', $this->verdict('200')['out']);
    }

    #[Test]
    public function the_escape_hatch_lets_a_deliberate_operator_through(): void
    {
        $r = $this->verdict('200', ['AIMWEB_ALLOW_PLAINTEXT' => '1']);

        $this->assertSame(0, $r['rc'], $r['out']);
        $this->assertStringContainsStringIgnoringCase('deliberate exception', $r['out']);
    }

    #[Test]
    public function a_closed_port_80_still_passes(): void
    {
        // The 403 tolerance is the one this script was written around: no DNS yet,
        // so no certificate yet, so :80 is shut on purpose. It must keep passing.
        $r = $this->verdict('403');

        $this->assertSame(0, $r['rc'], $r['out']);
        $this->assertStringContainsString('closed on purpose', $r['out']);
    }

    #[DataProvider('codigosQueNaoSaoRespostaDeLogin')]
    #[Test]
    public function anything_else_still_fails(string $code): void
    {
        $this->assertNotSame(0, $this->verdict($code)['rc'], "code $code should fail");
    }

    public static function codigosQueNaoSaoRespostaDeLogin(): array
    {
        // `000` is what curl reports when it could not connect at all — the case a
        // `|| true` in the caller turns into an empty string and a silent pass.
        return [['000'], ['301'], ['404'], ['500'], ['502']];
    }
}
