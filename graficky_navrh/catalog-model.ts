export type CategoryKind = 'catalog' | 'information';
export type RouteKind = 'filter';

export interface ScrapedCategoryNode {
  readonly name: string;
  readonly url: string;
  readonly children: readonly ScrapedCategoryNode[];
}

export interface LiveCatalogSnapshot {
  readonly source: 'https://kafka.monster/cs/';
  readonly fetchedAt: string;
  readonly categories: readonly ScrapedCategoryNode[];
}

export interface FilterItem {
  readonly kind: 'filter';
  readonly archive: false;
  readonly label: string;
  readonly url: string;
}

export type CategoryItem = FilterItem;

export interface CategoryModel {
  readonly id: string;
  readonly name: string;
  readonly kind: CategoryKind;
  readonly icon: string;
  readonly route: string;
  readonly sourceUrl: string;
  readonly archiveUrl: string;
  readonly items: readonly CategoryItem[];
}

declare global {
  interface Window {
    kafkaLiveCatalogSnapshot: LiveCatalogSnapshot;
    kafkaCatalog: {
      readonly categories: readonly CategoryModel[];
      readonly routes: Readonly<Record<string, CategoryModel>>;
    };
  }
}

export {};
