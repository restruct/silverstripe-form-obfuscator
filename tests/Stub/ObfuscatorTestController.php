<?php

namespace Restruct\FormObfuscator\Tests\Stub;

use SilverStripe\Control\Controller;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\TextField;

/**
 * Test-only controller that renders a real Silverstripe Form, so the functional test sees the
 * markup the framework actually produces rather than a hand-written string.
 * Routed by FormObfuscatorFunctionalTest::setUp() under 'formobfuscator-test'.
 */
class ObfuscatorTestController extends Controller implements TestOnly
{
    private static $allowed_actions = [
        'Form',
        'plain',
    ];

    public function Link($action = null)
    {
        return Controller::join_links('formobfuscator-test', $action);
    }

    public function index()
    {
        # Returned as a string, so the controller wraps it in a response with the default
        # text/html content type - the case the middleware is meant to act on.
        return '<!DOCTYPE html><html><body>' . $this->Form()->forTemplate() . '</body></html>';
    }

    public function plain()
    {
        # Same form markup, but not HTML: must pass through untouched. Plain text rather than
        # JSON, because json_encode() escapes the quotes and the markup would never match anyway.
        $this->getResponse()->addHeader('Content-Type', 'text/plain; charset=utf-8');
        return '<form action="/formobfuscator-test/Form">';
    }

    public function Form()
    {
        return Form::create(
            $this,
            'Form',
            FieldList::create(TextField::create('Name')),
            FieldList::create(FormAction::create('doSubmit', 'Go'))
        );
    }
}
