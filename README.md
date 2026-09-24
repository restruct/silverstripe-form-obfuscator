Form obfuscator (anti-spam) for SilverStripe
====

*Maintained by [Restruct](https://github.com/restruct). If this module saves you time, you can
[support ongoing maintenance](https://github.com/sponsors/restruct).*

An HTTP middleware that automatically obfuscates all form action URLs in HTML output by replacing
them with an encoded version, alternating between decimal and hexadecimal character references.

Browsers decode the references, so forms keep working unchanged. Naive spam bots that scrape
`action="..."` out of the raw HTML get an unusable string instead of a URL to post to. It is a
cheap first filter, not a replacement for a CAPTCHA or honeypot.

## Example
```
<form action="/cursus/">
```
becomes:
```
<form action="&#47;&#x63;&#117;&#x72;&#115;&#x75;&#115;&#x2f;">
```

## Requirements

* Silverstripe 5 or 6 (`silverstripe/framework`)
* PHP 8.1 or newer

## Installation

```
composer require restruct/silverstripe-form-obfuscator
```

Then flush (`?flush=1`, or `sake dev/build flush=1` on Silverstripe 5, `sake db:build --flush` on
Silverstripe 6). No further configuration is required.

## Version compatibility

| Branch | Module version | Silverstripe | PHP |
|--------|----------------|--------------|-----|
| `main` | `3.x` | `^5 \|\| ^6` | `^8.1` |
| (tags only) | `2.0.x` | `^4` | as Silverstripe 4 |

Silverstripe 4 reached end of life in April 2025 and is no longer supported or tested here. Projects
still on it should stay on the `2.0.x` tags, which remain available. `2.0.x` cannot be installed on
Silverstripe 5 or 6: it was built on `RequestFilter`, which framework 5 removed.

`main` is the only maintained line: it supports every Silverstripe version this module still
targets, so there is no separate maintenance branch. A version branch will be created only when a
change cannot be made compatible across the supported range.

**`composer.json` is the source of truth** for exact constraints; this table is a quick reference.

Upgrading from 2.x? See [UPGRADING.md](UPGRADING.md). Release notes: [CHANGELOG.md](CHANGELOG.md).

## How it works

The module registers `Restruct\FormObfuscator\FormObfuscatorMiddleware` as a global
[Director middleware](https://docs.silverstripe.org/en/developer_guides/controllers/middlewares/)
(`_config/extension.yml`). After the rest of the stack has produced a response, the middleware
rewrites every `action="..."` attribute in the body when:

* the response `Content-Type` is `text/html`, and
* the request URL does not start with one of the `excluded_url_prefixes` (below).

So it applies to every HTML response the site produces - pages, the login form, any controller -
not only to `ContentController` output.

The attribute value is decoded before it is encoded, so an action that already contains an entity
(for example a query string escaped as `&amp;`) still points at the same URL afterwards, and
non-ASCII characters are encoded as whole characters.

## Configuration

| Option | Default | Effect |
|--------|---------|--------|
| `excluded_url_prefixes` | `['admin', 'dev']` | Responses for URLs starting with one of these path segments (relative to the base URL, no leading slash) are left alone. Matched on whole segments: `admin` excludes `admin` and `admin/pages`, not `administration`. |

To add a prefix:

```yaml
Restruct\FormObfuscator\FormObfuscatorMiddleware:
  excluded_url_prefixes:
    - 'api'
```

This merges with the defaults (result: `admin`, `dev`, `api`). An indexed array cannot be replaced by
setting it again; to replace the list, reset it to `null` in one fragment and set it in a later one:

```yaml
---
Name: app-formobfuscator-reset
After: '#form-obfuscator'
---
Restruct\FormObfuscator\FormObfuscatorMiddleware:
  excluded_url_prefixes: null
---
Name: app-formobfuscator
After: '#app-formobfuscator-reset'
---
Restruct\FormObfuscator\FormObfuscatorMiddleware:
  excluded_url_prefixes:
    - 'api'
```

To switch the middleware off for a whole project, remove it from the Director:

```yaml
---
After: '#form-obfuscator'
---
SilverStripe\Core\Injector\Injector:
  SilverStripe\Control\Director:
    properties:
      Middlewares:
        FormObfuscatorMiddleware: null
```

## Public API

* `FormObfuscatorMiddleware::obfuscateForms(string $html): string` - encodes every double-quoted
  `action="..."` attribute in `$html`. Usable on its own, e.g. for HTML you send outside a response.
* `FormObfuscatorMiddleware::shouldObfuscate(HTTPRequest, HTTPResponse): bool` - the decision the
  middleware makes per response; override it in a subclass (registered via Injector) to change it.

## Limitations

* Only double-quoted `action="..."` attributes are rewritten. Silverstripe's own form templates use
  double quotes; single-quoted or unquoted attributes are left as they are.
* The rewrite is a pattern match on the response body, not an HTML parse. Any text in an HTML
  response that looks like `action="..."` is encoded - including inside a `<script>` block, where
  the browser does NOT decode character references. Keep form markup that JavaScript injects out of
  inline script strings, or build it with `setAttribute()`.
* It is not a security measure. It deters bots that do not decode HTML; anything running a real
  browser engine sees the plain URL.

## Running the tests

The suite needs a booted Silverstripe project. Install the module into a host project as a
**symlinked** path repository (`/tests` is `export-ignore`, so a dist install has no tests), then from
the host root:

```
# Silverstripe 5 (PHPUnit 9): flush with a trailing flush=1 AFTER the test path
vendor/bin/phpunit vendor/restruct/silverstripe-form-obfuscator/tests flush=1

# Silverstripe 6 (PHPUnit 11): flush through the environment
SS_PHPUNIT_FLUSH=1 vendor/bin/phpunit vendor/restruct/silverstripe-form-obfuscator/tests
```

`.github/workflows/ci.yml` builds exactly such a host project per Silverstripe major.
