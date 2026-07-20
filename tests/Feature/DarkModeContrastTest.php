<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FIX-02 regression guard (plan 14-01). Proves the four <x-status-pill> palettes each carry a
 * matched light+dark class pair. This does NOT (and cannot) assert page-level visual legibility
 * — that is inherently visual and is covered by Task 3's human-verify checkpoint instead. This
 * test exists purely so a future blanket find/replace across status-pill's match() cannot
 * silently strip the dark: arm off one palette again without a test noticing.
 */
class DarkModeContrastTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string, string, string}>
     */
    public static function paletteProvider(): array
    {
        return [
            'green (enrolled/published/open/available)' => ['published', 'bg-green-100', 'text-green-800', 'dark:bg-green-900', 'dark:text-green-300'],
            'red (rejected/closed)' => ['closed', 'bg-red-100', 'text-red-800', 'dark:bg-red-900', 'dark:text-red-300'],
            'amber (full)' => ['full', 'bg-amber-100', 'text-amber-800', 'dark:bg-amber-900', 'dark:text-amber-300'],
            'gray (withdrawn/opening/opens)' => ['withdrawn', 'bg-gray-100', 'text-gray-800', 'dark:bg-gray-700', 'dark:text-gray-300'],
        ];
    }

    #[DataProvider('paletteProvider')]
    public function test_each_status_pill_palette_carries_a_matched_light_and_dark_pair(
        string $status,
        string $lightBg,
        string $lightText,
        string $darkBg,
        string $darkText
    ): void {
        $rendered = Blade::render('<x-status-pill :status="$status">Label</x-status-pill>', ['status' => $status]);

        $this->assertStringContainsString($lightBg, $rendered);
        $this->assertStringContainsString($lightText, $rendered);
        $this->assertStringContainsString($darkBg, $rendered);
        $this->assertStringContainsString($darkText, $rendered);
    }

    public function test_an_unrecognised_status_falls_back_to_the_gray_palette_with_both_arms(): void
    {
        $rendered = Blade::render('<x-status-pill :status="$status">Label</x-status-pill>', ['status' => 'not-a-real-status']);

        $this->assertStringContainsString('bg-gray-100', $rendered);
        $this->assertStringContainsString('text-gray-800', $rendered);
        $this->assertStringContainsString('dark:bg-gray-700', $rendered);
        $this->assertStringContainsString('dark:text-gray-300', $rendered);
    }
}
