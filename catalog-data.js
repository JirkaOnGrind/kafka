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

  function category({ id, name, kind = 'catalog', icon, route, source, nodes, archiveUrl = source.url }) {
    return Object.freeze({
      id, name, kind, icon, route,
      sourceUrl: source.url,
      archiveUrl,
      items: Object.freeze(nodes.map(filterItem))
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
  const discountedGoods = Object.freeze({ ...sale, name: 'Zlevněné zboží' });
  const customLabels = Object.freeze(['Trička', 'Placky', 'Mikiny', 'Čepice', 'Nášivky', 'Náramky', 'Náhrdelníky', 'Hrnky', 'Samolepky', 'Zakázky']);
  if (custom.children.length !== customLabels.length) {
    throw new Error(`Expected ${customLabels.length} Custom items, received ${custom.children.length}.`);
  }
  const customItems = custom.children.map((node, index) => Object.freeze({ ...node, name: customLabels[index] }));

  const categories = Object.freeze([
    category({ id: 'clothing', name: 'Oblečení', icon: 'icon-clothing', route: '#/category/obleceni', source: men, nodes: [men, women, children], archiveUrl: snapshot.source }),
    category({ id: 'accessories', name: 'Doplňky', icon: 'icon-accessories', route: '#/category/doplnky', source: headwear, nodes: [headwear, fashionAccessories, styleAccessories, badges, patches, bags], archiveUrl: snapshot.source }),
    category({ id: 'merch', name: 'Merche', icon: 'icon-merch', route: '#/category/merche', source: merch, nodes: merch.children }),
    category({ id: 'music', name: 'Hudba', icon: 'icon-music', route: '#/category/hudba', source: music, nodes: [music] }),
    category({ id: 'sale', name: 'Výprodej', icon: 'icon-sale-cluster', route: '#/category/vyprodej', source: sale, nodes: [discountedGoods] }),
    category({ id: 'custom', name: 'Vlastní potisk', kind: 'information', icon: 'icon-custom', route: '#/info/custom-print', source: custom, nodes: customItems })
  ]);

  const routes = Object.freeze(Object.fromEntries(categories.map(entry => [entry.route.slice(1), entry])));
  window.kafkaCatalog = Object.freeze({ categories, routes });
})();
