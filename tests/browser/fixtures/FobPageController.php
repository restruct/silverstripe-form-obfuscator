<?php

namespace Restruct\FobBrowser;

use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\TextField;

/**
 * BROWSER-TEST FIXTURE ONLY - controller of FobPage (see there). Renders its own minimal HTML
 * instead of a theme template (the scratch host has no theme), and echoes what a submission
 * delivered, so a spec can tell the browser posted to the real, decoded action URL.
 */
class FobPageController extends ContentController
{
    private static $allowed_actions = ['Form', 'QueryForm'];

    public function index()
    {
        return $this->html(
            '<h1>Obfuscated form</h1>'
            . $this->Form()->forTemplate()
            . $this->QueryForm()->forTemplate()
        );
    }

    /** A plain form: its action is the controller's own Form URL. */
    public function Form()
    {
        return Form::create(
            $this,
            'Form',
            FieldList::create(TextField::create('Name', 'Name')),
            FieldList::create(FormAction::create('doSubmit', 'Send'))
        );
    }

    /**
     * The same form with a query string and a non-ASCII character in its action: rendered as
     * `...?a=1&amp;b=%C3%A9` / `...&b=é`, which the module must decode before it encodes, or the
     * browser posts to a double-encoded URL.
     */
    public function QueryForm()
    {
        $form = Form::create(
            $this,
            'QueryForm',
            FieldList::create(TextField::create('Name', 'Name')),
            FieldList::create(FormAction::create('doSubmit', 'Send with query'))
        );
        $form->setFormAction($this->Link('QueryForm') . '?a=1&b=é');
        $form->setHTMLID('Form_QueryForm');
        return $form;
    }

    public function doSubmit($data, $form)
    {
        # Echo the submission: which form handled it, the posted field and the query string.
        return $this->html(sprintf(
            '<p id="fob-received" data-form="%s" data-name="%s" data-a="%s" data-b="%s">received</p>',
            htmlspecialchars($form->getName()),
            htmlspecialchars((string) ($data['Name'] ?? '')),
            htmlspecialchars((string) $this->getRequest()->getVar('a')),
            htmlspecialchars((string) $this->getRequest()->getVar('b'))
        ));
    }

    /** A text/html response with a minimal document around $body. */
    private function html(string $body): HTTPResponse
    {
        $response = HTTPResponse::create(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><title>fob</title></head><body>'
            . $body . '</body></html>'
        );
        $response->addHeader('Content-Type', 'text/html; charset=utf-8');
        return $response;
    }
}
