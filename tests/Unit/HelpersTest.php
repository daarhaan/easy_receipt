<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    // ── e() ───────────────────────────────────────────────────────────────────

    public function test_e_escapes_html_special_chars(): void
    {
        $this->assertSame('&lt;b&gt;bold&lt;/b&gt;', e('<b>bold</b>'));
    }

    public function test_e_escapes_quotes(): void
    {
        $this->assertSame('&quot;quoted&quot;', e('"quoted"'));
        // ENT_HTML5 encode les guillemets simples en &apos;
        $this->assertSame('&apos;single&apos;', e("'single'"));
    }

    public function test_e_handles_empty_string(): void
    {
        $this->assertSame('', e(''));
    }

    public function test_e_handles_normal_string(): void
    {
        $this->assertSame('Hello World', e('Hello World'));
    }

    // ── money() ───────────────────────────────────────────────────────────────

    public function test_money_formats_integer(): void
    {
        $this->assertSame('800,00 €', money(800.0));
    }

    public function test_money_formats_decimal(): void
    {
        $this->assertSame('1 250,50 €', money(1250.50));
    }

    public function test_money_formats_zero(): void
    {
        $this->assertSame('0,00 €', money(0.0));
    }

    public function test_money_formats_large_amount(): void
    {
        $result = money(10000.00);
        $this->assertStringContainsString('€', $result);
        $this->assertStringContainsString('10', $result);
    }

    // ── french_month() ────────────────────────────────────────────────────────

    public function test_french_month_january(): void
    {
        $this->assertSame('janvier 2024', french_month(1, 2024));
    }

    public function test_french_month_december(): void
    {
        $this->assertSame('décembre 2024', french_month(12, 2024));
    }

    public function test_french_month_all_months(): void
    {
        $expected = [
            1  => 'janvier',  2  => 'février',   3  => 'mars',
            4  => 'avril',    5  => 'mai',        6  => 'juin',
            7  => 'juillet',  8  => 'août',       9  => 'septembre',
            10 => 'octobre',  11 => 'novembre',   12 => 'décembre',
        ];
        foreach ($expected as $n => $name) {
            $this->assertStringStartsWith($name, french_month($n, 2024));
        }
    }

    public function test_french_month_invalid_returns_question_mark(): void
    {
        $this->assertStringStartsWith('?', french_month(13, 2024));
    }

    // ── french_date() ─────────────────────────────────────────────────────────

    public function test_french_date_formats_correctly(): void
    {
        $this->assertSame('15/06/2024', french_date('2024-06-15'));
    }

    public function test_french_date_formats_first_of_month(): void
    {
        $this->assertSame('01/01/2024', french_date('2024-01-01'));
    }

    public function test_french_date_returns_original_on_invalid(): void
    {
        $this->assertSame('not-a-date', french_date('not-a-date'));
    }
}
