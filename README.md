# hkm-plugin-i18n

> HKM Kernel plugin — provides **`i18n.translation`**.
> Part of the [HKM Kernel](https://github.com/AlfaCode-Team/hkm-kernel) Gated Demand Architecture framework.

[![License: MIT](https://img.shields.io/badge/License-MIT-green)](LICENSE)
![PHP](https://img.shields.io/badge/PHP-8.4%2B-777bb4)

## Install

```bash
composer require alfacode-team/hkm-plugin-i18n
# or, from a project:
hkm plugins add i18n
```

## Capability

Provides `i18n.translation`.

## Locale negotiation

`LocaleStage` (registered at `after.load`, priority 45) picks the request's
locale from `Accept-Language`, matched against **`APP_LOCALES`** — a
comma-separated list of the locales your app actually ships catalogues for.

```dotenv
APP_LOCALE=en            # the default, and the fallback when nothing matches
APP_FALLBACK_LOCALE=en   # consulted for keys missing from the active locale
APP_LOCALES=en,fr        # what negotiation is allowed to choose from
```

> **Set `APP_LOCALES`.** Unset, it falls back to a list of one (the default
> locale), so negotiation can only ever return that locale and every other
> catalogue you ship is unreachable — a monolingual app that looks configured.

Matching, in order, per client tag by descending `q`:

| Header | `APP_LOCALES=en,fr` | Why |
|---|---|---|
| `fr` | `fr` | exact match |
| `fr-CD` | `fr` | **region subtag folded** to its base language |
| `fr-CA,fr;q=0.9` with `en,fr,fr-ca` | `fr-ca` | an exact match always beats a fold |
| `de-DE;q=0.9,fr-CH;q=0.8` | `fr` | a miss moves to the next tag, it does not give up |
| `*` | `en` | the first supported locale |
| `fr;q=0` | *(skipped)* | `q=0` means explicitly **not** acceptable |
| *(absent or malformed)* | `en` | never throws — the worst case is the default |

The fold matters more than it looks: a browser configured with a single
regional locale and no bare fallback sends `fr-CD` **alone**. Whole-tag
comparison matches nothing and serves the fallback language to a reader who
said exactly which language they wanted.

## The Translator is shared per request

`Provider::register()` binds `Translator` as a **singleton in the
request-scoped container**, so the instance behind `__()` / `trans()` /
`lang_locale()` is the same one a service receives by injection. Setting the
locale in one place is therefore visible in the other — and because the binding
lives in the request container, it is discarded at end-of-request and never
leaks into the next one.

An application that needs to override the negotiated locale (a per-tenant or
per-edition publishing language, say) should register its own stage with a
**higher priority number** than 45. Lower priority = outer, so 46 runs *inside*
`LocaleStage` — the Translator is already bound and the negotiated locale is
there to be refined rather than raced:

```php
$http->hook('after.load', MyEditionLocaleStage::class, priority: 46);
```

## Helpers

```php
__('nav.home');                                  // alias of trans()
trans('validation.required', ['field' => 'email']);
trans_choice('cart.items', 3);
lang_has('promo.banner');
lang_locale();                                    // for <html lang="…">
```

### `trans_or()` — the form plugin code should use

`trans()` returns the KEY when nothing resolves. That is right for an
application (a raw `checkout.title` on screen is an obvious, findable bug) and
wrong for a plugin, which can legitimately run where no catalogue exists at all:
I18n is not a dependency of every plugin, and translation is unbound on CLI and
worker paths besides.

```php
return Response::forbidden(trans_or(
    'security-filters::messages.shield.forbidden',
    'You do not have the required permissions to access this resource.',
));
```

Translate when we can; otherwise say exactly what the code said before it was
made translatable. Adding a catalogue can then only improve the output, never
degrade it — which is what makes it safe to convert a whole plugin at once.

## Documentation

- [Kernel guides](https://github.com/AlfaCode-Team/hkm-kernel/tree/main/docs/guides) — the framework contracts this plugin builds on.

## License

MIT — see [LICENSE](LICENSE).
