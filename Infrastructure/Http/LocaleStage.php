<?php

declare(strict_types=1);

namespace Plugins\I18n\Infrastructure\Http;

use AlfacodeTeam\PhpServicePlatform\Kernel\Http\Request;
use AlfacodeTeam\PhpServicePlatform\Kernel\Http\Response;
use AlfacodeTeam\PhpServicePlatform\Kernel\Pipelines\Http\Contracts\HttpStageContract;
use Plugins\I18n\Support\Lang;
use Plugins\I18n\Translator;

/**
 * Resolves the per-request locale and exposes the Translator to the global
 * helpers for the duration of the request.
 *
 * Runs at after.load, once the route's module container exists. The active
 * locale is negotiated from Accept-Language against the app's supported list
 * (APP_LOCALES, comma-separated), falling back to the Translator's configured
 * default. The binding is always cleared in finally so it never leaks.
 */
final class LocaleStage implements HttpStageContract
{
    public function handle(Request $request, callable $next): Response
    {
        $container = $request->container();
        if ($container === null || !$container->has(Translator::class)) {
            return $next($request);
        }

        /** @var Translator $translator */
        $translator = $container->make(Translator::class);

        $locale = $this->negotiate($request, $translator);
        if ($locale !== null) {
            $translator->setLocale($locale);
        }

        Lang::bind($translator);
        try {
            return $next($request);
        } finally {
            Lang::clear();
        }
    }

    private function negotiate(Request $request, Translator $translator): ?string
    {
        $supported = array_values(array_filter(array_map(
            static fn(string $l): string => strtolower(trim($l)),
            explode(',', (string) (env('APP_LOCALES') ?: $translator->locale())),
        )));

        if ($supported === []) {
            return null;
        }

        foreach ($this->accepted($request) as $tag) {
            // A client that accepts anything gets our first choice, which is what
            // `Accept-Language: *` means.
            if ($tag === '*') {
                return $supported[0];
            }

            // An exact match always wins, so a catalogue that genuinely
            // distinguishes regions (pt-BR against pt-PT) still resolves to the
            // right one before the fold below can collapse them.
            if (in_array($tag, $supported, true)) {
                return $tag;
            }

            // FOLD THE REGION SUBTAG. Accept-Language carries a region the
            // catalogue usually does not: `fr-CD` is French as served to DR
            // Congo, and a French catalogue is the correct answer for it.
            //
            // This used to delegate to Negotiate::language(), which compares
            // whole tags — and Symfony's getLanguages() rewrites `fr-CD` to
            // `fr_CD`, so it never equalled `fr` either. A browser configured
            // with a single regional locale and no bare fallback (common on
            // mobile, and the default on several French and Arabic builds) was
            // therefore served the fallback language while announcing, clearly,
            // which language it wanted.
            $base = strstr($tag, '-', true);
            if ($base !== false && $base !== '' && in_array($base, $supported, true)) {
                return $base;
            }
        }

        return $translator->locale();
    }

    /**
     * Accept-Language parsed into client-ranked, lower-cased tags.
     *
     * Read from the raw header rather than Negotiate/getLanguages() because the
     * region subtag has to survive intact for the fold above, and because doing
     * so keeps this stage on the kernel's own method surface — which is what the
     * kernel going dependency-free is meant to preserve.
     *
     * Ordered by q descending; ties keep header order, which is the client's own
     * ranking. A malformed q sorts last rather than throwing, and `q=0` — the
     * explicit "not acceptable" — is dropped instead of ranked.
     *
     * @return list<string>
     */
    private function accepted(Request $request): array
    {
        $header = (string) ($request->header('Accept-Language') ?? '');
        if (trim($header) === '') {
            return [];
        }

        $ranked = [];
        foreach (explode(',', $header) as $position => $part) {
            $bits = explode(';', trim($part));
            $tag  = strtolower(trim($bits[0]));

            if ($tag === '' || preg_match('/^(\*|[a-z]{1,8}(-[a-z0-9]{1,8})*)$/', $tag) !== 1) {
                continue;
            }

            $quality = 1.0;
            foreach (array_slice($bits, 1) as $parameter) {
                $parameter = strtolower(str_replace(' ', '', $parameter));
                if (str_starts_with($parameter, 'q=')) {
                    $value   = substr($parameter, 2);
                    $quality = is_numeric($value) ? (float) $value : 0.0;
                }
            }

            if ($quality <= 0.0) {
                continue;
            }

            $ranked[] = ['tag' => $tag, 'q' => $quality, 'at' => $position];
        }

        usort($ranked, static fn(array $a, array $b): int => $b['q'] <=> $a['q'] ?: $a['at'] <=> $b['at']);

        return array_values(array_unique(array_column($ranked, 'tag')));
    }
}
