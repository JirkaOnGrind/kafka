const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');

const { resolve } = require('node:path');
const projectRoot = resolve(__dirname, '../..');
const python = process.platform === 'win32' ? 'python' : 'python3';
execFileSync(python, [resolve(projectRoot, 'scripts/check-product-images.py')], { stdio: 'inherit' });

(async () => {
  const browser = await chromium.launch({
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    headless: true
  });
  const page = await browser.newPage({ viewport: { width: 1536, height: 1024 }, deviceScaleFactor: 1 });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('response', response => {
    if (response.url().startsWith('http://127.0.0.1:4173') && response.status() >= 400) {
      errors.push(`${response.status()} ${response.url()}`);
    }
  });
  page.on('requestfailed', request => {
    if (request.url().startsWith('http://127.0.0.1:4173')) errors.push(`Request failed: ${request.url()}`);
  });
  await page.goto('http://127.0.0.1:4173/product.html', { waitUntil: 'domcontentloaded' });
  const expectedNavHrefs = [
    './index.html#/category/obleceni',
    './index.html#/category/doplnky',
    './index.html#/category/merche',
    './index.html#/category/hudba',
    './index.html#/category/vyprodej',
    './index.html#/info/custom-print'
  ];
  const navHrefs = await page.locator('#main-nav a').evaluateAll(links =>
    links.map(link => link.getAttribute('href'))
  );
  if (JSON.stringify(navHrefs) !== JSON.stringify(expectedNavHrefs)) {
    throw new Error(`Product navigation routes are inconsistent: ${JSON.stringify(navHrefs)}`);
  }
  if ((await page.locator('.header-wordmark').innerText()).replace(/\s+/g, ' ').trim() !== 'KAFKA MONSTER') {
    throw new Error('Product header wordmark does not match the homepage');
  }
  if (await page.locator('.search').count()) {
    throw new Error('Legacy product-header search is still rendered');
  }
  await page.locator('img').evaluateAll(images => Promise.all(images.map(image => image.complete
    ? Promise.resolve()
    : new Promise(resolve => image.addEventListener('load', resolve, { once: true })))));

  if (!(await page.locator('#add-to-cart').isDisabled())) throw new Error('Black size 92 must block purchase');
  await page.locator('[data-color="red"]').click();
  await page.locator('#add-to-cart').waitFor({ state: 'visible' });
  if (await page.locator('#add-to-cart').isDisabled()) throw new Error('Red size 92 must be purchasable');
  if (!((await page.locator('#main-product-image').getAttribute('src')) || '').includes('red')) throw new Error('Gallery did not switch to red');
  await page.locator('#add-to-cart').click();
  if ((await page.locator('.cart-count').textContent()) !== '1') throw new Error('Cart count did not increment');
  await page.locator('[data-color="black"]').click();
  if (!(await page.locator('#add-to-cart').isDisabled())) throw new Error('Returning to black size 92 must block purchase');
  await page.waitForTimeout(2400);
  await page.screenshot({ path: resolve(projectRoot, 'design/rendered-pdp-desktop.png'), fullPage: false });

  await page.setViewportSize({ width: 390, height: 844 });
  await page.reload({ waitUntil: 'domcontentloaded' });
  await page.locator('img').evaluateAll(images => Promise.all(images.map(image => image.complete
    ? Promise.resolve()
    : new Promise(resolve => image.addEventListener('load', resolve, { once: true })))));
  const bodyWidth = await page.locator('body').evaluate(element => element.scrollWidth);
  if (bodyWidth > 390) throw new Error(`Mobile horizontal overflow: ${bodyWidth}px`);
  await page.screenshot({ path: resolve(projectRoot, 'design/rendered-pdp-mobile.png'), fullPage: true });

  await page.goto('http://127.0.0.1:4173/product.html?product=music', { waitUntil: 'domcontentloaded' });
  const musicBreadcrumb = (await page.locator('.breadcrumbs').innerText()).replace(/\s+/g, ' ').trim();
  if (musicBreadcrumb !== 'Domů / Hudba') {
    throw new Error(`Unexpected music breadcrumb: ${musicBreadcrumb}`);
  }

  await browser.close();
  if (errors.length) throw new Error(`Browser errors:\n${errors.join('\n')}`);
  console.log('PDP QA passed: variants, availability, cart, console and 390px overflow checks.');
})();
