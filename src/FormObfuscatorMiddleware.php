<?php

namespace Restruct\FormObfuscator;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPStreamResponse;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

/**
 * SilverStripe Form action attribute Obfuscator
 *
 * HTTP middleware to automatically encode
 * all form action attributes in outputted HTML.
 * Switches between ASCII & hexadecimal encoding.
 *
 * Replaces the 2.x `Restruct\FormObfuscator\RequestProcessor` filter: `RequestFilter` and
 * `RequestProcessor` were removed from framework 5, `HTTPMiddleware` is their documented successor
 * (framework 5.0.0 changelog).
 *
 * Usage: composer require the module and run a ?flush=1
 *
 * License: MIT-style license http://opensource.org/licenses/MIT
 */
class FormObfuscatorMiddleware implements HTTPMiddleware
{
    use Configurable;
    use Injectable;

    /**
     * URL prefixes (first path segment(s), relative to the base URL, no leading slash) whose
     * responses are left alone. The CMS and dev tools gain nothing from obfuscation.
     *
     * Matched on whole path segments: 'admin' excludes 'admin' and 'admin/pages', not
     * 'administration'.
     *
     * @config
     * @var string[]
     */
    private static $excluded_url_prefixes = [
        'admin',
        'dev',
    ];

    /**
     * Filter executed AROUND a request: let the rest of the stack produce the response,
     * then run its output through the obfuscateForms filter.
     */
    public function process(HTTPRequest $request, callable $delegate)
    {
        $response = $delegate($request);

        if ($response instanceof HTTPResponse && $this->shouldObfuscate($request, $response)) {
            $response->setBody(
                $this->obfuscateForms((string) $response->getBody())
            );
        }

        return $response;
    }

    /**
     * Only HTML responses that are not streamed, and only outside the excluded URL prefixes.
     *
     * 2.x read the path from `$request->getVar('url')`, an SS3-era rewrite parameter that
     * Silverstripe 4+ no longer sets, so its admin/dev exclusion never matched. The path now
     * comes from HTTPRequest::getURL(), which is relative to the base URL.
     */
    public function shouldObfuscate(HTTPRequest $request, HTTPResponse $response): bool
    {
        # A streamed response (e.g. a text/html file served from assets) is left alone: getBody()
        # would read the whole stream into memory, and setBody() would keep the Content-Length the
        # stream was created with, so a rewritten (longer) body is truncated by the client. Core's
        # ChangeDetectionMiddleware::generateETag() skips streams for the same reason.
        if ($response instanceof HTTPStreamResponse) {
            return false;
        }

        # getHeader() returns null when the header is absent; cast so preg_match gets a string
        if (!preg_match('/text\/html/i', (string) $response->getHeader('Content-Type'))) {
            return false;
        }

        $url = ltrim((string) $request->getURL(), '/');
        foreach ((array) static::config()->get('excluded_url_prefixes') as $prefix) {
            $prefix = trim((string) $prefix, '/');
            if ($prefix === '') {
                continue;
            }
            # whole-segment match: the prefix itself, or the prefix followed by a slash
            if ($url === $prefix || strpos($url, $prefix . '/') === 0) {
                return false;
            }
        }

        return true;
    }

    /*
     * Obfuscate all matching form actions
     * @param string
     * @return string
     */
    public function obfuscateForms($html)
    {
        $reg = '@action=(")([^"]*)(")@i';

        # One callback per match instead of the 2.x loop of str_replace() calls: same result
        # (identical actions encode identically), without re-scanning the whole document per form.
        return (string) preg_replace_callback(
            $reg,
            function ($match) {
                return 'action=' . $match[1] . $this->encode($match[2]) . $match[3];
            },
            (string) $html
        );
    }

    /**
     * Obscure form action attribute
     *
     * @param string The action URL/URLSegement, as it appears in the HTML attribute
     *
     * @return string The encoded (ASCII & hexadecimal) action attribute value
     */
    protected function encode($originalString)
    {
        # The attribute value is HTML: '&amp;' in it means '&'. Encoding that raw text character
        # by character would turn '&amp;' into a literal "&amp;" once the browser decodes the
        # references - a double-encoded action. Decode first, so every reference below stands for
        # exactly one character of the real URL.
        $originalString = html_entity_decode((string) $originalString, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        # Split into characters, not bytes: ord() of each byte of a multibyte UTF-8 character
        # yields references to the wrong characters (é became "Ã©"). Fall back to bytes only if
        # the value is not valid UTF-8, which is what 2.x always did.
        $characters = preg_split('//u', $originalString, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            $characters = str_split($originalString);
        }

        $encodedString = '';
        $nowCodeString = '';
        foreach ($characters as $i => $character) {
            $codePoint = mb_ord($character, 'UTF-8');
            if ($codePoint === false) {
                $codePoint = ord($character);
            }
            $encodeMode = ( $i % 2 == 0 ) ? 1 : 2; // Switch encoding odd/even
            switch ( $encodeMode ) {
                case 1: // Decimal code
                    $nowCodeString = '&#' . $codePoint . ';';
                    break;
                case 2: // Hexadecimal code
                default:
                    $nowCodeString = '&#x' . dechex($codePoint) . ';';
                    break;
            }
            $encodedString .= $nowCodeString;
        }

        return $encodedString;
    }
}
