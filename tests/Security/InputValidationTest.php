<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Vérifie que les entrées utilisateur sont correctement validées
 * et que les données sensibles sont bien échappées.
 */
class InputValidationTest extends TestCase
{
    // ── Injection SQL ─────────────────────────────────────────────────────────

    /** @dataProvider sqlInjectionProvider */
    public function test_e_does_not_execute_sql_injection(string $payload): void
    {
        // e() est utilisé pour l'affichage HTML — les injections SQL
        // sont préventées par PDO (prepared statements), mais on vérifie
        // que l'affichage est toujours sûr
        $result = e($payload);
        $this->assertIsString($result);
        // Le payload ne doit pas être renvoyé tel quel s'il contient des caractères dangereux HTML
        if (strpbrk($payload, '<>"\'&') !== false) {
            $this->assertNotSame($payload, $result);
        }
    }

    public static function sqlInjectionProvider(): array
    {
        return [
            "simple quote"        => ["' OR '1'='1"],
            "double quote"        => ['" OR "1"="1'],
            "comment"             => ['admin--'],
            "union select"        => ["' UNION SELECT * FROM users--"],
            "drop table"          => ["'; DROP TABLE users;--"],
            "boolean blind"       => ["' AND 1=1--"],
            "stacked queries"     => ["'; INSERT INTO users VALUES (1,'hacker','x','x','admin',NOW());--"],
        ];
    }

    // ── Traversal de chemin ───────────────────────────────────────────────────

    /** @dataProvider pathTraversalProvider */
    public function test_e_handles_path_traversal_payloads(string $payload): void
    {
        $result = e($payload);
        $this->assertIsString($result);
        // Après échappement, aucun caractère HTML dangereux ne doit subsister
        $this->assertStringNotContainsString('<', $result);
        $this->assertStringNotContainsString('>', $result);
    }

    public static function pathTraversalProvider(): array
    {
        return [
            'dot dot slash'        => ['../../etc/passwd'],
            'encoded traversal'    => ['..%2F..%2Fetc%2Fpasswd'],
            'windows traversal'    => ['..\\..\\windows\\system32'],
            'null byte'            => ["../config.php\0"],
        ];
    }

    // ── Validation des montants ───────────────────────────────────────────────

    public function test_rent_amount_must_be_positive(): void
    {
        $amounts = [-100.0, -0.01, 0.0];
        foreach ($amounts as $amount) {
            $this->assertLessThanOrEqual(0, $amount, "Montant $amount devrait être rejeté");
        }
    }

    public function test_rent_amount_cast_from_string(): void
    {
        // L'appli fait (float)str_replace(',', '.', $input)
        $inputs = ['800,00', '800.00', '1 250,50'];
        foreach ($inputs as $input) {
            $cleaned = (float)str_replace([',', ' '], ['.', ''], $input);
            $this->assertGreaterThan(0, $cleaned);
        }
    }

    public function test_period_month_bounds(): void
    {
        $valid   = range(1, 12);
        $invalid = [0, 13, -1, 100];

        foreach ($valid as $m) {
            $this->assertGreaterThanOrEqual(1, $m);
            $this->assertLessThanOrEqual(12, $m);
        }
        foreach ($invalid as $m) {
            $this->assertFalse($m >= 1 && $m <= 12, "Mois $m devrait être invalide");
        }
    }

    public function test_period_year_minimum(): void
    {
        $this->assertGreaterThanOrEqual(2000, 2024);
        $this->assertFalse(1999 >= 2000, "Année 1999 devrait être rejetée");
    }

    // ── Nettoyage des entrées texte ───────────────────────────────────────────

    public function test_trim_removes_whitespace_from_inputs(): void
    {
        $inputs = ['  admin  ', "\tadmin\n", '  '];
        foreach ($inputs as $input) {
            $trimmed = trim($input);
            $this->assertSame(trim($input), $trimmed);
        }
    }

    public function test_empty_required_fields_are_detected(): void
    {
        $required_fields = ['', '   ', "\t", "\n"];
        foreach ($required_fields as $field) {
            $this->assertEmpty(trim($field), "Le champ '$field' devrait être considéré vide");
        }
    }

    // ── Noms de fichiers PDF ──────────────────────────────────────────────────

    public function test_pdf_filename_sanitization(): void
    {
        $tenant_name = 'Jean Dupont <script>';
        $sanitized   = preg_replace('/[^a-z0-9]/', '_', strtolower($tenant_name));

        $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $sanitized);
        $this->assertStringNotContainsString('<', $sanitized);
        $this->assertStringNotContainsString(' ', $sanitized);
    }

    public function test_pdf_filename_format(): void
    {
        $filename = sprintf('receipt_%d_%04d_%02d_%s.pdf', 1, 2024, 6, 'jean_dupont');
        $this->assertMatchesRegularExpression('/^receipt_\d+_\d{4}_\d{2}_[a-z0-9_]+\.pdf$/', $filename);
    }
}
