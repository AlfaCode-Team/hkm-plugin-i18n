<?php

declare(strict_types=1);

namespace Tests\Unit\Plugins\I18n;

use AlfacodeTeam\PhpServicePlatform\Kernel\Container\CoreContainer;
use AlfacodeTeam\PhpServicePlatform\Kernel\Container\ModuleContainer;
use AlfacodeTeam\PhpServicePlatform\Kernel\Http\Request;
use AlfacodeTeam\PhpServicePlatform\Kernel\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Plugins\I18n\Infrastructure\Http\LocaleStage;
use Plugins\I18n\Support\Lang;
use Plugins\I18n\Translator;

/**
 * Accept-Language negotiation and the request-scoped Translator binding (I-03).
 *
 * Uses a real Request and a real ModuleContainer rather than doubles: the whole
 * point of the stage is how it behaves against the kernel's negotiation and
 * container contracts, and a mock would assert only that the stage calls the
 * methods this test was written to expect.
 */
#[CoversClass(LocaleStage::class)]
final class LocaleStageTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/hkm-i18n-stage-' . bin2hex(random_bytes(6));

        foreach (['en' => 'Hello.', 'fr' => 'Bonjour.', 'es' => 'Hola.'] as $locale => $greeting) {
            @mkdir($this->dir . '/' . $locale, 0777, true);
            file_put_contents(
                $this->dir . '/' . $locale . '/app.php',
                '<?php return ' . var_export(['greeting' => $greeting], true) . ';',
            );
        }

        $_ENV['APP_LOCALES'] = 'en,fr,es';
    }

    protected function tearDown(): void
    {
        // The stage clears this itself; unbinding here too keeps one failing
        // test from leaking a translator into the next.
        Lang::clear();
        unset($_ENV['APP_LOCALES']);

        foreach (glob($this->dir . '/*/*.php') ?: [] as $file) {
            unlink($file);
        }
        foreach (glob($this->dir . '/*') ?: [] as $sub) {
            is_dir($sub) && rmdir($sub);
        }
        is_dir($this->dir) && rmdir($this->dir);
    }

    private function requestAcceptingLanguage(?string $header, bool $withTranslator = true): Request
    {
        $server = $header === null ? [] : ['HTTP_ACCEPT_LANGUAGE' => $header];
        $request = Request::create('/', 'GET', server: $server);

        $container = new ModuleContainer(new CoreContainer());
        if ($withTranslator) {
            $container->bind(Translator::class, fn() => new Translator($this->dir, 'en', 'en'));
        }

        return $request->withContainer($container);
    }

    /** Runs the stage and reports the locale that was active inside the pipeline. */
    private function localeSeenDownstream(Request $request): ?string
    {
        $seen = null;

        (new LocaleStage())->handle($request, static function () use (&$seen): Response {
            $seen = Lang::translator()?->locale();

            return Response::text('ok');
        });

        return $seen;
    }

    // --- Negotiation ----------------------------------------------------------

    public function test_a_supported_language_is_selected_from_the_header(): void
    {
        $this->assertSame('fr', $this->localeSeenDownstream(
            $this->requestAcceptingLanguage('fr-FR,fr;q=0.9,en;q=0.5'),
        ));
    }

    public function test_quality_values_decide_between_two_supported_languages(): void
    {
        // Spanish is supported but explicitly lower-preference than French.
        $this->assertSame('fr', $this->localeSeenDownstream(
            $this->requestAcceptingLanguage('es;q=0.3,fr;q=0.9'),
        ));
    }

    public function test_a_regional_tag_folds_to_its_base_language(): void
    {
        // The bug this fixes: a browser configured with ONE regional locale and
        // no bare fallback sends `fr-CD` alone. Whole-tag comparison matched
        // nothing in [en, fr, es] and served English to a French reader who had
        // said, unambiguously, which language they wanted.
        $this->assertSame('fr', $this->localeSeenDownstream(
            $this->requestAcceptingLanguage('fr-CD'),
        ));
    }

    public function test_the_fold_respects_quality_order(): void
    {
        // The highest-q tag is unsupported even after folding, so the next one
        // is folded rather than the header being abandoned at the first miss.
        $this->assertSame('fr', $this->localeSeenDownstream(
            $this->requestAcceptingLanguage('de-DE;q=0.9,fr-CH;q=0.8'),
        ));
    }

    public function test_an_exact_match_is_preferred_over_a_fold(): void
    {
        // Folding must not flatten a catalogue that genuinely DOES distinguish
        // regions. With both 'fr-ca' and 'fr' on offer, `fr-CA` has to resolve to
        // the Canadian catalogue — folding it to 'fr' first would make a regional
        // catalogue unreachable for the only header that can ask for it.
        $_ENV['APP_LOCALES'] = 'en,fr,fr-ca';

        $this->assertSame('fr-ca', $this->localeSeenDownstream(
            $this->requestAcceptingLanguage('fr-CA,fr;q=0.9'),
        ));
    }

    public function test_a_regional_tag_still_folds_when_no_regional_catalogue_exists(): void
    {
        // The mirror of the case above: same header, but 'fr-ca' is not offered,
        // so the fold is what stops it falling all the way to English.
        $_ENV['APP_LOCALES'] = 'en,fr';

        $this->assertSame('fr', $this->localeSeenDownstream(
            $this->requestAcceptingLanguage('fr-CA'),
        ));
    }

    public function test_a_zero_quality_language_is_not_selected(): void
    {
        // q=0 means "explicitly not acceptable" — honouring it would serve the
        // one language the client ruled out.
        $this->assertSame('en', $this->localeSeenDownstream(
            $this->requestAcceptingLanguage('fr;q=0,en'),
        ));
    }

    public function test_a_wildcard_takes_the_first_supported_language(): void
    {
        $this->assertSame('en', $this->localeSeenDownstream(
            $this->requestAcceptingLanguage('*'),
        ));
    }

    public function test_an_unsupported_language_falls_back_to_the_default(): void
    {
        $this->assertSame('en', $this->localeSeenDownstream(
            $this->requestAcceptingLanguage('de-DE,de;q=0.9'),
        ));
    }

    public function test_a_missing_header_leaves_the_default_locale_active(): void
    {
        $this->assertSame('en', $this->localeSeenDownstream(
            $this->requestAcceptingLanguage(null),
        ));
    }

    public function test_a_malformed_header_does_not_break_the_request(): void
    {
        // Garbage in Accept-Language is attacker-controllable and must never
        // turn into a 500 — the worst acceptable outcome is the default locale.
        $this->assertSame('en', $this->localeSeenDownstream(
            $this->requestAcceptingLanguage(';;;q=,,'),
        ));
    }

    // --- Binding lifecycle ----------------------------------------------------

    public function test_the_translator_is_bound_during_the_request(): void
    {
        $bound = false;

        (new LocaleStage())->handle(
            $this->requestAcceptingLanguage('fr'),
            static function () use (&$bound): Response {
                $bound = Lang::translator() instanceof Translator;

                return Response::text('ok');
            },
        );

        $this->assertTrue($bound);
    }

    public function test_the_binding_is_cleared_after_the_request(): void
    {
        (new LocaleStage())->handle(
            $this->requestAcceptingLanguage('fr'),
            static fn(): Response => Response::text('ok'),
        );

        $this->assertNull(Lang::translator(), 'a leaked translator would serve the next request the wrong locale');
    }

    public function test_the_binding_is_cleared_even_when_the_pipeline_throws(): void
    {
        // The finally is the only thing standing between one failed request and
        // every subsequent request on the worker inheriting its locale.
        try {
            (new LocaleStage())->handle(
                $this->requestAcceptingLanguage('fr'),
                static fn(): Response => throw new \RuntimeException('downstream failure'),
            );
            self::fail('the exception should propagate');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertNull(Lang::translator());
    }

    // --- Degradation ----------------------------------------------------------

    public function test_the_stage_passes_through_when_no_translator_is_bound(): void
    {
        // A route that never loaded the I18n module still has to serve.
        $response = (new LocaleStage())->handle(
            $this->requestAcceptingLanguage('fr', withTranslator: false),
            static fn(): Response => Response::text('served'),
        );

        $this->assertSame('served', $response->getContent());
        $this->assertNull(Lang::translator());
    }

    public function test_the_stage_passes_through_when_there_is_no_container(): void
    {
        // CLI and worker entry points produce a request with no module container.
        $response = (new LocaleStage())->handle(
            Request::create('/', 'GET'),
            static fn(): Response => Response::text('served'),
        );

        $this->assertSame('served', $response->getContent());
    }
}
