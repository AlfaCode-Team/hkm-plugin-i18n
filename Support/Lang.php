<?php

declare(strict_types=1);

namespace Plugins\I18n\Support;

use Plugins\I18n\Translator;

/**
 * Request-scoped holder that lets the global translation helpers (__/trans/
 * trans_choice) reach the active Translator without a container reference.
 *
 * Bound by LocaleStage at the start of each request and cleared in a finally
 * block once the response is produced.
 *
 * COROUTINE SAFETY
 * ----------------
 * This used to keep the translator in a plain process-static, on the stated
 * assumption that "under OpenSwoole each worker serves one request at a time".
 * That is not how coroutines work: one worker interleaves MANY in-flight
 * requests, so while request A was suspended on I/O, request B would overwrite
 * the static — and A would resume translating in B's locale. A user could be
 * served another user's language, and the clear() at the end of whichever
 * finished first blanked it for everyone still running.
 *
 * The value now lives in the CURRENT COROUTINE's context when one exists, which
 * is isolated per coroutine and destroyed automatically when it ends (so a
 * missed clear() cannot leak either). Outside a coroutine — PHP-FPM, CLI,
 * workers — it falls back to the static, which is correct there because those
 * SAPIs genuinely do serve one request at a time.
 */
final class Lang
{
    private const KEY = 'hkm.i18n.translator';

    /** Fallback holder for non-coroutine SAPIs (FPM, CLI, worker). */
    private static ?Translator $translator = null;

    public static function bind(Translator $translator): void
    {
        $context = self::context();

        if ($context !== null) {
            $context[self::KEY] = $translator;

            return;
        }

        self::$translator = $translator;
    }

    public static function clear(): void
    {
        $context = self::context();

        if ($context !== null) {
            unset($context[self::KEY]);

            return;
        }

        self::$translator = null;
    }

    public static function translator(): ?Translator
    {
        $context = self::context();

        if ($context !== null) {
            $bound = $context[self::KEY] ?? null;

            return $bound instanceof Translator ? $bound : null;
        }

        return self::$translator;
    }

    /**
     * The current coroutine's context, or null when not inside one.
     *
     * getCid() returns -1 outside a coroutine, so the check is "> 0". Mirrors
     * the runtime detection used by the HttpClient retry backoff and the cache
     * lock, so all three agree on what "we are in a coroutine" means.
     */
    private static function context(): ?\ArrayObject
    {
        if (\class_exists('\\OpenSwoole\\Coroutine') && \OpenSwoole\Coroutine::getCid() > 0) {
            /** @var \ArrayObject $context */
            $context = \OpenSwoole\Coroutine::getContext();

            return $context;
        }

        if (\class_exists('\\Swoole\\Coroutine') && \Swoole\Coroutine::getCid() > 0) {
            /** @var \ArrayObject $context */
            $context = \Swoole\Coroutine::getContext();

            return $context;
        }

        return null;
    }
}
