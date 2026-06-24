export interface ShopInfo {
  id: string;
  name: string;
  nameMn: string;
  description: string;
  descriptionMn: string;
  hexColor: string;
  categories: string[];
}

// Static fallback (used only until API loads)
export const shops: ShopInfo[] = [];

export const getShopInfo = (shopId: string): ShopInfo | undefined => {
  return _shopCache.find(s => s.id === shopId);
};

export const getShopsByCategory = (category: string): ShopInfo[] => {
  return _shopCache.filter(s => s.categories.includes(category));
};

// Runtime cache populated by API
let _shopCache: ShopInfo[] = [];

export function setShopCache(shops: ShopInfo[]) {
  _shopCache = shops;
}