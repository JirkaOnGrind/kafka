// Real native interactions only, isolated 8083. No order is placed; each test removes its cart line.
const fs = require('node:fs');
const path = require('node:path');
const playwright = process.env.KAFKA_PLAYWRIGHT_PATH || 'C:/Users/Utrh/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright';
const { chromium } = require(playwright);
const output = path.resolve(process.argv[2] || '');
if (!process.argv[2] || !fs.existsSync(output)) throw new Error('Supply an existing dated evidence folder.');
const report = { started: new Date().toISOString(), target: 'http://localhost:8083/', browser: 'Chrome / Playwright',
  fallback: 'Browser plugin not available; existing bundled Playwright used without installing dependencies',
  viewports: [{ width: 1536, height: 1024 }, { width: 390, height: 844 }], checks: [], errors: [], consoleErrors: [], httpErrors: [], externalBlocked: [], screenshots: [] };
let browser;
function check(name, passed, detail) {
  report.checks.push({ name, passed: !!passed, detail });
  if (!passed) throw new Error(name);
}
async function run() {
  browser = await chromium.launch({ executablePath: process.env.KAFKA_CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
  for (const [index, viewport] of report.viewports.entries()) {
    const label = index ? 'mobile' : 'desktop';
    const context = await browser.newContext({ viewport, deviceScaleFactor: 1 });
    await context.route('**/*', route => {
      const url = new URL(route.request().url());
      if (['localhost', '127.0.0.1', 'fonts.googleapis.com', 'fonts.gstatic.com', 'cdn.tailwindcss.com'].includes(url.hostname)) return route.continue();
      report.externalBlocked.push(url.origin + url.pathname);
      return route.abort();
    });
    const page = await context.newPage();
    page.on('pageerror', e => report.errors.push({ label, message: e.message }));
    page.on('console', m => { if (m.type() === 'error' && page.url().startsWith('http://localhost:8083/')) report.consoleErrors.push({ label, message: m.text() }); });
    page.on('response', r => { if (r.status() >= 400 && new URL(r.url()).port === '8083') report.httpErrors.push({ label, status: r.status(), path: new URL(r.url()).pathname }); });
    const go = async url => {
      const response = await page.goto(url, { waitUntil: 'load', timeout: 90000 });
      check(label + ' page HTTP 200', response.status() === 200, new URL(page.url()).pathname);
      await page.locator('main').first().waitFor();
      await page.evaluate(async () => { await document.fonts.ready; scrollTo(0, 0); });
    };
    const shot = async name => { const filename = name + '-' + label + '.png'; await page.screenshot({ path: path.join(output, filename), fullPage: false }); report.screenshots.push(filename); };
    await go('http://localhost:8083/cs/');
    check(label + ' homepage title', await page.title() === 'Kafka Monster');
    check(label + ' six category cards', await page.locator('.kafka-category-card').count() === 6);
    check(label + ' theme picker absent', await page.locator('.theme-control,[data-theme-picker],#theme-select').count() === 0);
    check(label + ' nonblank', (await page.locator('main').innerText()).length > 100 && await page.locator('.kafka-category-primary').count() === 6);
    await shot('home');
    const product = 'http://localhost:8083/cs/detska-tricka/3454-tricko-punk-is-freedom-rock-shop.html';
    await go(product);
    check(label + ' product identity', (await page.title()) === 'Tričko Punk Is Freedom - Kafka Monster' && page.url() === product);
    check(label + ' baseline price', /259,00\s*Kč/.test(await page.locator('main').innerText()));
    check(label + ' product images load', await page.locator('main img').evaluateAll(images => images.filter(e => { const r = e.getBoundingClientRect(); return r.width && r.height && r.top < innerHeight && r.bottom > 0; }).every(e => e.complete && e.naturalWidth > 0)));
    await page.locator('select[name="group[2]"]').selectOption('16');
    await page.waitForFunction(() => /269,00\s*Kč/.test(document.querySelector('main').innerText));
    check(label + ' native red price update', true);
    await page.locator('select[name="group[10]"]').selectOption('174');
    await page.waitForFunction(() => /299,00\s*Kč/.test(document.querySelector('main').innerText));
    check(label + ' native size price update', true);
    const combinationUrl = page.url();
    check(label + ' variant URL retains original product path', new URL(combinationUrl).pathname === new URL(product).pathname && !!new URL(combinationUrl).searchParams.get('id_product_attribute'), combinationUrl);
    await page.reload({ waitUntil: 'load' });
    check(label + ' selected variant survives reload', await page.locator('select[name="group[2]"]').inputValue() === '16' && await page.locator('select[name="group[10]"]').inputValue() === '174' && /299,00\s*Kč/.test(await page.locator('main').innerText()));
    const selectedAttribute = new URL(combinationUrl).searchParams.get('id_product_attribute');
    await go('http://localhost:8083/cs/3454-' + selectedAttribute + '-tricko-punk-is-freedom-rock-shop.html');
    check(label + ' old flat variant path preserves selection', new URL(page.url()).searchParams.get('id_product_attribute') === selectedAttribute
      && await page.locator('select[name="group[2]"]').inputValue() === '16' && await page.locator('select[name="group[10]"]').inputValue() === '174'
      && /299,00\s*Kč/.test(await page.locator('main').innerText()));
    check(label + ' canonical excludes variant query', await page.locator('link[rel="canonical"]').getAttribute('href') === product);
    check(label + ' responsive width', await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2));
    check(label + ' no framework overlay', await page.locator('vite-error-overlay,nextjs-portal').count() === 0);
    await shot('variant');
    await page.locator('.product__add-to-cart-button').click();
    await page.waitForFunction(() => document.body.innerText.includes('úspěšně') || document.querySelector('#blockcart-modal.show,.modal.show'));
    await go('http://localhost:8083/cs/cart?action=show');
    const cart = await page.locator('main').innerText();
    check(label + ' correct real cart line', cart.includes('Tričko Punk Is Freedom') && cart.includes('Červená') && cart.includes('164') && cart.includes('299,00'));
    check(label + ' Czech cart title', await page.title() === 'Košík - Kafka Monster');
    check(label + ' cart responsive width', await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2));
    await shot('cart');
    // Remove only this fresh browser session's own line, using its native action link.
    const remove = page.locator('[data-link-action="delete-from-cart"]').first();
    await remove.click();
    await page.waitForFunction(() => !document.querySelector('[data-link-action="delete-from-cart"]'));
    check(label + ' own test cart cleaned', await page.locator('[data-link-action="delete-from-cart"]').count() === 0);
    await go('http://localhost:8085/?theme=black');
    await page.locator('.hero').waitFor();
    await shot('reference-home');
    await context.close();
  }
  check('JavaScript runtime health', report.errors.length === 0, report.errors);
  check('native console health', report.consoleErrors.length === 0, report.consoleErrors);
  check('native HTTP health', report.httpErrors.length === 0, report.httpErrors);
  report.visualNotes = 'Native theme and prototype remain unchanged. Content differs because the native page uses the existing imported catalog. SEO work changed no theme CSS, layout, category cards or registration block.';
}
run().catch(e => { report.failure = e.message; process.exitCode = 1; }).finally(async () => {
  report.finished = new Date().toISOString(); report.passed = !report.failure;
  fs.writeFileSync(path.join(output, 'browser-qa.json'), JSON.stringify(report, null, 2));
  console.log(JSON.stringify({ passed: report.passed, checks: report.checks.length, failure: report.failure, errors: report.errors.length, httpErrors: report.httpErrors.length }));
  if (browser) await browser.close();
});
