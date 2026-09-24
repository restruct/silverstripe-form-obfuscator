# Upgrading

## 2.x -> 3.0

**3.0 is the Silverstripe 5 and 6 line.** Silverstripe 4 projects stay on `2.0.x`, which cannot be
installed on 5 or 6.

```
composer require restruct/silverstripe-form-obfuscator:^3   # Silverstripe 5 / 6
composer require restruct/silverstripe-form-obfuscator:^2   # Silverstripe 4
```

Requirements changed with the line: PHP `^8.1`, `silverstripe/framework ^5 || ^6`.

### If you only installed the module

Nothing to do beyond a flush. The middleware registers itself.

### If you referenced the 2.x class

`Restruct\FormObfuscator\RequestProcessor` no longer exists. It implemented
`SilverStripe\Control\RequestFilter`, which framework 5 removed together with
`SilverStripe\Control\RequestProcessor`. Its replacement is
`Restruct\FormObfuscator\FormObfuscatorMiddleware`:

| 2.x | 3.0 |
|-----|-----|
| `RequestProcessor::obfuscateForms($html)` | `FormObfuscatorMiddleware::obfuscateForms($html)` (same contract) |
| `RequestProcessor::postRequest()` | `FormObfuscatorMiddleware::process()` (HTTPMiddleware) |
| hard-coded `admin`/`dev` exclusion (never matched on SS4+) | `excluded_url_prefixes` config, default `['admin', 'dev']` |
| registered under `RequestProcessor.filters` | registered under `Director.Middlewares` as `FormObfuscatorMiddleware` |

If your project config removed or reordered the 2.x filter in `RequestProcessor.filters`, that
config no longer does anything; see README "Configuration" for how to switch the middleware off.

### Behaviour you may notice

- **CMS (`admin/...`) and `dev/...` responses are no longer obfuscated.** 2.x meant to skip them but
  its check never matched on Silverstripe 4+. If something relied on encoded actions there, set
  `excluded_url_prefixes` (README "Configuration").
- Actions containing `&amp;` or non-ASCII characters now decode to the correct URL; in 2.x they
  decoded to a wrong one.
