<?php

declare(strict_types=1);

namespace Plugins\I18n;

/**
 * File-based translator over a project-first catalogue cascade.
 *
 * Lang files live at {dir}/{locale}/{group}.php and return a nested array:
 *
 *   // lang/en/validation.php
 *   return ['required' => 'The :field field is required.', ...];
 *
 * Lookups use "group.key" dotted notation and :placeholder substitution:
 *
 *   $t->get('validation.required', ['field' => 'email']);
 *
 * Missing keys fall back to the configured fallback locale, then to the key
 * itself — translation never throws.
 *
 * THE CASCADE
 * -----------
 * This used to hold a SINGLE directory, which meant the only catalogue that
 * could ever load was the one this plugin ships. Plugins had nowhere to put
 * their own messages, so every user-facing string outside that one file was
 * hard-coded in whatever language it was written in. Worse, APP_LANG_PATH
 * REPLACED the directory, so a project that pointed it at its own catalogue
 * silently lost the plugin's.
 *
 * Sources are now an ordered list compiled at boot (lang-manifest.php), lowest
 * priority last: project paths first, plugin paths after. A project therefore
 * overrides a plugin's wording by default, and can do it without forking.
 *
 * GROUPS MERGE — THEY DO NOT SHADOW
 * ---------------------------------
 * When several sources define the same group, their arrays are merged with the
 * higher-priority source winning per key. Taking the first hit wholesale would
 * mean overriding one key in a forty-key group required copying the other
 * thirty-nine — and the copy would then silently miss every key the plugin
 * added later. Merging keeps an override to exactly what it overrides.
 *
 * NAMESPACES
 * ----------
 * 'user::profile.title' targets the `user` catalogue specifically, so two
 * plugins can both define 'profile.title' without colliding. A project can
 * still override a namespaced key by placing {project-lang}/user/{locale}/
 * profile.php — checked BEFORE the plugin's own directory, mirroring how
 * namespaced views resolve.
 */
final class Translator
{
    /** @var array<string,array<string,mixed>> loaded [locale => cacheKey => data] */
    private array $loaded = [];

    /** @var list<string> Global catalogue roots, highest priority first. */
    private readonly array $paths;

    /** @var array<string,list<string>> Namespace => roots, highest priority first. */
    private readonly array $namespaces;

    /**
     * @param string|list<string>      $directory  One root, or the compiled cascade.
     * @param array<string,list<string>> $namespaces Namespaced roots from lang-manifest.php.
     */
    public function __construct(
        string|array $directory,
        private string $locale = 'en',
        private readonly string $fallback = 'en',
        array $namespaces = [],
    ) {
        // A bare string stays valid: most call sites (and every test that only
        // cares about lookup behaviour) have one directory and no cascade.
        $this->paths = array_values(array_filter(
            is_string($directory) ? [$directory] : $directory,
            static fn(mixed $p): bool => is_string($p) && $p !== '',
        ));

        $clean = [];
        foreach ($namespaces as $ns => $dirs) {
            $dirs = array_values(array_filter(
                is_string($dirs) ? [$dirs] : (is_array($dirs) ? $dirs : []),
                static fn(mixed $p): bool => is_string($p) && $p !== '',
            ));
            if ($dirs !== []) {
                $clean[(string) $ns] = $dirs;
            }
        }
        $this->namespaces = $clean;
    }

    /**
     * Build a Translator from the boot-compiled cascade.
     *
     * @param array{global?:list<string>,namespaces?:array<string,list<string>>} $manifest
     */
    public static function fromManifest(
        array $manifest,
        string $locale = 'en',
        string $fallback = 'en',
    ): self {
        return new self(
            $manifest['global'] ?? [],
            $locale,
            $fallback,
            $manifest['namespaces'] ?? [],
        );
    }

    /**
     * Switch the active locale for subsequent lookups. Called once per request
     * by LocaleStage after negotiating Accept-Language; a per-call $locale on
     * get()/choice()/has() overrides this without mutating it.
     *
     * Usage:
     *   $translator->setLocale('fr');
     *   $translator->get('validation.required', ['field' => 'e-mail']);
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    /**
     * The currently active locale.
     *
     * Usage:
     *   $translator->locale();   // => "en"
     */
    public function locale(): string
    {
        return $this->locale;
    }

    /**
     * Translate a "group.key" message with :placeholder substitution.
     *
     * The first dotted segment names the lang file (group); the rest indexes
     * into its returned array — 'validation.required' reads
     * {dir}/{locale}/validation.php ['required']. A missing key falls back to the
     * configured fallback locale, then returns the key itself (never throws,
     * never an empty string). Placeholders fill three cases: 'name' fills :name,
     * :Name and :NAME, and longer keys win so :min cannot corrupt :minutes.
     *
     * Usage:
     *   $t->get('validation.required', ['field' => 'email']);
     *   // => "The email field is required."
     *
     *   $t->get('report.title', locale: 'fr');   // force a locale for one call
     *
     *   $t->get('nope.missing');                 // => "nope.missing"
     *
     * @param array<string,string|int|float> $replace Placeholder => value map.
     * @param ?string                        $locale  Override the active locale.
     */
    public function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale ??= $this->locale;

        $value = $this->lookup($key, $locale);
        if ($value === null && $locale !== $this->fallback) {
            $value = $this->lookup($key, $this->fallback);
        }
        if (!is_string($value)) {
            return $key; // unresolved — surface the key rather than empty string
        }

        return $this->interpolate($value, $replace);
    }

    /**
     * Whether a key resolves to a string in the given (or active) locale. Tests
     * that locale ONLY — it does not consult the fallback — so it answers "does
     * this locale actually define this message?".
     *
     * Usage:
     *   if ($t->has('promo.banner')) { echo $t->get('promo.banner'); }
     *
     *   $t->has('promo.banner', 'fr');   // test the French file specifically
     *
     * @param ?string $locale Override the active locale.
     */
    public function has(string $key, ?string $locale = null): bool
    {
        return is_string($this->lookup($key, $locale ?? $this->locale));
    }

    /**
     * Pluralize a message. The resolved line is split on '|' into forms selected
     * by $count, with optional range prefixes:
     *
     *   'apple|apples'                        // count === 1 → first, else second
     *   '{0} none|[1,19] some|[20,*] many'    // exact count / inclusive ranges
     *
     * ':count' is always available as a replacement, alongside any you pass.
     *
     * Usage:
     *   // lang line: 'apple|apples'
     *   $t->choice('cart.apples', 1);                        // => "apple"
     *   $t->choice('cart.apples', 5);                        // => "apples"
     *
     *   // lang line: '{0} No items|[1,*] :count item(s) in :cart'
     *   $t->choice('cart.items', 0, ['cart' => 'bag']);      // => "No items"
     *   $t->choice('cart.items', 3, ['cart' => 'bag']);      // => "3 item(s) in bag"
     *
     * @param int                            $count   Drives which form is chosen.
     * @param array<string,string|int|float> $replace Extra placeholders (:count is auto).
     * @param ?string                        $locale  Override the active locale.
     */
    public function choice(string $key, int $count, array $replace = [], ?string $locale = null): string
    {
        $line    = $this->get($key, [], $locale);
        $replace = ['count' => $count] + $replace;

        return $this->interpolate($this->selectPluralForm($line, $count), $replace);
    }

    private function lookup(string $key, string $locale): mixed
    {
        // 'user::profile.title' → namespace 'user', group 'profile', item 'title'.
        $namespace = null;
        if (str_contains($key, '::')) {
            [$namespace, $key] = explode('::', $key, 2);
            if ($namespace === '') {
                $namespace = null;
            }
        }

        [$group, $item] = array_pad(explode('.', $key, 2), 2, null);
        if ($group === null || $item === null) {
            return null;
        }

        $data = $this->loadGroup($locale, $group, $namespace);

        $value = $data;
        foreach (explode('.', $item) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return null;
            }
        }
        return $value;
    }

    /**
     * Load and merge a group across the cascade.
     *
     * @return array<string,mixed>
     */
    private function loadGroup(string $locale, string $group, ?string $namespace = null): array
    {
        $cacheKey = ($namespace ?? '') . '::' . $group;

        if (isset($this->loaded[$locale][$cacheKey])) {
            return $this->loaded[$locale][$cacheKey];
        }

        // Defend against path traversal in every segment that reaches the path.
        // The namespace is included: it comes from the key, which may be built
        // from user input.
        $safe = '/^[A-Za-z0-9_\-]+$/';
        if (
            !preg_match($safe, $locale)
            || !preg_match($safe, $group)
            || ($namespace !== null && !preg_match($safe, $namespace))
        ) {
            return $this->loaded[$locale][$cacheKey] = [];
        }

        $data = [];

        // Reversed so the LOWEST-priority source is applied first and each
        // higher-priority source overwrites it key by key.
        foreach (array_reverse($this->rootsFor($namespace)) as $root) {
            $file = $root . '/' . $locale . '/' . $group . '.php';
            if (!is_file($file)) {
                continue;
            }

            $loaded = require $file;
            if (is_array($loaded)) {
                $data = array_replace_recursive($data, $loaded);
            }
        }

        return $this->loaded[$locale][$cacheKey] = $data;
    }

    /**
     * Catalogue roots to search, highest priority first.
     *
     * For a namespaced key the project's `{root}/{namespace}` folders come
     * first, so a project can override one plugin message while the plugin
     * stays the canonical source for the rest.
     *
     * @return list<string>
     */
    private function rootsFor(?string $namespace): array
    {
        if ($namespace === null) {
            return $this->paths;
        }

        $roots = [];
        foreach ($this->paths as $path) {
            $roots[] = $path . '/' . $namespace;
        }

        foreach ($this->namespaces[$namespace] ?? [] as $path) {
            $roots[] = $path;
        }

        return $roots;
    }

    /**
     * Substitute :placeholder tokens. Keys are applied longest-first so a short
     * name (:min) can never clobber a longer one that shares its prefix
     * (:minutes). Each key also honours capitalized variants — :Field and
     * :FIELD produce "Value" and "VALUE" respectively.
     *
     * @param array<string,string|int|float> $replace
     */
    private function interpolate(string $line, array $replace): string
    {
        if ($replace === []) {
            return $line;
        }

        // Longest key first — prevents ":min" corrupting ":minutes".
        uksort($replace, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        $pairs = [];
        foreach ($replace as $key => $value) {
            $value = (string) $value;
            $pairs[':' . $key]            = $value;
            $pairs[':' . ucfirst($key)]   = ucfirst($value);
            $pairs[':' . strtoupper($key)] = strtoupper($value);
        }

        return strtr($line, $pairs);
    }

    /**
     * Pick the correct segment of a '|'-delimited plural string for $count.
     * Supports Laravel-style range prefixes: '{0}', '[1,19]', '[20,*]'.
     */
    private function selectPluralForm(string $line, int $count): string
    {
        $segments = explode('|', $line);

        // Explicit range/exact prefixes take priority.
        foreach ($segments as $segment) {
            if (preg_match('/^\s*(?:\{(\d+)\}|\[(\d+),(\d+|\*)\])\s*/', $segment, $m) === 1) {
                $matches = isset($m[1]) && $m[1] !== ''
                    ? (int) $m[1] === $count
                    : (int) $m[2] <= $count && ($m[3] === '*' || $count <= (int) $m[3]);

                if ($matches) {
                    return trim(substr($segment, strlen($m[0])));
                }
            }
        }

        // Simple "singular|plural" fallback (no prefixes).
        $plain = array_values(array_filter(
            $segments,
            static fn(string $s): bool => preg_match('/^\s*(?:\{\d+\}|\[\d+,(?:\d+|\*)\])/', $s) !== 1,
        ));

        if ($plain === []) {
            return $line;
        }

        return $count === 1 ? $plain[0] : ($plain[1] ?? $plain[0]);
    }
}
