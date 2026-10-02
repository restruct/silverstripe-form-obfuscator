import { test as base, expect, type Page, type Request } from '@playwright/test';

// Shared fixtures and helpers for the form-obfuscator specs.
//
// The front-end form is the fixture page /fob-form (tests/browser/fixtures/FobPage*.php, copied
// into the scratch host by the runner): two forms, one with a query string and a non-ASCII
// character in its action, and a submit handler that echoes what it received.

/**
 * test, extended with an automatic console guard: every spec fails if the page logs a console
 * error or throws an uncaught exception at any point. Warnings do not count.
 */
export const test = base.extend<{ consoleGuard: void }>({
    consoleGuard: [
        async ({ page }, use, testInfo) => {
            const errors: string[] = [];
            page.on('console', (msg) => {
                if (msg.type() === 'error') {
                    errors.push(`console.error: ${msg.text()} (${msg.location().url})`);
                }
            });
            page.on('pageerror', (err) => errors.push(`uncaught: ${err.message}`));

            await use();

            if (errors.length) {
                await testInfo.attach('console-errors', { body: errors.join('\n'), contentType: 'text/plain' });
            }
            expect(errors, 'no console errors or uncaught exceptions').toEqual([]);
        },
        { auto: true },
    ],
});

export { expect };

/** Every raw (as served, before the browser decodes it) action="..." value of the form tags. */
export function rawFormActions(html: string): string[] {
    return [...html.matchAll(/<form\b[^>]*\saction="([^"]*)"/gi)].map((m) => m[1]);
}

/** True when an attribute value is made of numeric character references only (&#47;&#x66;...). */
export function isFullyEncoded(value: string): boolean {
    return value.length > 0 && /^(&#\d+;|&#x[0-9a-f]+;)+$/i.test(value);
}

/**
 * Click a submit button and return the main-frame document POST it caused. The obfuscated form
 * must still be an ordinary form submission to the real URL.
 */
export async function submitAndCapture(page: Page, click: () => Promise<void>): Promise<Request> {
    const posted = page.waitForRequest((r) => r.isNavigationRequest() && r.method() === 'POST' && r.frame() === page.mainFrame());
    await click();
    return posted;
}
