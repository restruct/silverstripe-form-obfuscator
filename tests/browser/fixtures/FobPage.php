<?php

namespace Restruct\FobBrowser;

use SilverStripe\CMS\Model\SiteTree;

/**
 * BROWSER-TEST FIXTURE ONLY - a published front-end page at /fob-form whose controller
 * (FobPageController) renders a plain form, so the specs can submit an obfuscated form in a real
 * browser.
 *
 * Never loaded by a real install: it lives under tests/browser/, which carries a _manifest_exclude
 * marker, and the browser-test runner copies it into a scratch host's app/ before dev/build.
 * Written to load on both Silverstripe 5 and 6. Extends SiteTree, not Page: the scratch host has no
 * app Page class.
 */
class FobPage extends SiteTree
{
    # Short table name: no namespaced default.
    private static $table_name = 'FobPage';

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        # One published page at /fob-form; created once, left alone on later builds.
        if (!static::get()->filter('URLSegment', 'fob-form')->exists()) {
            $page = static::create(['Title' => 'Obfuscated form', 'URLSegment' => 'fob-form']);
            $page->write();
            $page->publishRecursive();
        }
    }
}
