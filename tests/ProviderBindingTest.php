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
use Plugins\I18n\Provider;
use Plugins\I18n\Support\Lang;
use Plugins\I18n\Translator;

/**
 * How the Provider binds the Translator into the request container.
 *
 * This is one assertion with real consequences, which is why it has its own
 * test: the Provider used to call bind(), whose $shared parameter defaults to
 * false, so every make() produced a NEW Translator. LocaleStage would negotiate
 * a locale, set it on the instance it had just resolved and bind THAT to the
 * Lang helpers — while a service injecting Translator received a fresh one
 * still sitting at APP_LOCALE. `__()` and an injected Translator could answer
 * in two different languages inside the same request, and there was no way for
 * an application to change the locale in a way the helpers would see.
 */
#[CoversClass(Provider::class)]
final class ProviderBindingTest extends TestCase
{
    private function container(): ModuleContainer
    {
        $container = new ModuleContainer(new CoreContainer());
        (new Provider())->register($container);

        return $container;
    }

    protected function tearDown(): void
    {
        Lang::clear();
        unset($_ENV['APP_LOCALES'], $_ENV['APP_LOCALE']);
    }

    public function test_the_translator_is_shared_within_a_request(): void
    {
        $container = $this->container();

        $this->assertSame(
            $container->make(Translator::class),
            $container->make(Translator::class),
            'two resolutions in one request must be the same Translator',
        );
    }

    public function test_a_locale_change_is_visible_to_both_the_helpers_and_the_container(): void
    {
        $_ENV['APP_LOCALE']  = 'en';
        $_ENV['APP_LOCALES'] = 'en,fr';

        $container = $this->container();
        $request   = Request::create('/', 'GET', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr'])
            ->withContainer($container);

        $seen = [];
        (new LocaleStage())->handle($request, static function () use (&$seen, $container): Response {
            $seen = [
                'helpers'   => Lang::translator()?->locale(),
                'container' => $container->make(Translator::class)->locale(),
            ];

            return Response::text('ok');
        });

        $this->assertSame('fr', $seen['helpers']);
        $this->assertSame('fr', $seen['container'], 'an injected Translator must speak the negotiated locale too');
    }

    public function test_each_request_container_gets_its_own_translator(): void
    {
        // Shared per REQUEST, not per worker: a locale set for one request must
        // never be inherited by the next one on the same process.
        $this->assertNotSame(
            $this->container()->make(Translator::class),
            $this->container()->make(Translator::class),
        );
    }
}
