import { Product } from '../types';

// Products now loaded from API — this is kept for backward compatibility  
export const products: Product[] = [];

// Runtime cache for category names populated from API
let _categoryNames: Record<string, string> = {
  'home-appliance': 'Гэр ахуйн бараа',
  'beauty': 'Гоо сайхан',
  'supplement': 'Эрүүл мэндийн бүтээгдэхүүн',
};

export function setCategoryNames(map: Record<string, string>) {
  _categoryNames = { ..._categoryNames, ...map };
}

export const getCategoryName = (category: string): string => {
  return _categoryNames[category] || category;
};