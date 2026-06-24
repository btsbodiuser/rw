/**
 * Global Utility Functions for Runners World
 */
import type { Product, PriceTier } from '../types';

// Format price in Mongolian Tugrik
export const formatPrice = (price: number): string => {
  return `${price.toLocaleString('mn-MN')}₮`;
};

// Effective per-unit price for a given quantity. Tiers must be sorted ASC by minQty.
// Tiers apply only to products without variants (see y.md decision).
export const getEffectivePrice = (product: Product, qty: number): number => {
  const base = product.price;
  if (product.hasVariants) return base;
  const tiers = product.priceTiers;
  if (!tiers || tiers.length === 0 || qty < 1) return base;
  let price = base;
  for (const t of tiers) {
    if (qty >= t.minQty) price = t.unitPrice;
    else break;
  }
  return price;
};

// Human-readable tier rows for display, e.g. "1 ширхэг — 10,000₮"
export const formatPriceTiers = (basePrice: number, tiers?: PriceTier[]): { label: string; price: string; minQty: number }[] => {
  const rows: { label: string; price: string; minQty: number }[] = [];
  if (!tiers || tiers.length === 0) return rows;
  // Single-unit row uses base price (unless a tier explicitly starts at 1)
  const hasOne = tiers.some(t => t.minQty === 1);
  if (!hasOne) {
    rows.push({ label: '1 ширхэг', price: formatPrice(basePrice), minQty: 1 });
  }
  for (const t of tiers) {
    rows.push({ label: `${t.minQty}+ ширхэг`, price: formatPrice(t.unitPrice), minQty: t.minQty });
  }
  return rows;
};

// Format phone number for display
export const formatPhone = (phone: string): string => {
  // Assuming format: 7711-1234
  return phone.replace(/(\d{4})(\d{4})/, '$1-$2');
};

// Validate Mongolian phone number
export const validatePhone = (phone: string): boolean => {
  const phoneRegex = /^[0-9]{8}$/;
  return phoneRegex.test(phone.replace(/[-\s]/g, ''));
};

// Calculate discount percentage
export const calculateDiscount = (originalPrice: number, currentPrice: number): number => {
  if (!originalPrice || originalPrice <= currentPrice) return 0;
  return Math.round(((originalPrice - currentPrice) / originalPrice) * 100);
};

// Check if product has discount
export const hasDiscount = (originalPrice?: number, currentPrice?: number): boolean => {
  if (!originalPrice || !currentPrice) return false;
  return originalPrice > currentPrice;
};

// Truncate text with ellipsis
export const truncateText = (text: string, maxLength: number): string => {
  if (text.length <= maxLength) return text;
  return text.substring(0, maxLength) + '...';
};

// Generate slug from text (for URLs)
export const slugify = (text: string): string => {
  return text
    .toLowerCase()
    .replace(/[^\w\s-]/g, '')
    .replace(/[\s_-]+/g, '-')
    .replace(/^-+|-+$/g, '');
};

// Calculate estimated delivery date
export const getEstimatedDelivery = (type: 'ready' | 'preorder'): string => {
  const today = new Date();
  let deliveryDays = 0;
  
  if (type === 'ready') {
    deliveryDays = 2; // 1-2 days for ready stock
  } else {
    deliveryDays = 14; // 7-14 days for preorder
  }
  
  const deliveryDate = new Date(today);
  deliveryDate.setDate(today.getDate() + deliveryDays);
  
  const monthNames = [
    'Нэгдүгээр сар',
    'Хоёрдугаар сар',
    'Гуравдугаар сар',
    'Дөрөвдүгээр сар',
    'Тавдугаар сар',
    'Зургаадугаар сар',
    'Долоодугаар сар',
    'Наймдугаар сар',
    'Есдүгээр сар',
    'Аравдугаар сар',
    'Арван нэгдүгээр сар',
    'Арван хоёрдугаар сар'
  ];
  
  return `${monthNames[deliveryDate.getMonth()]} ${deliveryDate.getDate()}`;
};

// Format date in Mongolian
export const formatDate = (date: Date): string => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}/${month}/${day}`;
};

// Check if free shipping applies
export const isFreeShipping = (totalAmount: number): boolean => {
  return totalAmount >= 50000;
};

// Calculate shipping cost
export const calculateShipping = (totalAmount: number): number => {
  if (isFreeShipping(totalAmount)) return 0;
  return 5000; // Default shipping cost
};

// Validate email
export const validateEmail = (email: string): boolean => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
};

// Generate order ID
export const generateOrderId = (): string => {
  const timestamp = Date.now();
  const random = Math.floor(Math.random() * 1000);
  return `ORD-${timestamp}-${random}`;
};

// Get product stock status. null/undefined = unlimited (in-stock).
export const getStockStatus = (stock?: number | null): 'in-stock' | 'low-stock' | 'out-of-stock' => {
  if (stock === null || stock === undefined) return 'in-stock';
  if (stock === 0) return 'out-of-stock';
  if (stock <= 5) return 'low-stock';
  return 'in-stock';
};

// Get stock status text in Mongolian
export const getStockStatusText = (stock?: number | null): string => {
  const status = getStockStatus(stock);
  const statusTexts = {
    'in-stock': 'Бэлэн байна',
    'low-stock': `${stock} ширхэг үлдсэн`,
    'out-of-stock': 'Дууссан'
  };
  return statusTexts[status];
};

// Safe localStorage access (with SSR compatibility)
export const safeLocalStorage = {
  getItem: (key: string): string | null => {
    if (typeof window === 'undefined') return null;
    try {
      return localStorage.getItem(key);
    } catch {
      return null;
    }
  },
  setItem: (key: string, value: string): void => {
    if (typeof window === 'undefined') return;
    try {
      localStorage.setItem(key, value);
    } catch {
      // Handle quota exceeded or other errors
    }
  },
  removeItem: (key: string): void => {
    if (typeof window === 'undefined') return;
    try {
      localStorage.removeItem(key);
    } catch {
      // Handle errors
    }
  }
};

// Scroll to top smoothly
export const scrollToTop = (smooth: boolean = true): void => {
  if (typeof window === 'undefined') return;
  window.scrollTo({
    top: 0,
    behavior: smooth ? 'smooth' : 'auto'
  });
};

// Debounce function
export const debounce = <T extends (...args: any[]) => any>(
  func: T,
  wait: number
): ((...args: Parameters<T>) => void) => {
  let timeout: NodeJS.Timeout | null = null;
  return (...args: Parameters<T>) => {
    if (timeout) clearTimeout(timeout);
    timeout = setTimeout(() => func(...args), wait);
  };
};
