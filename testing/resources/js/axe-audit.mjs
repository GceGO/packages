// Runs axe-core against one page in headless Chromium and prints the
// violations as JSON. Called by Gcego\Testing\Accessibility\BrowserAccessibility.
//
// Input (stdin, JSON): { modules, axe, url?, html?, tags?, timeout }
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';

const input = JSON.parse(readFileSync(0, 'utf8'));
const require = createRequire(input.modules + '/');
const { chromium } = require('playwright');

const browser = await chromium.launch();

try {
    const page = await browser.newPage();
    page.setDefaultTimeout(input.timeout);

    if (input.url) {
        await page.goto(input.url, { waitUntil: 'load' });
    } else {
        await page.setContent(input.html, { waitUntil: 'load' });
    }

    await page.addScriptTag({ path: input.axe });

    const result = await page.evaluate(async (tags) => {
        const options = tags && tags.length ? { runOnly: { type: 'tag', values: tags } } : {};
        const run = await window.axe.run(document, options);

        return run.violations.map((violation) => ({
            rule: violation.id,
            message: violation.help,
            impact: violation.impact ?? 'serious',
            elements: violation.nodes.map((node) => node.html),
        }));
    }, input.tags ?? []);

    process.stdout.write(JSON.stringify({ violations: result }));
} finally {
    await browser.close();
}
