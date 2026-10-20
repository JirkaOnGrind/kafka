const { chromium } = require('C:/Users/Utrh/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs = require('node:fs');
(async () => {
  const phase = process.argv[2] || 'before';
  const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
  const results = [];
  for (const width of [390, 320, 767, 768, 1536]) {
    const page = await browser.newPage({ viewport: { width, height: width > 767 ? 1024 : 844 } });
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
    const response = await page.goto('http://localhost:8083/cs/', { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    const data = await page.evaluate(() => {
      const hero = document.querySelector('.kafka-hero');
      const cta = document.querySelector('.kafka-hero-cta');
      const rect = el => { const r = el.getBoundingClientRect(); return { x:r.x, y:r.y, width:r.width, height:r.height }; };
      return { title:document.title, trust: getComputedStyle(document.querySelector('.kafka-trust-bar')).display, hero:rect(hero), cta:rect(cta), heroBackground:getComputedStyle(hero, '::before').backgroundImage, overflow:document.documentElement.scrollWidth > innerWidth };
    });
    await page.screenshot({ path: `${__dirname}/${phase}-${width}.png` });
    await page.locator('.kafka-hero-cta').click();
    await page.waitForTimeout(800);
    const jump = await page.evaluate(() => ({hash:location.hash, top:document.querySelector('#kategorie').getBoundingClientRect().top}));
    results.push({ width, status:response.status(), noindex:response.headers()['x-robots-tag'], url:page.url(), ...data, jump, errors });
    await page.close();
  }
  if (phase === 'before') {
    for (const width of [390,1536]) {
      const p = await browser.newPage({ viewport:{width,height:width===390?844:1024} });
      await p.goto('http://localhost:4173/', {waitUntil:'networkidle'});
      await p.screenshot({path:`${__dirname}/reference-${width}.png`});
      await p.close();
    }
  }
  fs.writeFileSync(`${__dirname}/${phase}.json`, JSON.stringify(results,null,2));
  console.log(JSON.stringify(results));
  await browser.close();
})().catch(e=>{ console.error(e); process.exit(1); });
