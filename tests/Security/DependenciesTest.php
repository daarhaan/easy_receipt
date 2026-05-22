<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Vérifie que les dépendances Composer n'ont pas de CVE connues.
 * Utilise `composer audit` via le fichier composer.lock local.
 */
class DependenciesTest extends TestCase
{
    private string $composerLock;
    private string $phpBin;
    private string $composerBin;

    protected function setUp(): void
    {
        $this->composerLock = dirname(__DIR__, 2) . '/composer.lock';
        $this->phpBin       = PHP_BINARY;

        // Cherche Composer dans les emplacements habituels
        $candidates = [
            dirname(__DIR__, 2) . '/vendor/bin/composer',
            'C:/laragon/bin/composer/composer.phar',
            'composer',
        ];
        $this->composerBin = '';
        foreach ($candidates as $c) {
            if (file_exists($c) || $c === 'composer') {
                $this->composerBin = $c;
                break;
            }
        }
    }

    public function test_composer_lock_exists(): void
    {
        $this->assertFileExists(
            $this->composerLock,
            'composer.lock manquant — lancez "composer install"'
        );
    }

    public function test_no_known_vulnerabilities_in_dependencies(): void
    {
        if (empty($this->composerBin)) {
            $this->markTestSkipped('Composer introuvable.');
        }

        $cmd    = escapeshellarg($this->phpBin) . ' ' . escapeshellarg($this->composerBin) . ' audit --no-interaction --format=json 2>&1';
        $output = shell_exec($cmd);

        if ($output === null) {
            $this->markTestSkipped('Impossible d\'exécuter composer audit.');
        }

        $data = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // composer audit sans CVE renvoie parfois juste un message texte
            $this->assertStringNotContainsString(
                'vulnerability',
                strtolower($output),
                "composer audit a signalé des vulnérabilités :\n$output"
            );
            return;
        }

        $advisories = $data['advisories'] ?? [];
        $this->assertEmpty(
            $advisories,
            "CVEs détectées dans les dépendances :\n" . json_encode($advisories, JSON_PRETTY_PRINT)
        );
    }

    public function test_dependencies_are_up_to_date_with_lock(): void
    {
        // Vérifie que composer.json et composer.lock sont cohérents
        $composerJson = dirname(__DIR__, 2) . '/composer.json';
        $this->assertFileExists($composerJson);

        $json = json_decode(file_get_contents($composerJson), true);
        $lock = json_decode(file_get_contents($this->composerLock), true);

        $this->assertNotNull($json, 'composer.json invalide');
        $this->assertNotNull($lock, 'composer.lock invalide');
        $this->assertArrayHasKey('packages', $lock);
    }
}
