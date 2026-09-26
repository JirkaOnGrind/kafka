(function () {
  'use strict';

  const snapshot = window.kafkaLiveCatalogSnapshot;
  if (!snapshot?.categories?.length) {
    throw new Error('Live catalog snapshot is missing. Run: node scripts/scrape-catalog.mjs');
  }

  function findCategory(name) {
    const category = snapshot.categories.find(entry => entry.name === name);
    if (!category) throw new Error(`Live category not found: ${name}`);
    return category;
  }

  function filterItem(node) {
    return Object.freeze({ kind: 'filter', archive: false, label: node.name, url: node.url });
  }

  function category({
    id,
    name,
    kind = 'catalog',
    icon,
    route,
    mobileOrder,
    source,
    nodes,
    hasSubcategories = nodes.length > 0,
    products = [],
    archiveUrl = source.url
  }) {
    return Object.freeze({
      id, name, kind, icon, route, mobileOrder, hasSubcategories,
      sourceUrl: source.url,
      archiveUrl,
      items: Object.freeze(nodes.map(filterItem)),
      products: Object.freeze(products.map(product => Object.freeze(product)))
    });
  }

  const men = findCategory('PÁNSKÉ');
  const women = findCategory('DÁMSKÉ');
  const children = findCategory('DĚTSKÉ');
  const headwear = findCategory('ČEPICE , ŠÁTKY');
  const fashionAccessories = findCategory('MÓDNÍ DOPLŇKY');
  const styleAccessories = findCategory('STYLOVÉ DOPLŇKY');
  const badges = findCategory('PLACKY,ŠPENDLÍKY,PINY');
  const patches = findCategory('NÁŠIVKY');
  const bags = findCategory('TAŠKY,PENĚŽENKY');
  const merch = findCategory('MERCH');
  const music = findCategory('CD');
  const custom = findCategory('VLASTNÍ POTISK-CENÍK');
  const sale = findCategory('% VÝPRODEJ %');
  const customLabels = Object.freeze(['Trička', 'Placky', 'Mikiny', 'Čepice', 'Nášivky', 'Náramky', 'Náhrdelníky', 'Hrnky', 'Samolepky', 'Zakázky']);
  if (custom.children.length !== customLabels.length) {
    throw new Error(`Expected ${customLabels.length} Custom items, received ${custom.children.length}.`);
  }
  const customItems = custom.children.map((node, index) => Object.freeze({ ...node, name: customLabels[index] }));

  const musicProducts = [{
    id: 'spuntqane-posledni-singly',
    name: 'ŠpuntQaně — Poslední singly',
    type: 'CD',
    price: '99 Kč',
    image: './assets/products/image_a62278.jpg',
    alt: 'Obal CD ŠpuntQaně — Poslední singly',
    href: 'https://kafka.monster/cs/cd/3415-cd-spuntqane-posledni-singly.html'
  }];

  const saleProducts = [{
    id: 'punk-freedom-sale',
    name: 'Tričko Punk is freedom',
    type: 'Dětské tričko',
    price: '219 Kč',
    originalPrice: '269 Kč',
    discount: '-19 %',
    image: './assets/products/punk-freedom-black.webp',
    alt: 'Černé dětské tričko Punk is freedom',
    href: './product.html'
  }];

  const categories = Object.freeze([
    category({ id: 'clothing', name: 'Oblečení', icon: 'icon-clothing', route: '#/category/obleceni', mobileOrder: 1, source: men, nodes: [men, women, children], archiveUrl: snapshot.source }),
    category({ id: 'music', name: 'Hudba', icon: 'icon-music', route: '#/category/hudba', mobileOrder: 5, source: music, nodes: [], hasSubcategories: false, products: musicProducts }),
    category({ id: 'sale', name: 'Výprodej', icon: 'icon-sale-cluster', route: '#/category/vyprodej', mobileOrder: 6, source: sale, nodes: [], hasSubcategories: false, products: saleProducts }),
    category({ id: 'accessories', name: 'Doplňky', icon: 'icon-accessories', route: '#/category/doplnky', mobileOrder: 2, source: headwear, nodes: [headwear, fashionAccessories, styleAccessories, badges, patches, bags], archiveUrl: snapshot.source }),
    category({ id: 'merch', name: 'Merche', icon: 'icon-merch', route: '#/category/merche', mobileOrder: 3, source: merch, nodes: merch.children }),
    category({ id: 'custom', name: 'Vlastní potisk', kind: 'information', icon: 'icon-custom', route: '#/info/custom-print', mobileOrder: 4, source: custom, nodes: customItems })
  ]);

  const routes = Object.freeze(Object.fromEntries(categories.map(entry => [entry.route.slice(1), entry])));
  window.kafkaCatalog = Object.freeze({ categories, routes });
})();
