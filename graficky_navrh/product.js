(function () {
  'use strict';

  const isMusicProduct = new URLSearchParams(window.location.search).get('product') === 'music';

  const variants = Object.freeze({
    black: Object.freeze({
      label: 'Černá',
      image: '../assets/products/punk-freedom-black.webp',
      unavailableSizes: Object.freeze(['92'])
    }),
    red: Object.freeze({
      label: 'Červená',
      image: '../assets/products/punk-freedom-red.webp',
      unavailableSizes: Object.freeze([])
    })
  });

  const state = { color: 'black', size: '92', cartCount: 0 };
  const image = document.querySelector('#main-product-image');
  const colorButtons = [...document.querySelectorAll('[data-color]')];
  const sizeButtons = [...document.querySelectorAll('[data-size]')];
  const availability = document.querySelector('#availability-message');
  const stockCopy = document.querySelector('#stock-copy');
  const addToCart = document.querySelector('#add-to-cart');
  const cartButton = document.querySelector('.cart-button');
  const cartCount = document.querySelector('.cart-count');
  const toast = document.querySelector('#toast');

  function configureMusicProduct() {
    document.title = 'ŠpuntQaně — Poslední singly — Kafka Monster';
    document.querySelector('meta[name="description"]').content = 'CD ŠpuntQaně — Poslední singly.';
    document.querySelector('#product-title').textContent = 'ŠpuntQaně — Poslední singly';
    document.querySelector('#product-price').textContent = '99 Kč';
    document.querySelector('#product-category-link').textContent = 'Hudba';
    document.querySelector('#product-category-link').href = './index.html#/category/hudba';
    document.querySelector('#breadcrumb-category').textContent = 'Hudba';
    document.querySelector('#breadcrumb-category').href = './index.html#/category/hudba';
    document.querySelector('#breadcrumb-category').setAttribute('aria-current', 'page');
    document.querySelector('#breadcrumb-current-separator').hidden = true;
    document.querySelector('#breadcrumb-subcategory').hidden = true;
    document.querySelector('#breadcrumb-subcategory-separator').hidden = true;
    document.querySelector('#breadcrumb-current').hidden = true;
    image.src = '../assets/products/spuntqane-posledni-singly.webp';
    image.alt = 'CD ŠpuntQaně — Poslední singly';
    document.querySelector('.color-selector').hidden = true;
    document.querySelector('.size-selector').hidden = true;
    document.querySelector('#product-description').textContent = 'Poslední singly kapely ŠpuntQaně na fyzickém CD. Punková nahrávka pro sběratele i do přehrávače.';
    document.querySelector('#product-spec-heading').textContent = 'Formát';
    document.querySelector('#product-spec-copy').textContent = 'Hudební CD v papírovém obalu.';
  }

  function isAvailable() {
    if (isMusicProduct) return true;
    return !variants[state.color].unavailableSizes.includes(state.size);
  }

  function updateGallery() {
    if (isMusicProduct) return;
    const variant = variants[state.color];
    image.src = variant.image;
    image.alt = `${variant.label === 'Černá' ? 'Černé' : 'Červené'} dětské tričko Punk is freedom rock shop`;

    colorButtons.forEach(button => {
      const selected = button.dataset.color === state.color;
      button.classList.toggle('is-selected', selected);
      button.setAttribute('aria-pressed', String(selected));
    });
  }

  function updateAvailability() {
    if (isMusicProduct) {
      availability.classList.remove('is-unavailable');
      availability.classList.add('is-available');
      availability.querySelector('span').textContent = 'Produkt je skladem';
      stockCopy.textContent = 'Skladem • odesíláme do 48 hodin';
      addToCart.disabled = false;
      addToCart.setAttribute('aria-describedby', 'availability-message');
      return;
    }
    const unavailableSizes = variants[state.color].unavailableSizes;
    sizeButtons.forEach(button => {
      const selected = button.dataset.size === state.size;
      const unavailable = unavailableSizes.includes(button.dataset.size);
      button.classList.toggle('is-selected', selected);
      button.classList.toggle('is-unavailable', unavailable);
      button.setAttribute('aria-pressed', String(selected));
      button.setAttribute('aria-label', unavailable
        ? `Velikost ${button.dataset.size}, pro tuto barvu není skladem`
        : `Velikost ${button.dataset.size}, skladem`);
    });

    const available = isAvailable();
    availability.classList.toggle('is-unavailable', !available);
    availability.classList.toggle('is-available', available);
    availability.querySelector('span').textContent = available
      ? 'Vybraná kombinace je skladem'
      : 'Tato kombinace není skladem';
    stockCopy.textContent = available
      ? 'Skladem • odesíláme do 48 hodin'
      : 'Není skladem v této kombinaci';
    addToCart.disabled = !available;
    addToCart.setAttribute('aria-describedby', 'availability-message');
  }

  function selectColor(color) {
    state.color = color;
    updateGallery();
    updateAvailability();
  }

  colorButtons.forEach(button => button.addEventListener('click', () => selectColor(button.dataset.color)));
  sizeButtons.forEach(button => button.addEventListener('click', () => {
    state.size = button.dataset.size;
    updateAvailability();
  }));

  addToCart.addEventListener('click', () => {
    if (!isAvailable()) return;
    state.cartCount += 1;
    cartCount.hidden = false;
    cartCount.textContent = state.cartCount;
    cartButton.setAttribute('aria-label', `Košík, ${state.cartCount} položek`);
    toast.textContent = isMusicProduct
      ? 'CD ŠpuntQaně — přidáno do košíku'
      : `${variants[state.color].label}, velikost ${state.size} — přidáno do košíku`;
    toast.classList.add('is-visible');
    window.clearTimeout(addToCart.toastTimer);
    addToCart.toastTimer = window.setTimeout(() => toast.classList.remove('is-visible'), 2200);
  });

  const menuToggle = document.querySelector('#menu-toggle');
  const mainNav = document.querySelector('#main-nav');
  menuToggle.addEventListener('click', () => {
    const open = menuToggle.getAttribute('aria-expanded') === 'true';
    menuToggle.setAttribute('aria-expanded', String(!open));
    menuToggle.setAttribute('aria-label', open ? 'Otevřít menu' : 'Zavřít menu');
    mainNav.classList.toggle('is-open', !open);
  });

  const themeControls = [...document.querySelectorAll('[data-theme-select]')];
  const syncThemeControls = theme => themeControls.forEach(control => { control.value = theme; });
  syncThemeControls(window.kafkaTheme.getTheme());
  themeControls.forEach(control => control.addEventListener('change', event => {
    window.kafkaTheme.setTheme(event.currentTarget.value);
  }));
  window.addEventListener('kafka:themechange', event => syncThemeControls(event.detail.theme));

  if (isMusicProduct) configureMusicProduct();
  updateGallery();
  updateAvailability();
})();
