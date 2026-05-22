<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Vérifie que la fonction e() neutralise les attaques XSS
 * avant tout affichage dans le HTML.
 */
class XSSTest extends TestCase
{
    /** @dataProvider xssPayloadProvider */
    public function test_e_neutralizes_xss_payload(string $payload): void
    {
        $result = e($payload);

        // e() neutralise le XSS en encodant < et > — les balises ne peuvent pas s'ouvrir.
        // Les noms d'attributs (onerror=, onload=...) peuvent rester dans la chaîne encodée
        // mais sont inoffensifs sans balise HTML valide autour d'eux.
        $this->assertStringNotContainsString('<script', $result, "Balise <script> non neutralisée");
        $this->assertStringNotContainsString('</script>', $result);

        // Vérifier que les < et > sont bien encodés
        if (str_contains($payload, '<')) {
            $this->assertStringContainsString('&lt;', $result, "Le < devrait être encodé en &lt;");
            $this->assertStringNotContainsString('<', str_replace(['&lt;', '&gt;', '&amp;', '&quot;', '&apos;'], '', $result));
        }
    }

    public static function xssPayloadProvider(): array
    {
        return [
            'script tag'              => ['<script>alert(1)</script>'],
            'script with src'         => ['<script src="http://evil.com/x.js"></script>'],
            'img onerror'             => ['<img src=x onerror=alert(1)>'],
            'svg onload'              => ['<svg onload=alert(1)>'],
            'javascript protocol'     => ['<a href="javascript:alert(1)">click</a>'],
            'double quote injection'  => ['"><script>alert(1)</script>'],
            'single quote injection'  => ["'><script>alert(1)</script>"],
            'html entity bypass'      => ['&lt;script&gt;alert(1)&lt;/script&gt;'],
            'nested tags'             => ['<scr<script>ipt>alert(1)</scr</script>ipt>'],
            'uppercase tag'           => ['<SCRIPT>alert(1)</SCRIPT>'],
            'event handler onclick'   => ['<div onclick="alert(1)">x</div>'],
        ];
    }

    public function test_e_preserves_safe_content(): void
    {
        $this->assertSame('Dupont Jean', e('Dupont Jean'));
        $this->assertSame('12 rue de la Paix, Paris', e('12 rue de la Paix, Paris'));
        $this->assertSame('800,00 €', e('800,00 €'));
    }

    public function test_e_encodes_html_entities(): void
    {
        $this->assertSame('&lt;', e('<'));
        $this->assertSame('&gt;', e('>'));
        $this->assertSame('&amp;', e('&'));
        $this->assertSame('&quot;', e('"'));
        $this->assertSame('&apos;', e("'")); // ENT_HTML5 utilise &apos;
    }
}
