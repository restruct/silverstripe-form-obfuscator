# Changelog

## 3.0.0

**Silverstripe 5 and 6.** One line, `main`, supports both. Silverstripe 4 stays on the `2.0.x` tags;
nothing here is backported. Upgrade guide: [UPGRADING.md](UPGRADING.md).

Requires PHP `^8.1` and `silverstripe/framework ^5 || ^6`.

### Changed

- **Rebuilt as an HTTP middleware.** 2.x was a `RequestFilter` added to the `RequestProcessor`;
  framework 5 removed both, so 2.x cannot load on Silverstripe 5 or 6. The class is now
  `Restruct\FormObfuscator\FormObfuscatorMiddleware`, registered on the `Director` as
  `FormObfuscatorMiddleware`. The old `Restruct\FormObfuscator\RequestProcessor` class is gone.
- **New config option `excluded_url_prefixes`** (default `['admin', 'dev']`): responses for those
  URL prefixes are left alone. Replaces the hard-coded check in 2.x, which never matched (below).
- `composer.json` now declares PSR-4 autoloading, a `funding` entry and a `3.x-dev` branch alias.

### Fixed

- **CMS and dev URLs were obfuscated too.** 2.x read the request path from `getVar('url')`, a
  Silverstripe 3 rewrite parameter that Silverstripe 4+ never sets, so its `admin`/`dev` exclusion
  never applied. The path now comes from `HTTPRequest::getURL()`.
- **An action containing an entity was double-encoded.** A query string in an action is escaped as
  `&amp;`; 2.x encoded that text character by character, so the browser decoded it to a literal
  `&amp;` and the form posted to the wrong URL. The value is now decoded before it is encoded.
- **Non-ASCII characters in an action were corrupted.** 2.x encoded bytes, not characters, so
  `é` came out as `Ã©`. Characters are now encoded whole (invalid UTF-8 falls back to bytes).
- **Streamed HTML responses were rewritten and truncated.** A `text/html` `HTTPStreamResponse`
  (for example an HTML file served from assets) was read whole into memory and its rewritten, longer
  body was sent with the Content-Length of the original stream. Streamed responses are now passed
  through untouched.

### Added

- Test suite (19 tests, identical on Silverstripe 5 and 6) with a regression test for each fix
  above, and a GitHub Actions matrix: Silverstripe 5 on PHP 8.1 and 8.3, Silverstripe 6 on PHP 8.3
  and 8.4.
- README: how it works, configuration, public API, limitations, running the tests, and a version
  compatibility table.

## 2.0.3

- Fix the action regex, so obfuscation works again.

## 2.0.2 / 2.0.1

- `composer.json` updates (`2.0.1` and `2.0.2` point at the same commit).

## 2.0

- Silverstripe 4 version.
