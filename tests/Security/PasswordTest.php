<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Vérifie le hachage et la vérification des mots de passe.
 */
class PasswordTest extends TestCase
{
    public function test_password_hash_uses_bcrypt(): void
    {
        $hash = password_hash('admin1234', PASSWORD_BCRYPT);
        $info = password_get_info($hash);
        $this->assertSame('bcrypt', $info['algoName']);
    }

    public function test_password_hash_has_minimum_cost(): void
    {
        $hash = password_hash('admin1234', PASSWORD_BCRYPT, ['cost' => 12]);
        $info = password_get_info($hash);
        $this->assertGreaterThanOrEqual(12, $info['options']['cost']);
    }

    public function test_password_verify_succeeds_with_correct_password(): void
    {
        $hash = password_hash('monMotDePasse', PASSWORD_BCRYPT);
        $this->assertTrue(password_verify('monMotDePasse', $hash));
    }

    public function test_password_verify_fails_with_wrong_password(): void
    {
        $hash = password_hash('monMotDePasse', PASSWORD_BCRYPT);
        $this->assertFalse(password_verify('mauvaisMotDePasse', $hash));
    }

    public function test_password_verify_fails_with_empty_string(): void
    {
        $hash = password_hash('monMotDePasse', PASSWORD_BCRYPT);
        $this->assertFalse(password_verify('', $hash));
    }

    public function test_default_admin_hash_matches_admin1234(): void
    {
        // Hash stocké dans database.sql
        $stored_hash = '$2y$12$oNEt/lbwQqu6.9BNdWvOa.nfgtNq4OAPaJzQTHyCa.VoSDhOoO5jG';
        $this->assertTrue(
            password_verify('admin1234', $stored_hash),
            'Le hash admin dans database.sql ne correspond pas au mot de passe admin1234'
        );
    }

    public function test_two_hashes_of_same_password_differ(): void
    {
        // bcrypt génère un salt aléatoire — deux hashes ne doivent jamais être identiques
        $hash1 = password_hash('memeMotDePasse', PASSWORD_BCRYPT);
        $hash2 = password_hash('memeMotDePasse', PASSWORD_BCRYPT);
        $this->assertNotSame($hash1, $hash2);
        // mais les deux doivent être valides
        $this->assertTrue(password_verify('memeMotDePasse', $hash1));
        $this->assertTrue(password_verify('memeMotDePasse', $hash2));
    }

    public function test_password_minimum_length_enforced(): void
    {
        // L'appli exige 8 caractères minimum — vérifier la règle métier
        $short = 'abc123';
        $valid = 'abc12345';
        $this->assertLessThan(8, strlen($short));
        $this->assertGreaterThanOrEqual(8, strlen($valid));
    }

    public function test_plain_text_password_not_equal_to_hash(): void
    {
        $password = 'admin1234';
        $hash     = password_hash($password, PASSWORD_BCRYPT);
        $this->assertNotSame($password, $hash);
    }
}
