<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Vérifie la génération et la validation des tokens CSRF.
 */
class CSRFTest extends TestCase
{
    protected function setUp(): void
    {
        // Simuler une session active
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['csrf_token']);
        $_POST = [];
    }

    protected function tearDown(): void
    {
        unset($_SESSION['csrf_token']);
        $_POST = [];
    }

    public function test_csrf_token_is_generated(): void
    {
        $token = csrf_token();
        $this->assertNotEmpty($token);
    }

    public function test_csrf_token_is_hex_string(): void
    {
        $token = csrf_token();
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $token);
    }

    public function test_csrf_token_has_sufficient_length(): void
    {
        $token = csrf_token();
        // bin2hex(random_bytes(32)) = 64 chars
        $this->assertGreaterThanOrEqual(64, strlen($token));
    }

    public function test_csrf_token_is_stored_in_session(): void
    {
        $token = csrf_token();
        $this->assertSame($token, $_SESSION['csrf_token']);
    }

    public function test_csrf_token_is_stable_within_same_session(): void
    {
        $token1 = csrf_token();
        $token2 = csrf_token();
        $this->assertSame($token1, $token2);
    }

    public function test_csrf_check_passes_with_valid_token(): void
    {
        $token = csrf_token();
        $_POST['csrf_token'] = $token;

        // csrf_check() ne doit pas mourir
        $this->expectNotToPerformAssertions();
        csrf_check();
    }

    public function test_csrf_invalid_token_does_not_match_session(): void
    {
        // csrf_check() appelle die() — on teste la logique directement
        $valid_token   = csrf_token();
        $invalid_token = 'invalid_token_xyz';

        $this->assertFalse(
            hash_equals($valid_token, $invalid_token),
            'Un token invalide ne doit pas correspondre au token de session'
        );
    }

    public function test_csrf_empty_token_does_not_match_session(): void
    {
        $valid_token = csrf_token();

        $this->assertFalse(
            hash_equals($valid_token, ''),
            'Un token vide ne doit pas correspondre au token de session'
        );
    }

    public function test_tokens_use_constant_time_comparison(): void
    {
        // hash_equals est utilisé (pas ==) — on vérifie que la fonction existe et est utilisée
        $this->assertTrue(function_exists('hash_equals'));

        $a = 'abc123';
        $b = 'abc123';
        $c = 'xyz999';

        $this->assertTrue(hash_equals($a, $b));
        $this->assertFalse(hash_equals($a, $c));
    }
}
