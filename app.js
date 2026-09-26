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

  function renderItem(item, className, showArrow = true) {
    const arrow = showArrow ? ' <span aria-hidden="true">→</span>' : '';
    return `<a class="${className}" href="${escapeHtml(item.url)}">${escapeHtml(item.label)}${arrow}</a>`;
  }

  function renderCategoryCard(category) {
    const listId = `category-items-${category.id}`;
    const subcategoryCount = category.items.length;
    const hasSubcategories = subcategoryCount > 0;
    const hasOverflow = subcategoryCount > 3;
    const icon = `<div class="icon-frame grid aspect-[16/9] place-items-center border-b border-line"><svg class="category-icon punk-icon h-36 w-56" viewBox="0 0 240 150" aria-hidden="true"><use href="#${category.icon}"/></svg></div>`;
    const teaserItems = category.items.map((item, index) => `<li${index >= 3 ? ' data-category-extra' : ''}>${renderItem(item, 'focus-ring flex justify-between py-2.5', hasOverflow)}</li>`).join('');
    const teaserList = hasSubcategories
      ? `<ul id="${listId}" class="category-teaser-list flex flex-col divide-y divide-line text-sm text-muted">${teaserItems}</ul>`
      : '';
    const entry = subcategoryCount <= 3
      ? `<a class="category-entry focus-ring" href="${escapeHtml(category.route)}">Vstoupit</a>`
      : '';
    const toggle = hasSubcategories
      ? `<button type="button" class="category-accordion-toggle focus-ring" data-category-toggle aria-expanded="false" aria-controls="${listId}" aria-label="Zobrazit podkategorie ${escapeHtml(category.name)}"><span aria-hidden="true">▼</span></button>`
      : '';

    return `
      <article class="category-card overflow-hidden border border-line bg-raised" data-category-id="${category.id}" data-category-kind="${category.kind}" data-subcategory-count="${subcategoryCount}" data-subcategory-overflow="${hasOverflow}" style="--mobile-order:${category.mobileOrder}">
        <a href="${escapeHtml(category.route)}" class="category-primary focus-ring" aria-label="${escapeHtml(category.name)} — otevřít kategorii">
          ${icon}
          <div class="category-heading">
            <h3 class="display text-2xl font-semibold uppercase">${escapeHtml(category.name)}</h3>
          </div>
        </a>
        <div class="category-disclosure">
          ${teaserList}
        </div>
        <div class="category-trigger-slot">${entry}${toggle}</div>
      </article>`;
  }

  function renderCategories() {
    categoryGrid.innerHTML = categories.map(renderCategoryCard).join('');
  }

  function toggleCategory(button) {
    const expanded = button.getAttribute('aria-expanded') === 'true';
    const nextExpanded = !expanded;
    const card = button.closest('.category-card');
    card.classList.toggle('is-expanded', nextExpanded);
    button.setAttribute('aria-expanded', String(nextExpanded));
    button.setAttribute('aria-label', `${nextExpanded ? 'Skrýt' : 'Zobrazit'} podkategorie ${card.querySelector('h3').textContent}`);
  }

  function renderProductCard(product) {
    const saleFlag = product.discount
      ? `<strong class="product-sale-flag">Výprodej ${escapeHtml(product.discount)}</strong>`
      : '';
    const originalPrice = product.originalPrice
      ? `<del>${escapeHtml(product.originalPrice)}</del>`
      : '';

    return `
      <article class="product-card">
        <a class="product-card-link focus-ring" href="${escapeHtml(product.href)}">
          <div class="product-media">
            ${saleFlag}
            <img src="${escapeHtml(product.image)}" alt="${escapeHtml(product.alt)}">
          </div>
          <div class="product-copy">
            <p>${escapeHtml(product.type)}</p>
            <h2 class="display">${escapeHtml(product.name)}</h2>
            <div class="product-price">${originalPrice}<strong>${escapeHtml(product.price)}</strong></div>
            <span class="product-action">Detail produktu</span>
          </div>
        </a>
      </article>`;
  }

  function renderHub(category) {
    return `
      <a class="route-back focus-ring" href="#kategorie">← Zpět na kategorie</a>
      <header class="route-header">
        <h1 class="display">${escapeHtml(category.name)}</h1>
      </header>
      <ol class="subcategory-list">${category.items.map((item, index) => `
        <li>
          <a class="subcategory-link focus-ring" href="${escapeHtml(item.url)}">
            <span class="subcategory-number" aria-hidden="true">${String(index + 1).padStart(2, '0')}</span>
            <strong class="display">${escapeHtml(item.label)}</strong>
          </a>
        </li>`).join('')}</ol>`;
  }

  function renderProductList(category) {
    return `
      <a class="route-back focus-ring" href="#kategorie">← Zpět na kategorie</a>
      <header class="route-header">
        <h1 class="display">${escapeHtml(category.name)}</h1>
      </header>
      <div class="product-grid">${category.products.map(renderProductCard).join('')}</div>`;
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
    } else if (category.hasSubcategories) {
      routeView.innerHTML = renderHub(category);
    } else {
      routeView.innerHTML = renderProductList(category);
    }
    routeView.scrollIntoView({ behavior: 'auto' });
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
