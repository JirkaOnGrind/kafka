(() => {
  'use strict';
  if (document.documentElement.dataset.kafkaUiReady === 'true') return;
  document.documentElement.dataset.kafkaUiReady = 'true';
  // Closing a facet clears its native selections, one AJAX update at a time.
  const facetStates = new Map();
  let clearingFacet = null;
  const facetKey = section => `${section.dataset.type}:${section.dataset.name}`;
  const clearClosedFacet = () => {
    if (!clearingFacet) return;
    const section = [...document.querySelectorAll('#search-filters section[data-type]')]
      .find(item => facetKey(item) === clearingFacet);
    const selected = section?.querySelector('input:checked[data-search-url]');
    const slider = section?.querySelector('[data-slider-encoded-url]');
    const url = selected?.dataset.searchUrl || (slider?.dataset.sliderValues !== 'null' ? slider?.dataset.sliderEncodedUrl : null);
    if (url && new URL(url, location.href).href !== location.href) {
      window.prestashop.emit('updateFacets', url);
    } else clearingFacet = null;
  };
  document.addEventListener('hide.bs.collapse', event => {
    const section = event.target.closest('#search-filters section');
    if (!section) return;
    clearingFacet = facetKey(section);
    facetStates.set(clearingFacet, false);
    queueMicrotask(clearClosedFacet);
  });
  document.addEventListener('show.bs.collapse', event => {
    const section = event.target.closest('#search-filters section');
    if (section) facetStates.set(facetKey(section), true);
  });
  window.prestashop?.on('updateProductList', () => setTimeout(() => {
    document.querySelectorAll('#search-filters section[data-type]').forEach(section => {
      const open = facetStates.get(facetKey(section));
      if (open === undefined) return;
      section.querySelector('.accordion-collapse')?.classList.toggle('show', open);
      const button = section.querySelector('.accordion-button');
      button?.classList.toggle('collapsed', !open);
      button?.setAttribute('aria-expanded', String(open));
    });
    clearClosedFacet();
  }, 0));
  const panel = document.getElementById('kafka-search-panel');
  const toggle = document.querySelector('[data-kafka-search-toggle]');
  toggle?.addEventListener('click', () => {
    panel.hidden = !panel.hidden;
    toggle.setAttribute('aria-expanded', String(!panel.hidden));
    if (!panel.hidden) panel.querySelector('input[name="s"]')?.focus();
  });
  panel?.addEventListener('keydown', event => {
    if (event.key === 'Escape') { panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); toggle.focus(); }
  });

  // Reveal the native current category, keeping Bootstrap's toggles in sync.
  document.querySelectorAll('.category-tree [aria-current="page"]').forEach(link => {
    let item = link.closest('.category-tree__item');
    while (item) {
      const collapse = item.querySelector(':scope > .accordion-collapse');
      const button = item.querySelector(':scope > .category-tree__item-header > button');
      if (collapse && button) {
        collapse.classList.add('show');
        button.classList.remove('collapsed');
        button.setAttribute('aria-expanded', 'true');
      }
      item = item.parentElement.closest('.category-tree__item');
    }
  });

  document.addEventListener('click', event => {
    const button = event.target.closest('[data-kafka-subcategory-toggle]');
    if (!button) return;
    const list = document.getElementById(button.getAttribute('aria-controls'));
    if (!list) return;
    const open = button.getAttribute('aria-expanded') !== 'true';
    const card = button.closest('[data-kafka-category]');
    if (card && desktop.matches && open) {
      const row = rowCards(card);
      if (!row.some(other => other.classList.contains('is-independent'))) {
        const height = Math.max(...row.map(other => other.getBoundingClientRect().height));
        row.forEach(other => { other.classList.add('is-independent'); other.style.setProperty('--collapsed-height', `${height}px`); });
      }
    }
    list.hidden = !open;
    button.setAttribute('aria-expanded', String(open));
    if (card && desktop.matches && !open) {
      const row = rowCards(card);
      if (!row.some(other => other.classList.contains('is-expanded') || other.querySelector('[data-kafka-subcategory-toggle][aria-expanded="true"]'))) {
        row.forEach(other => { other.classList.remove('is-independent'); other.style.removeProperty('--collapsed-height'); });
      }
    }
  });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('[data-kafka-subcategory-toggle][aria-expanded="true"]').forEach(button => {
      document.getElementById(button.getAttribute('aria-controls')).hidden = true;
      button.setAttribute('aria-expanded', 'false');
    });
  });

  const grid = document.querySelector('.kafka-category-grid');
  if (!grid) return;
  const desktop = matchMedia('(min-width: 768px)');
  const cards = [...grid.querySelectorAll('[data-kafka-category]')];
  const rowCards = card => {
    const columns = getComputedStyle(grid).gridTemplateColumns.split(' ').length;
    const start = Math.floor(cards.indexOf(card) / columns) * columns;
    return cards.slice(start, start + columns);
  };
  const expand = (card, open) => {
    const button = card.querySelector('[data-kafka-category-toggle]');
    if (!button) return;
    if (open && !desktop.matches) cards.filter(other => other !== card && other.classList.contains('is-expanded')).forEach(other => expand(other, false));
    const row = rowCards(card);
    if (open && desktop.matches && !row.some(other => other.classList.contains('is-independent'))) {
      const height = Math.max(...row.map(other => other.getBoundingClientRect().height));
      row.forEach(other => { other.classList.add('is-independent'); other.style.setProperty('--collapsed-height', `${height}px`); });
    }
    card.classList.toggle('is-expanded', open);
    button.setAttribute('aria-expanded', String(open));
    button.setAttribute('aria-label', `${open ? 'Skrýt' : 'Zobrazit'} podkategorie ${card.querySelector('h3').textContent}`);
    if (desktop.matches && !row.some(other => other.classList.contains('is-expanded'))) {
      row.forEach(other => { other.classList.remove('is-independent'); other.style.removeProperty('--collapsed-height'); });
    }
  };
  grid.addEventListener('click', event => {
    const button = event.target.closest('[data-kafka-category-toggle]');
    if (button) expand(button.closest('[data-kafka-category]'), button.getAttribute('aria-expanded') !== 'true');
  });
  document.addEventListener('click', event => {
    if (!grid.contains(event.target)) cards.forEach(card => expand(card, false));
  });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') cards.forEach(card => expand(card, false)); });
  desktop.addEventListener('change', () => cards.forEach(card => {
    expand(card, false); card.classList.remove('is-independent'); card.style.removeProperty('--collapsed-height');
  }));
})();
