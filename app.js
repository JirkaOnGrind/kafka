(function () {
  'use strict';

  const { categories, routes } = window.kafkaCatalog;
  const navList = document.querySelector('#main-nav ul');
  const categoryGrid = document.querySelector('#kategorie .grid');
  const main = document.querySelector('#obsah');
  const hero = document.querySelector('.hero');
  const categoriesSection = document.querySelector('#kategorie');

  function escapeHtml(value) {
    return String(value).replace(/[&<>'"]/g, character => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    })[character]);
  }

  function renderNavigation() {
    navList.innerHTML = categories.map(category => `
      <li><a class="main-nav-link focus-ring" href="${category.route}">${escapeHtml(category.name)}</a></li>
    `).join('');
  }

  function renderItem(item, className) {
    return `<a class="${className}" href="${escapeHtml(item.url)}">${escapeHtml(item.label)} <span aria-hidden="true">→</span></a>`;
  }

  function renderCategoryCard(category) {
    const icon = `<div class="icon-frame grid aspect-[16/9] place-items-center border-b border-line transition"><svg class="category-icon punk-icon h-36 w-56 transition duration-300" viewBox="0 0 240 150" aria-hidden="true"><use href="#${category.icon}"/></svg></div>`;
    const title = `<h3 class="display text-2xl font-semibold uppercase">${escapeHtml(category.name)}</h3>`;

    if (category.items.length <= 1) {
      return `
        <article class="category-card overflow-hidden border border-line bg-raised" data-category-kind="${category.kind}" data-category-density="sparse">
          <a href="${escapeHtml(category.archiveUrl)}" class="focus-ring flex h-full flex-col" aria-label="${escapeHtml(category.name)} — otevřít kategorii">
            ${icon}
            <div class="category-card-body p-6">
              ${title}
              <span class="category-sparse-cta" aria-hidden="true">Vstoupit ➔</span>
            </div>
          </a>
        </article>`;
    }

    const listId = `category-items-${category.id}`;
    const teaserItems = category.items.map((item, index) => `<li${index >= 3 ? ' data-category-extra hidden' : ''}>${renderItem(item, 'focus-ring flex justify-between py-2.5 hover:text-rebel')}</li>`).join('');
    const teaserList = `<ul id="${listId}" class="category-teaser-list mt-4 flex flex-1 flex-col divide-y divide-line text-sm text-muted">${teaserItems}<li class="category-accordion-tail"><button type="button" class="category-accordion-toggle focus-ring" data-category-toggle aria-expanded="false" aria-controls="${listId}" aria-label="Zobrazit všechny podkategorie ${escapeHtml(category.name)}"><span aria-hidden="true">▼</span></button></li></ul>`;

    return `
      <article class="category-card overflow-hidden border border-line bg-raised" data-category-kind="${category.kind}" data-category-density="dense">
        ${icon}
        <div class="category-card-body p-6">
          ${title}
          ${teaserList}
        </div>
      </article>`;
  }

  function renderCategories() {
    categoryGrid.innerHTML = categories.map(renderCategoryCard).join('');
  }

  function toggleCategory(button) {
    const expanded = button.getAttribute('aria-expanded') === 'true';
    const nextExpanded = !expanded;
    const card = button.closest('.category-card');

    card.querySelectorAll('[data-category-extra]').forEach(item => {
      item.hidden = !nextExpanded;
    });
    button.setAttribute('aria-expanded', String(nextExpanded));
    button.setAttribute('aria-label', `${nextExpanded ? 'Skrýt rozšířené' : 'Zobrazit všechny'} podkategorie ${card.querySelector('h3').textContent}`);
  }

  function getRoute() {
    return window.location.hash.slice(1).split('?')[0];
  }

  function renderRoute() {
    const route = getRoute();
    const category = routes[route];
    let routeView = document.querySelector('#route-view');

    if (!category) {
      if (routeView) routeView.remove();
      hero.hidden = false;
      categoriesSection.hidden = false;
      return;
    }

    hero.hidden = true;
    categoriesSection.hidden = true;
    if (!routeView) {
      routeView = document.createElement('section');
      routeView.id = 'route-view';
      routeView.className = 'panel p-6 sm:p-10';
      main.append(routeView);
    }

    if (category.kind === 'information') {
      routeView.innerHTML = `
        <a class="focus-ring text-xs font-bold uppercase tracking-wider text-rebel" href="#kategorie">← Zpět na kategorie</a>
        <div class="mt-8 grid gap-10 lg:grid-cols-[1.05fr_.95fr]">
          <div>
            <p class="text-xs font-bold uppercase tracking-[.2em] text-rebel">Sítotisk na zakázku</p>
            <h1 class="display mt-3 text-5xl font-bold uppercase leading-none sm:text-6xl">Vlastní potisk</h1>
            <p class="mt-6 max-w-xl text-base leading-relaxed text-muted">Ceník a podklady pro vlastní motiv. Tahle stránka je informační služba — nezobrazuje produktový feed ani skladové položky.</p>
            <ol class="mt-8 space-y-4 text-sm"><li><strong>01 / Pošli motiv</strong><br><span class="text-muted">Vektor nebo bitmapa alespoň 300 DPI.</span></li><li><strong>02 / Potvrdíme kalkulaci</strong><br><span class="text-muted">Cena se odvíjí od počtu barev, kusů a textilu.</span></li><li><strong>03 / Tiskneme</strong><br><span class="text-muted">Ruční sítotisk s kontrolou každého kusu.</span></li></ol>
            <div class="mt-10 grid gap-3 sm:grid-cols-2" aria-label="Služby z živého katalogu">${category.items.map(item => renderItem(item, 'focus-ring border border-line bg-raised p-4 text-sm hover:border-rebel hover:text-rebel')).join('')}</div>
          </div>
          <div class="space-y-3" aria-label="Orientační ceník">
            <h2 class="display text-3xl font-semibold uppercase">Orientační ceník</h2>
            <div class="route-price bg-raised p-5"><strong>1–9 kusů</strong><span class="float-right text-rebel">od 290 Kč / ks</span><p class="mt-2 text-xs text-muted">Jednobarevný tisk, vlastní textil.</p></div>
            <div class="route-price bg-raised p-5"><strong>10–29 kusů</strong><span class="float-right text-rebel">od 190 Kč / ks</span><p class="mt-2 text-xs text-muted">Jednobarevný tisk, vlastní textil.</p></div>
            <div class="route-price bg-raised p-5"><strong>30+ kusů</strong><span class="float-right text-rebel">na kalkulaci</span><p class="mt-2 text-xs text-muted">Více barev a dodání textilu naceníme zvlášť.</p></div>
          </div>
        </div>`;
    } else {
      routeView.innerHTML = `
        <a class="focus-ring text-xs font-bold uppercase tracking-wider text-rebel" href="#kategorie">← Zpět na kategorie</a>
        <h1 class="display mt-8 text-5xl font-bold uppercase sm:text-6xl">${escapeHtml(category.name)}</h1>
        <p class="mt-4 max-w-2xl text-muted">Produktová kategorie. Vyber podkategorii a pokračuj do výpisu produktů.</p>
        <div class="mt-10 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">${category.items.map(item => renderItem(item, 'focus-ring border border-line bg-raised p-5 font-semibold hover:border-rebel hover:text-rebel')).join('')}</div>`;
    }
    routeView.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  }

  renderNavigation();
  renderCategories();
  renderRoute();
  categoryGrid.addEventListener('click', event => {
    const button = event.target.closest('[data-category-toggle]');
    if (button) toggleCategory(button);
  });
  window.addEventListener('hashchange', renderRoute);
})();
