<?php

declare(strict_types=1);

namespace Plugins\I18n;

use AlfacodeTeam\PhpServicePlatform\Kernel\Boot\ManifestReader;
use AlfacodeTeam\PhpServicePlatform\Kernel\Contracts\ModuleContract;
use AlfacodeTeam\PhpServicePlatform\Kernel\Container\ModuleContainer;
use AlfacodeTeam\PhpServicePlatform\Kernel\Events\EventBus;
use AlfacodeTeam\PhpServicePlatform\Kernel\Pipelines\Cli\CliPipeline;
use AlfacodeTeam\PhpServicePlatform\Kernel\Pipelines\Http\HttpPipeline;
use AlfacodeTeam\PhpServicePlatform\Kernel\Pipelines\Worker\WorkerPipeline;
use Plugins\I18n\Infrastructure\Http\LocaleStage;

/**
 * I18n plugin — file-based translator for localized messages (validation, etc.).
 *
 * Binds a shared Translator into the request-scoped container so services can
 * inject it. Catalogues resolve through the boot-compiled cascade
 * (lang-manifest.php): project paths first, then each plugin that declared
 * "lang" in its module.json, with this plugin's own lang/ as the last resort.
 * APP_LANG_PATH prepends a further override on top.
 */
final class Provider implements ModuleContract
{
    public function solves(): string
    {
        return 'i18n.translation';
    }

    /** @return list<class-string> */
    public function requires(): array
    {
        return [];
    }

    /** @return list<class-string> */
    public function exposes(): array
    {
        return [Translator::class];
    }

    public function register(ModuleContainer $container): void
    {
        $container->bind(Translator::class, static function () {
            // Boot-compiled cascade: project catalogues first, then every
            // plugin that declared "lang" in its module.json.
            $manifest = ManifestReader::readCompiled('lang-manifest.php', [
                'global' => [],
                'namespaces' => [],
            ]);

            $global = $manifest['global'] ?? [];

            // APP_LANG_PATH is PREPENDED, not substituted. It used to replace
            // the directory outright, so an operator who pointed it at their own
            // catalogue silently lost every plugin's messages and got raw keys
            // back for anything they had not copied. Prepending keeps it an
            // override — highest priority, still cascading to the rest.
            $override = (string) (env('APP_LANG_PATH') ?: '');
            if ($override !== '') {
                array_unshift($global, $override);
            }

            // This plugin's own catalogue is the last resort. It is appended
            // rather than declared via module.json "lang" so it stays below
            // every other source no matter what the manifest contains.
            $global[] = __DIR__ . '/lang';

            return new Translator(
                directory:  array_values(array_unique($global)),
                locale:     env('APP_LOCALE') ?: 'en',
                fallback:   env('APP_FALLBACK_LOCALE') ?: 'en',
                namespaces: $manifest['namespaces'] ?? [],
            );
        });
    }

    public function boot(HttpPipeline $http, CliPipeline $cli, WorkerPipeline $worker, EventBus $events): void
    {
        // Negotiate the per-request locale and expose the Translator to the
        // global helpers. after.load runs once the route's container exists.
        $http->hook('after.load', LocaleStage::class, priority: 45);
    }
}
