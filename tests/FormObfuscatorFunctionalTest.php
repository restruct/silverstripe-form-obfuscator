<?php

namespace Restruct\FormObfuscator\Tests;

use Restruct\FormObfuscator\FormObfuscatorMiddleware;
use Restruct\FormObfuscator\Tests\Stub\ObfuscatorTestController;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;

/**
 * End to end through Director: a real Form rendered by a real controller, with the module's own
 * YAML registration doing the wiring (nothing is added to the middleware list here).
 */
class FormObfuscatorFunctionalTest extends FunctionalTest
{
    protected $usesDatabase = false;

    protected static $extra_controllers = [
        ObfuscatorTestController::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Config::modify()->merge(Director::class, 'rules', [
            'formobfuscator-test//$Action' => ObfuscatorTestController::class,
        ]);
    }

    public function testRenderedFormActionIsObfuscated()
    {
        $response = $this->get('formobfuscator-test');
        $this->assertSame(200, $response->getStatusCode());

        $body = $response->getBody();
        $this->assertStringContainsString('<form', $body, 'the stub did not render a form');
        $this->assertStringNotContainsString('formobfuscator-test/Form', $body, 'form action left in plain text');

        $this->assertSame(1, preg_match('@<form[^>]*\saction="([^"]*)"@', $body, $m));
        $decoded = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringEndsWith('formobfuscator-test/Form', $decoded, 'decoded action is not the form URL');
    }

    public function testNonHtmlResponseIsUntouched()
    {
        $body = $this->get('formobfuscator-test/plain')->getBody();
        $this->assertSame('<form action="/formobfuscator-test/Form">', $body);
    }

    public function testConfiguredExclusionAppliesThroughTheStack()
    {
        Config::modify()->set(FormObfuscatorMiddleware::class, 'excluded_url_prefixes', ['formobfuscator-test']);
        $body = $this->get('formobfuscator-test')->getBody();
        $this->assertStringContainsString('formobfuscator-test/Form', $body, 'excluded URL was obfuscated');
    }
}
