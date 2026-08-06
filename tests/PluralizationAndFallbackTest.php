<?php

declare(strict_types=1);

namespace Tests\Unit\Plugins\I18n;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Plugins\I18n\Translator;

/**
 * Pluralization and fallback-locale behaviour (I-03).
 *
 * These use a purpose-built catalogue rather than the plugin's shipped lang
 * files: the shipped files carry validation copy with no plural lines and only
 * one locale, so they cannot exercise either mechanism. Building the fixture
 * here also keeps the assertions readable — the expected output sits next to
 * the line that produced it.
 */
#[CoversClass(Translator::class)]
final class PluralizationAndFallbackTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/hkm-i18n-' . bin2hex(random_bytes(6));

        $this->writeCatalogue('en', 'cart', [
            'apples'  => 'apple|apples',
            'items'   => '{0} No items|[1,19] :count item(s) in :cart|[20,*] lots of items',
            'only_en' => 'English only.',
            'shared'  => 'English shared.',
        ]);

        // Deliberately missing 'only_en' so the fallback path is exercised.
        $this->writeCatalogue('fr', 'cart', [
            'apples' => 'pomme|pommes',
            'shared' => 'Partagé en français.',
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*/*.php') ?: [] as $file) {
            unlink($file);
        }
        foreach (glob($this->dir . '/*') ?: [] as $sub) {
            is_dir($sub) && rmdir($sub);
        }
        is_dir($this->dir) && rmdir($this->dir);
    }

    /** @param array<string,string> $lines */
    private function writeCatalogue(string $locale, string $group, array $lines): void
    {
        @mkdir($this->dir . '/' . $locale, 0777, true);
        file_put_contents(
            $this->dir . '/' . $locale . '/' . $group . '.php',
            '<?php return ' . var_export($lines, true) . ';',
        );
    }

    private function translator(string $locale = 'en', string $fallback = 'en'): Translator
    {
        return new Translator($this->dir, $locale, $fallback);
    }

    // --- Simple singular|plural ----------------------------------------------

    public function test_simple_form_selects_singular_only_for_exactly_one(): void
    {
        $t = $this->translator();

        $this->assertSame('apple', $t->choice('cart.apples', 1));
        $this->assertSame('apples', $t->choice('cart.apples', 2));
        $this->assertSame('apples', $t->choice('cart.apples', 0), 'zero takes the plural form');
    }

    /**
     * A negative count is not a documented input, but it must not fall through
     * to the raw "apple|apples" line — a user-visible pipe character is worse
     * than picking the wrong form.
     */
    public function test_a_negative_count_still_yields_one_form(): void
    {
        $this->assertStringNotContainsString('|', $this->translator()->choice('cart.apples', -1));
    }

    // --- Explicit ranges ------------------------------------------------------

    public function test_exact_zero_prefix_wins_over_the_ranges(): void
    {
        $this->assertSame('No items', $this->translator()->choice('cart.items', 0));
    }

    public function test_a_count_inside_a_range_selects_that_range(): void
    {
        $this->assertSame(
            '3 item(s) in bag',
            $this->translator()->choice('cart.items', 3, ['cart' => 'bag']),
        );
    }

    public function test_range_boundaries_are_inclusive(): void
    {
        $t = $this->translator();

        $this->assertSame('1 item(s) in bag', $t->choice('cart.items', 1, ['cart' => 'bag']));
        $this->assertSame('19 item(s) in bag', $t->choice('cart.items', 19, ['cart' => 'bag']));
        $this->assertSame('lots of items', $t->choice('cart.items', 20), 'the [20,*] range starts at 20');
    }

    public function test_star_upper_bound_is_unbounded(): void
    {
        $this->assertSame('lots of items', $this->translator()->choice('cart.items', 999_999));
    }

    public function test_count_is_supplied_automatically(): void
    {
        // :count is injected without the caller passing it.
        $this->assertSame('7 item(s) in bag', $this->translator()->choice('cart.items', 7, ['cart' => 'bag']));
    }

    public function test_choice_pluralizes_in_the_active_locale(): void
    {
        $this->assertSame('pommes', $this->translator('fr')->choice('cart.apples', 4));
    }

    public function test_a_missing_plural_key_returns_the_key_not_an_empty_string(): void
    {
        $this->assertSame('cart.nope', $this->translator()->choice('cart.nope', 2));
    }

    // --- Fallback locale ------------------------------------------------------

    public function test_a_key_missing_in_the_active_locale_falls_back(): void
    {
        $this->assertSame('English only.', $this->translator('fr', 'en')->get('cart.only_en'));
    }

    public function test_the_active_locale_wins_when_it_defines_the_key(): void
    {
        // The fallback must not shadow a translation that genuinely exists.
        $this->assertSame('Partagé en français.', $this->translator('fr', 'en')->get('cart.shared'));
    }

    public function test_has_reports_the_active_locale_only_and_ignores_the_fallback(): void
    {
        $t = $this->translator('fr', 'en');

        // get() resolves it through the fallback, but has() answers the narrower
        // question "does French actually define this?" — which is what a caller
        // deciding whether to show an untranslated string needs to know.
        $this->assertFalse($t->has('cart.only_en'));
        $this->assertSame('English only.', $t->get('cart.only_en'));
    }

    public function test_a_key_missing_everywhere_returns_the_key(): void
    {
        $this->assertSame('cart.absent', $this->translator('fr', 'en')->get('cart.absent'));
    }

    public function test_an_explicit_locale_argument_overrides_the_active_one(): void
    {
        $t = $this->translator('en', 'en');

        $this->assertSame('Partagé en français.', $t->get('cart.shared', [], 'fr'));
        $this->assertSame('en', $t->locale(), 'a per-call locale must not mutate the active one');
    }

    public function test_set_locale_switches_subsequent_lookups(): void
    {
        $t = $this->translator('en', 'en');
        $t->setLocale('fr');

        $this->assertSame('fr', $t->locale());
        $this->assertSame('Partagé en français.', $t->get('cart.shared'));
    }

    // --- Placeholder substitution --------------------------------------------

    /**
     * Replacement keys are applied longest-first. With naive per-key replacement,
     * ':min' would rewrite the ':min' prefix inside ':minutes' and corrupt it —
     * the kind of bug that only shows up for the one message that happens to use
     * both placeholders.
     */
    public function test_a_shorter_key_cannot_corrupt_a_longer_one(): void
    {
        $this->writeCatalogue('en', 'timer', ['wait' => 'Wait :minutes minutes (min :min).']);

        $this->assertSame(
            'Wait 30 minutes (min 5).',
            $this->translator()->get('timer.wait', ['min' => 5, 'minutes' => 30]),
        );
    }
}
