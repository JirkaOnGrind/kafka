(function () {
  'use strict';

  const variants = Object.freeze({
    black: Object.freeze({
      label: 'Černá',
      image: './assets/products/punk-freedom-black.webp',
      unavailableSizes: Object.freeze(['92'])
    }),
    red: Object.freeze({
      label: 'Červená',
      image: './assets/products/punk-freedom-red.webp',
      unavailableSizes: Object.freeze([])
    })
  });

  const state = { color: 'black', size: '92', cartCount: 0 };
  const image = document.querySelector('#main-product-image');
  const colorButtons = [...document.querySelectorAll('[data-color]')];
  const galleryButtons = [...document.querySelectorAll('[data-gallery-color]')];
  const sizeButtons = [...document.querySelectorAll('[data-size]')];
  const availability = document.querySelector('#availability-message');
  const stockCopy = document.querySelector('#stock-copy');
  const addToCart = document.querySelector('#add-to-cart');
  const cartButton = document.querySelector('.cart-button');
  const cartCount = document.querySelector('.cart-count');
  const toast = document.querySelector('#toast');

  function isAvailable() {
    return !variants[state.color].unavailableSizes.includes(state.size);
  }

  function updateGallery() {
    const variant = variants[state.color];
    image.src = variant.image;
    image.alt = `${variant.label === 'Černá' ? 'Černé' : 'Červené'} dětské tričko Punk is freedom rock shop`;

    colorButtons.forEach(button => {
      const selected = button.dataset.color === state.color;
      button.classList.toggle('is-selected', selected);
      button.setAttribute('aria-pressed', String(selected));
    });
    galleryButtons.forEach(button => {
      const selected = button.dataset.galleryColor === state.color;
      button.classList.toggle('is-selected', selected);
      button.setAttribute('aria-pressed', String(selected));
    });
  }

  function updateAvailability() {
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
  galleryButtons.forEach(button => button.addEventListener('click', () => selectColor(button.dataset.galleryColor)));
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
    toast.textContent = `${variants[state.color].label}, velikost ${state.size} — přidáno do košíku`;
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

  const themeSelect = document.querySelector('#theme-select');
  themeSelect.value = window.kafkaTheme.getTheme();
  themeSelect.addEventListener('change', event => window.kafkaTheme.setTheme(event.currentTarget.value));
  window.addEventListener('kafka:themechange', event => { themeSelect.value = event.detail.theme; });

  document.querySelector('.search').addEventListener('submit', event => {
    event.preventDefault();
    const query = new FormData(event.currentTarget).get('q');
    if (!query) return;
    toast.textContent = `Hledám: ${query}`;
    toast.classList.add('is-visible');
    window.setTimeout(() => toast.classList.remove('is-visible'), 1800);
  });

  updateGallery();
  updateAvailability();
})();
