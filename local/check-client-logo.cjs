const { chromium } = require('C:/Users/Utrh/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
(async () => {
  const browser = await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
  const page = await browser.newPage();
  for (const [name,width,height] of [['desktop',1536,1024],['mobile',390,844]]) {
    await page.setViewportSize({width,height});
    await page.goto('http://localhost:4173/',{waitUntil:'networkidle'});
    await page.screenshot({path:`local/logo-reference-${name}.png`});
    await page.goto('http://localhost:8080/',{waitUntil:'networkidle'});
    const logo=page.locator('.kafka-brand img');
    console.log(name, await logo.evaluate(img=>({loaded:img.complete&&img.naturalWidth>0,src:img.getAttribute('src'),width:img.getBoundingClientRect().width,height:img.getBoundingClientRect().height})), await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
    await page.screenshot({path:`local/logo-shop-${name}.png`});
    await page.locator('.kafka-brand').click();
    console.log('home link',page.url());
  }
  await page.setViewportSize({width:588,height:438});
  await page.setContent('<body style="margin:0;background:#111214"><img src="http://localhost:8080/themes/kafka/assets/img/kafka-monster-client-logo.svg" style="width:588px;height:438px"></body>');
  await page.locator('img').evaluate(img=>img.decode());
  await page.screenshot({path:'local/logo-preview.png'});
  await browser.close();
})().catch(e=>{console.error(e.message);process.exit(1)});

