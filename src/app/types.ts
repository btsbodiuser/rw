export type ProductType = 'ready' | 'preorder';

export type Category = string;

export type Shop = string;

export type OrderStatus = 'pending' | 'confirmed' | 'shipping' | 'partially_delivered' | 'delivered' | 'cancelled';

export interface ProductVariant {
  id: number;
  colorId: number | null;
  colorName: string | null;
  colorHex: string | null;
  sizeId: number | null;
  sizeName: string | null;
  sku: string | null;
  priceOverride: number | null;
  stock: number | null;
}

export interface ProductColorImage {
  colorId: number;
  colorName: string;
  colorHex: string;
  images: string[];
}

export interface Product {
  id: string;
  slug: string;
  name: string;
  nameMn: string;
  category: Category;
  shop: Shop;
  type: ProductType;
  price: number;
  originalPrice?: number;
  image: string;
  images: string[];
  description: string;
  descriptionMn: string;
  stock?: number | null;
  weightKg?: number;
  hideCargoFee?: boolean;
  cargoIncludedInPrice?: boolean;
  preorderDate?: string;
  orderStatus?: 'open' | 'closed';
  rating: number;
  reviews: number;
  hasVariants?: boolean;
  variants?: ProductVariant[];
  colorImages?: ProductColorImage[];
  priceTiers?: PriceTier[];
}

export interface PriceTier {
  minQty: number;
  unitPrice: number;
}

export interface CartItem {
  product: Product;
  quantity: number;
  variantId?: number;
  variantLabel?: string;
}

export interface Order {
  id: string;
  orderNumber: string;
  items: CartItem[];
  total: number;
  shippingFee: number;
  status: OrderStatus;
  shippingAddress: {
    name: string;
    phone: string;
    district: string;
    khoroo: string;
    address: string;
    detailAddress: string;
  };
  createdAt: string;
  updatedAt: string;
}

export interface User {
  phone: string;
  name: string;
  addresses: Address[];
}

export interface Address {
  id: string;
  name: string;
  phone: string;
  district: string;
  khoroo: string;
  address: string;
  detailAddress: string;
  isDefault: boolean;
}

export interface District {
  id: string;
  name: string;
  nameMn?: string;
  khoroos: { id: number; number: number; label: string }[];
}