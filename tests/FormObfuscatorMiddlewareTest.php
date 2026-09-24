<?php

namespace Restruct\FormObfuscator\Tests;

use Restruct\FormObfuscator\FormObfuscatorMiddleware;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * Unit tests for the encoder and for the middleware's decision whether to touch a response.
 * No database needed.
 */
class FormObfuscatorMiddlewareTest extends SapphireTest
{
    protected $usesDatabase = false;

    /**
     * What a browser does with the attribute value: decode the character references once.
     */
    private function browserDecode(string $attributeValue): string
    {
        return html_entity_decode($attributeValue, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Pull the (still encoded) action attribute value out of a snippet.
     */
    private function actionOf(string $html): string
    {
        $this->assertSame(1, preg_match('@action="([^"]*)"@', $html, $m), 'no action attribute in: ' . $html);
        return $m[1];
    }

    /**
     * Run the middleware over a response produced by a stub delegate.
     */
    private function runMiddleware(string $url, string $body, ?string $contentType = null): HTTPResponse
    {
        $response = HTTPResponse::create($body);
        if ($contentType !== null) {
            $response->addHeader('Content-Type', $contentType);
        }
        return FormObfuscatorMiddleware::create()->process(
            new HTTPRequest('GET', $url),
            function () use ($response) {
                return $response;
            }
        );
    }

    public function testMiddlewareIsRegisteredOnTheDirector()
    {
        $found = false;
        foreach (Injector::inst()->get(Director::class)->getMiddlewares() as $middleware) {
            if ($middleware instanceof FormObfuscatorMiddleware) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'FormObfuscatorMiddleware is not in Director Middlewares');
    }

    public function testEncodingMatchesTheDocumentedExample()
    {
        # The exact example from the README: characters alternate decimal / hexadecimal.
        $this->assertSame(
            '<form action="&#47;&#x63;&#117;&#x72;&#115;&#x75;&#115;&#x2f;">',
            FormObfuscatorMiddleware::create()->obfuscateForms('<form action="/cursus/">')
        );
    }

    public function testEncodedActionDecodesToTheOriginal()
    {
        $html = FormObfuscatorMiddleware::create()->obfuscateForms('<form action="/some/Page/Form" method="post">');
        $action = $this->actionOf($html);
        $this->assertStringNotContainsString('/some', $action, 'action left in plain text');
        $this->assertSame('/some/Page/Form', $this->browserDecode($action));
        $this->assertStringEndsWith(' method="post">', $html, 'markup around the attribute changed');
    }

    public function testEveryFormInTheDocumentIsEncoded()
    {
        $in = '<form action="/a"></form><p>action text</p><form action="/b/c"></form><form action="/a"></form>';
        $out = FormObfuscatorMiddleware::create()->obfuscateForms($in);
        preg_match_all('@action="([^"]*)"@', $out, $m);
        $this->assertSame(['/a', '/b/c', '/a'], array_map([$this, 'browserDecode'], $m[1]));
        $this->assertStringNotContainsString('action="/', $out);
        $this->assertStringContainsString('<p>action text</p>', $out, 'text that is not an attribute changed');
    }

    public function testHtmlWithoutFormsIsUnchanged()
    {
        $html = '<html><body><a href="/cursus/">Cursus</a></body></html>';
        $this->assertSame($html, FormObfuscatorMiddleware::create()->obfuscateForms($html));
    }

    public function testEmptyActionStaysEmpty()
    {
        $this->assertSame('<form action="">', FormObfuscatorMiddleware::create()->obfuscateForms('<form action="">'));
    }

    /**
     * Regression, 2.x: an action containing an entity (a query string escaped as '&amp;' by the
     * template layer) was encoded as raw text, so the browser decoded it to a literal "&amp;" and
     * the form posted to the wrong URL.
     */
    public function testEscapedAmpersandInActionIsNotDoubleEncoded()
    {
        $html = FormObfuscatorMiddleware::create()->obfuscateForms('<form action="/search?a=1&amp;b=2">');
        $this->assertSame('/search?a=1&b=2', $this->browserDecode($this->actionOf($html)));
    }

    /**
     * Regression, 2.x: encoded byte by byte, so a multibyte UTF-8 character became two wrong
     * characters ("é" decoded as "Ã©").
     */
    public function testMultibyteCharactersInActionSurvive()
    {
        $html = FormObfuscatorMiddleware::create()->obfuscateForms('<form action="/café/Form">');
        $this->assertSame('/café/Form', $this->browserDecode($this->actionOf($html)));
    }

    public function testInvalidUtf8FallsBackToBytes()
    {
        # Not valid UTF-8: must not crash or drop the value; bytes are encoded as 2.x did.
        $html = FormObfuscatorMiddleware::create()->obfuscateForms("<form action=\"/a\xE9\">");
        $this->assertSame('&#47;&#x61;&#233;', $this->actionOf($html));
    }

    public function testHtmlResponseIsObfuscated()
    {
        $response = $this->runMiddleware('some/page', '<form action="/some/page/Form">');
        $this->assertStringNotContainsString('action="/some', $response->getBody());
        $this->assertSame('/some/page/Form', $this->browserDecode($this->actionOf($response->getBody())));
    }

    public function testNonHtmlResponseIsUntouched()
    {
        # Raw markup in a non-HTML response (a JSON string would escape the quotes and never
        # match the pattern, so it could not show the content-type check working).
        $body = '<form action="/x">';
        $this->assertSame($body, $this->runMiddleware('api/thing', $body, 'text/plain')->getBody());
    }

    public function testResponseWithoutContentTypeIsUntouched()
    {
        $response = HTTPResponse::create('<form action="/x">');
        $response->removeHeader('Content-Type');
        $out = FormObfuscatorMiddleware::create()->process(
            new HTTPRequest('GET', 'some/page'),
            function () use ($response) {
                return $response;
            }
        );
        $this->assertSame('<form action="/x">', $out->getBody());
    }

    /**
     * Pins the ltrim() of the request URL: a request whose getURL() reports a leading slash (a
     * subclass or a hand-built request; HTTPRequest::setUrl() itself strips it) must still match
     * the excluded prefixes.
     */
    public function testExcludedPrefixMatchesAUrlWithALeadingSlash()
    {
        $request = new class ('GET', 'admin/pages') extends HTTPRequest {
            public function getURL($includeGetVars = false)
            {
                return '/admin/pages';
            }
        };
        $response = HTTPResponse::create('<form action="/x">');
        $response->addHeader('Content-Type', 'text/html; charset=utf-8');

        $this->assertFalse(
            FormObfuscatorMiddleware::create()->shouldObfuscate($request, $response),
            '/admin/pages was treated as not excluded'
        );
        $this->assertSame(
            '<form action="/x">',
            FormObfuscatorMiddleware::create()->process(
                $request,
                function () use ($response) {
                    return $response;
                }
            )->getBody(),
            '/admin/pages was obfuscated'
        );
    }

    /**
     * Regression, 2.x: the exclusion read $request->getVar('url'), which Silverstripe 4+ never
     * sets, so CMS and dev responses were obfuscated too.
     */
    public function testAdminAndDevUrlsAreNotObfuscated()
    {
        foreach (['admin', 'admin/pages/edit/show/1', 'dev', 'dev/tasks'] as $url) {
            $this->assertSame(
                '<form action="/x">',
                $this->runMiddleware($url, '<form action="/x">')->getBody(),
                "$url was obfuscated"
            );
        }
    }

    public function testExclusionMatchesWholeSegmentsOnly()
    {
        foreach (['administration', 'developers/list'] as $url) {
            $this->assertStringNotContainsString(
                'action="/x"',
                $this->runMiddleware($url, '<form action="/x">')->getBody(),
                "$url was treated as excluded"
            );
        }
    }

    public function testEmptyPrefixDoesNotExcludeTheHomePage()
    {
        # An empty or bare-slash entry (easy to produce in YAML) must be ignored, not treated as
        # "the URL ''" - which is the home page.
        Config::modify()->set(FormObfuscatorMiddleware::class, 'excluded_url_prefixes', ['', '/']);
        $this->assertStringNotContainsString(
            'action="/x"',
            $this->runMiddleware('', '<form action="/x">')->getBody(),
            'home page treated as excluded by an empty prefix'
        );
    }

    public function testExcludedUrlPrefixesConfigChangesBehaviour()
    {
        Config::modify()->set(FormObfuscatorMiddleware::class, 'excluded_url_prefixes', ['members/area']);

        $this->assertSame(
            '<form action="/x">',
            $this->runMiddleware('members/area/profile', '<form action="/x">')->getBody(),
            'configured prefix not excluded'
        );
        $this->assertStringNotContainsString(
            'action="/x"',
            $this->runMiddleware('admin/pages', '<form action="/x">')->getBody(),
            'default prefix still excluded after the list was replaced'
        );
    }
}
