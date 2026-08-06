<?php

declare(strict_types=1);

namespace Tests\Unit\Plugins\I18n;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Plugins\I18n\Translator;

/**
 * The multi-source catalogue cascade that lets plugins ship their own messages.
 *
 * Before this existed the Translator held one directory, so the only catalogue
 * that could load was the one this plugin ships — which is why no other plugin
 * was translatable. These tests pin the resolution rules that plugins and
 * projects now depend on.
 */
#[CoversClass(Translator::class)]
final class CatalogueCascadeTest extends TestCase
{
    private string $root;
    private string $project;
    private string $plugin;

    protected function setUp(): void
    {
        $this->root    = sys_get_temp_dir() . '/hkm-i18n-cascade-' . bin2hex(random_bytes(6));
        $this->project = $this->root . '/project';
        $this->plugin  = $this->root . '/plugin';
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeTree($path) : unlink($path);
        }
        rmdir($dir);
    }

    /** @param array<string,mixed> $lines */
    private function write(string $root, string $locale, string $group, array $lines): void
    {
        @mkdir($root . '/' . $locale, 0777, true);
        file_put_contents(
            $root . '/' . $locale . '/' . $group . '.php',
            '<?php return ' . var_export($lines, true) . ';',
        );
    }

    // --- Global cascade -------------------------------------------------------

    public function test_a_project_key_overrides_the_plugin_key(): void
    {
        $this->write($this->plugin, 'en', 'app', ['title' => 'Plugin title']);
        $this->write($this->project, 'en', 'app', ['title' => 'Project title']);

        // Project path first — highest priority.
        $t = new Translator([$this->project, $this->plugin], 'en', 'en');

        $this->assertSame('Project title', $t->get('app.title'));
    }

    /**
     * The rule that makes overriding practical. If the project's file replaced
     * the plugin's wholesale, overriding one key in a large group would mean
     * copying every other key — and the copy would then silently miss anything
     * the plugin added in a later release.
     */
    public function test_overriding_one_key_keeps_the_rest_of_the_group(): void
    {
        $this->write($this->plugin, 'en', 'app', [
            'title'    => 'Plugin title',
            'subtitle' => 'Plugin subtitle',
            'footer'   => 'Plugin footer',
        ]);
        $this->write($this->project, 'en', 'app', ['title' => 'Project title']);

        $t = new Translator([$this->project, $this->plugin], 'en', 'en');

        $this->assertSame('Project title', $t->get('app.title'));
        $this->assertSame('Plugin subtitle', $t->get('app.subtitle'));
        $this->assertSame('Plugin footer', $t->get('app.footer'));
    }

    public function test_nested_keys_merge_rather_than_replace(): void
    {
        $this->write($this->plugin, 'en', 'app', [
            'nav' => ['home' => 'Home', 'about' => 'About'],
        ]);
        $this->write($this->project, 'en', 'app', [
            'nav' => ['home' => 'Start'],
        ]);

        $t = new Translator([$this->project, $this->plugin], 'en', 'en');

        $this->assertSame('Start', $t->get('app.nav.home'));
        $this->assertSame('About', $t->get('app.nav.about'));
    }

    public function test_a_group_only_the_plugin_defines_still_resolves(): void
    {
        $this->write($this->plugin, 'en', 'errors', ['denied' => 'Access denied.']);

        $t = new Translator([$this->project, $this->plugin], 'en', 'en');

        $this->assertSame('Access denied.', $t->get('errors.denied'));
    }

    public function test_a_missing_source_directory_is_skipped_not_fatal(): void
    {
        $this->write($this->plugin, 'en', 'app', ['title' => 'Plugin title']);

        // The project directory is never created — a declared-but-absent
        // catalogue must not take the request down.
        $t = new Translator([$this->project, $this->plugin], 'en', 'en');

        $this->assertSame('Plugin title', $t->get('app.title'));
    }

    // --- Namespaces -----------------------------------------------------------

    public function test_a_namespaced_key_resolves_from_its_own_catalogue(): void
    {
        $this->write($this->plugin, 'en', 'profile', ['title' => 'User profile']);

        $t = new Translator([], 'en', 'en', ['user' => [$this->plugin]]);

        $this->assertSame('User profile', $t->get('user::profile.title'));
    }

    /**
     * Two plugins defining the same group and key must not collide — the whole
     * reason namespaces exist.
     */
    public function test_two_namespaces_can_define_the_same_key(): void
    {
        $other = $this->root . '/other';
        $this->write($this->plugin, 'en', 'profile', ['title' => 'User profile']);
        $this->write($other, 'en', 'profile', ['title' => 'Tenant profile']);

        $t = new Translator([], 'en', 'en', [
            'user'    => [$this->plugin],
            'tenancy' => [$other],
        ]);

        $this->assertSame('User profile', $t->get('user::profile.title'));
        $this->assertSame('Tenant profile', $t->get('tenancy::profile.title'));
    }

    /**
     * A project overrides a namespaced message by mirroring the namespace as a
     * subfolder — the same shape namespaced views use.
     */
    public function test_the_project_can_override_a_namespaced_key(): void
    {
        $this->write($this->plugin, 'en', 'profile', [
            'title'  => 'User profile',
            'legend' => 'Plugin legend',
        ]);
        $this->write($this->project . '/user', 'en', 'profile', ['title' => 'My account']);

        $t = new Translator([$this->project], 'en', 'en', ['user' => [$this->plugin]]);

        $this->assertSame('My account', $t->get('user::profile.title'));
        $this->assertSame('Plugin legend', $t->get('user::profile.legend'), 'the override still merges');
    }

    public function test_an_unknown_namespace_returns_the_key(): void
    {
        $t = new Translator([$this->project], 'en', 'en');

        $this->assertSame('nope::app.title', $t->get('nope::app.title'));
    }

    public function test_a_namespaced_key_falls_back_to_the_fallback_locale(): void
    {
        $this->write($this->plugin, 'en', 'profile', ['title' => 'User profile']);
        $this->write($this->plugin, 'fr', 'profile', ['other' => 'Autre']);

        $t = new Translator([], 'fr', 'en', ['user' => [$this->plugin]]);

        $this->assertSame('User profile', $t->get('user::profile.title'));
    }

    // --- Safety ---------------------------------------------------------------

    /**
     * A namespace reaches the filesystem path, and keys can be built from user
     * input, so it gets the same traversal guard as locale and group.
     */
    public function test_a_traversing_namespace_is_refused(): void
    {
        $t = new Translator([$this->project], 'en', 'en', ['user' => [$this->plugin]]);

        $this->assertSame('../../etc::app.title', $t->get('../../etc::app.title'));
    }

    public function test_an_empty_namespace_prefix_is_treated_as_global(): void
    {
        $this->write($this->project, 'en', 'app', ['title' => 'Project title']);

        $t = new Translator([$this->project], 'en', 'en');

        $this->assertSame('Project title', $t->get('::app.title'));
    }

    // --- Construction ---------------------------------------------------------

    public function test_a_single_directory_string_still_works(): void
    {
        // Backward compatibility: most call sites pass one directory.
        $this->write($this->plugin, 'en', 'app', ['title' => 'Plugin title']);

        $this->assertSame('Plugin title', (new Translator($this->plugin, 'en', 'en'))->get('app.title'));
    }

    public function test_from_manifest_builds_the_cascade(): void
    {
        $this->write($this->plugin, 'en', 'profile', ['title' => 'User profile']);
        $this->write($this->project, 'en', 'app', ['title' => 'Project title']);

        $t = Translator::fromManifest([
            'global'     => [$this->project],
            'namespaces' => ['user' => [$this->plugin]],
        ], 'en', 'en');

        $this->assertSame('Project title', $t->get('app.title'));
        $this->assertSame('User profile', $t->get('user::profile.title'));
    }

    public function test_an_empty_manifest_degrades_to_returning_keys(): void
    {
        $t = Translator::fromManifest([], 'en', 'en');

        $this->assertSame('app.title', $t->get('app.title'));
    }
}
