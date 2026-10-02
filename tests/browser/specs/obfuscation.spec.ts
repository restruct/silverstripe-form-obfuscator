import { test, expect, isFullyEncoded, rawFormActions, submitAndCapture } from './support';

// FormObfuscatorMiddleware: in the HTML as served, every form action is a string of character
// references; the browser decodes them, so the forms keep working.

test('the served HTML carries no plain form action, the browser sees the real one', async ({ page }) => {
    const response = await page.goto('/fob-form');
    expect(response?.status()).toBe(200);

    // What a scraper gets: only character references, no URL.
    const raw = await response!.text();
    const actions = rawFormActions(raw);
    expect(actions, 'two forms on the fixture page').toHaveLength(2);
    for (const a of actions) {
        expect(isFullyEncoded(a), `raw action is fully encoded: ${a}`).toBe(true);
    }
    expect(raw).not.toContain('action="/fob-form/Form"');

    // What the browser makes of it: the decoded URL, query string and all.
    await expect(page.locator('#Form_Form')).toHaveAttribute('action', '/fob-form/Form');
    await expect(page.locator('#Form_QueryForm')).toHaveAttribute('action', '/fob-form/QueryForm?a=1&b=é');
});

test('an obfuscated form submits to its real URL and is handled', async ({ page }) => {
    await page.goto('/fob-form');
    await page.locator('#Form_Form input[name="Name"]').fill('Plain sender');

    const post = await submitAndCapture(page, () => page.locator('#Form_Form_action_doSubmit').click());
    expect(new URL(post.url()).pathname).toBe('/fob-form/Form');

    // The form's own handler ran (it echoes the form name and the posted field).
    const received = page.locator('#fob-received');
    await expect(received).toHaveAttribute('data-form', 'Form');
    await expect(received).toHaveAttribute('data-name', 'Plain sender');
});

test('a query string and a non-ASCII character in the action survive the round trip', async ({ page }) => {
    // The action renders as "...?a=1&amp;b=é". Encoding "&amp;" literally (without decoding it
    // first) would make the browser post to "?a=1&amp;b=...", and "é" byte by byte to "Ã©".
    await page.goto('/fob-form');
    await page.locator('#Form_QueryForm input[name="Name"]').fill('Query sender');

    const post = await submitAndCapture(page, () => page.locator('#Form_QueryForm_action_doSubmit').click());
    const url = new URL(post.url());
    expect(url.pathname).toBe('/fob-form/QueryForm');
    expect(url.searchParams.get('a')).toBe('1');
    expect(url.searchParams.get('b')).toBe('é');
    expect([...url.searchParams.keys()], 'no "amp;b" parameter').toEqual(['a', 'b']);

    const received = page.locator('#fob-received');
    await expect(received).toHaveAttribute('data-form', 'QueryForm');
    await expect(received).toHaveAttribute('data-name', 'Query sender');
    await expect(received).toHaveAttribute('data-a', '1');
    await expect(received).toHaveAttribute('data-b', 'é');
});

test('the login form is obfuscated and logging in through it works', async ({ browser, baseURL }) => {
    // A fresh visitor, not the saved admin session. (The setup project logs in through this same
    // form, so every other spec depends on it too; this one also checks the raw HTML.)
    // An explicit empty storageState: a context created here otherwise picks up the project's saved
    // login (measured: it landed logged in, redirected to /admin/pages).
    const context = await browser.newContext({ baseURL, storageState: { cookies: [], origins: [] } });
    const page = await context.newPage();
    const errors: string[] = [];
    page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
    page.on('pageerror', (e) => errors.push(e.message));

    const response = await page.goto('/Security/login?BackURL=/admin');
    const actions = rawFormActions(await response!.text());
    expect(actions.length, 'the login page has a form').toBeGreaterThan(0);
    for (const a of actions) {
        expect(isFullyEncoded(a), `raw action is fully encoded: ${a}`).toBe(true);
    }

    await page.locator('input[name="Email"]').fill('admin');
    await page.locator('input[name="Password"]').fill('admin');
    const post = await submitAndCapture(page, () => page.locator('[name="action_doLogin"]').click());
    expect(new URL(post.url()).pathname).toMatch(/^\/Security\/login\/default\/LoginForm/);
    await expect(page).toHaveURL(/\/admin/);
    await expect(page.locator('.cms-menu')).toBeVisible();

    expect(errors, 'no console errors on the login round trip').toEqual([]);
    await context.close();
});

test('the CMS is excluded: admin pages keep their plain form actions', async ({ page }) => {
    // excluded_url_prefixes defaults to admin and dev; the CMS's own JS reads form actions.
    // /admin/settings: a section whose edit form is in the served HTML (on /admin/pages the CMS
    // loads it afterwards).
    const response = await page.goto('/admin/settings');
    expect(response?.status()).toBe(200);
    const actions = rawFormActions(await response!.text());
    expect(actions.length, 'the CMS page has forms').toBeGreaterThan(0);
    for (const a of actions) {
        expect(isFullyEncoded(a), `admin action left plain: ${a}`).toBe(false);
    }
    expect(actions.some((a) => a.includes('admin/')), 'an admin form action in plain text').toBe(true);
    await expect(page.locator('.cms-menu')).toBeVisible();
});
