<?php

declare(strict_types=1);

namespace Tests\Unit\Plugins\I18n;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Every locale must define the same keys, with the same placeholders, as the
 * reference locale.
 *
 * WHY THIS TEST EXISTS
 * --------------------
 * Translations rot silently. Someone adds a rule and its English message, the
 * French file is not updated, and French users start seeing the raw key
 * ('validation.uuid') in place of a message. Nothing fails — the Translator
 * deliberately never throws — so the gap survives until a user reports it.
 *
 * Placeholders rot the same way and are worse: a translated line that drops
 * ':max' produces a grammatical sentence with the number missing, which reads
 * as correct and is not.
 *
 * This is the template each plugin shipping a catalogue should copy. It walks
 * whatever locales exist rather than hard-coding them, so adding a third
 * language needs no change here.
 */
#[CoversNothing]
final class CatalogueParityTest extends TestCase
{
    /** The locale every other locale is compared against. */
    private const REFERENCE = 'en';

    private static function langRoot(): string
    {
        return dirname(__DIR__) . '/lang';
    }

    /** @return list<string> */
    private static function locales(): array
    {
        $found = [];
        foreach (glob(self::langRoot() . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $found[] = basename($dir);
        }
        sort($found);

        return $found;
    }

    /** @return array<string,scalar> group.key => line, flattened */
    private static function flatten(array $data, string $prefix = ''): array
    {
        $flat = [];
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $flat += self::flatten($value, $path);
            } else {
                $flat[$path] = $value;
            }
        }

        return $flat;
    }

    /** @return array<string,scalar> */
    private static function catalogue(string $locale): array
    {
        $flat = [];
        foreach (glob(self::langRoot() . '/' . $locale . '/*.php') ?: [] as $file) {
            $data = require $file;
            if (is_array($data)) {
                $flat += self::flatten($data, basename($file, '.php'));
            }
        }

        return $flat;
    }

    /** @return list<string> */
    private static function placeholders(string $line): array
    {
        preg_match_all('/:([A-Za-z][A-Za-z0-9_]*)/', $line, $m);
        $found = array_map('strtolower', $m[1]);
        sort($found);

        return array_values(array_unique($found));
    }

    public function test_the_reference_locale_is_not_empty(): void
    {
        // Guards the test itself: a broken path would make every other
        // assertion below vacuously pass.
        $this->assertNotEmpty(self::catalogue(self::REFERENCE), 'reference catalogue failed to load');
    }

    public function test_french_is_shipped(): void
    {
        $this->assertContains('fr', self::locales());
    }

    public function test_every_locale_defines_every_reference_key(): void
    {
        $reference = self::catalogue(self::REFERENCE);

        foreach (self::locales() as $locale) {
            if ($locale === self::REFERENCE) {
                continue;
            }

            $missing = array_diff(array_keys($reference), array_keys(self::catalogue($locale)));

            $this->assertSame([], array_values($missing), sprintf(
                "[%s] is missing %d key(s) that [%s] defines: %s\n"
                . 'A missing key is served as the raw key, so the user sees "validation.required".',
                $locale,
                count($missing),
                self::REFERENCE,
                implode(', ', $missing),
            ));
        }
    }

    public function test_no_locale_defines_a_key_the_reference_lacks(): void
    {
        // An orphan key is dead weight at best; usually it means the key was
        // renamed in the reference and the translation was left behind.
        $reference = self::catalogue(self::REFERENCE);

        foreach (self::locales() as $locale) {
            if ($locale === self::REFERENCE) {
                continue;
            }

            $extra = array_diff(array_keys(self::catalogue($locale)), array_keys($reference));

            $this->assertSame([], array_values($extra), sprintf(
                '[%s] defines %d key(s) [%s] does not: %s',
                $locale,
                count($extra),
                self::REFERENCE,
                implode(', ', $extra),
            ));
        }
    }

    public function test_every_translation_keeps_the_reference_placeholders(): void
    {
        $reference = self::catalogue(self::REFERENCE);

        foreach (self::locales() as $locale) {
            if ($locale === self::REFERENCE) {
                continue;
            }

            $translated = self::catalogue($locale);

            foreach ($reference as $key => $line) {
                if (!isset($translated[$key])) {
                    continue; // reported by the missing-keys test
                }

                $expected = self::placeholders((string) $line);
                $actual   = self::placeholders((string) $translated[$key]);

                $this->assertSame([], array_values(array_diff($expected, $actual)), sprintf(
                    '[%s] "%s" drops placeholder(s) present in [%s]. Reference: "%s" / translated: "%s". '
                    . 'The sentence still reads correctly with the value missing, which is why this is easy to miss.',
                    $locale,
                    $key,
                    self::REFERENCE,
                    $line,
                    $translated[$key],
                ));

                $this->assertSame([], array_values(array_diff($actual, $expected)), sprintf(
                    '[%s] "%s" introduces placeholder(s) the Validator never supplies: %s. '
                    . 'They would be rendered literally.',
                    $locale,
                    $key,
                    implode(', ', array_diff($actual, $expected)),
                ));
            }
        }
    }
}
