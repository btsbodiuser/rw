const API_BASE = `${import.meta.env.BASE_URL}backend/api`;

/** Prepend BASE_URL to backend image paths so they work under sub-directory deploys */
export function fixImageUrl(url: string | null | undefined): string | null {
  if (!url) return null;
  if (url.startsWith('/backend/')) {
    return `${import.meta.env.BASE_URL}${url.slice(1)}`;
  }
  return url;
}

async function fetchJson<T>(url: string): Promise<T> {
  const res = await fetch(`${API_BASE}${url}`);
  if (!res.ok) throw new Error(`API error: ${res.status}`);
  return res.json();
}

// ── Products ──

export interface ApiProduct {
  id: number;
  name: string;
  name_mn: string;
  slug: string;
  type: 'ready' | 'preorder';
  price: number;
  original_price: number | null;
  weight_kg: number | null;
  image: string | null;
  images: string[];
  description: string;
  description_mn: string;
  stock: number | null;
  rating: number;
  reviews: number;
  category: string;
  category_name: string;
  shop: string;
  shop_name: string;
  preorder_date?: string;
  order_status?: 'open' | 'closed';
  hide_cargo_fee?: number;
  cargo_included_in_price?: number;
  has_variants?: number;
  variants?: {
    id: number;
    color_id: number | null;
    color_name: string | null;
    color_hex: string | null;
    size_id: number | null;
    size_name: string | null;
    sku: string | null;
    price_override: number | null;
    stock: number | null;
  }[];
  color_images?: {
    color_id: number;
    color_name: string;
    color_hex: string;
    images: string[];
  }[];
  price_tiers?: { min_qty: number; unit_price: number }[];
}

interface ProductsResponse {
  products: ApiProduct[];
  total: number;
  page: number;
  limit: number;
  total_pages: number;
}

export async function fetchProducts(params?: Record<string, string>): Promise<ProductsResponse> {
  const qs = params ? '?' + new URLSearchParams(params).toString() : '';
  return fetchJson<ProductsResponse>(`/products.php${qs}`);
}

export async function fetchProduct(identifier: string): Promise<ApiProduct> {
  const param = /^\d+$/.test(identifier) ? 'id' : 'slug';
  return fetchJson<ApiProduct>(`/product.php?${param}=${encodeURIComponent(identifier)}`);
}

// ── Shops ──

export interface ApiShop {
  id: number;
  slug: string;
  name: string;
  name_mn: string;
  description: string;
  description_mn: string;
  color: string;
  logo: string | null;
  categories: string[];
}

interface ShopsResponse {
  shops: ApiShop[];
}

export async function fetchShops(): Promise<ApiShop[]> {
  const data = await fetchJson<ShopsResponse>('/shops.php');
  return data.shops;
}

// ── FAQ ──

export interface ApiFaq {
  id: number;
  category: string;
  question: string;
  answer: string;
}

interface FaqsResponse { faqs: ApiFaq[]; }

export async function fetchFaqs(): Promise<ApiFaq[]> {
  const data = await fetchJson<FaqsResponse>('/faqs.php');
  return data.faqs;
}

// ── Categories ──

export interface ApiCategory {
  id: number;
  slug: string;
  name: string;
  name_mn: string;
  icon: string | null;
  image: string | null;
}

interface CategoriesResponse {
  categories: ApiCategory[];
}

export async function fetchCategories(): Promise<ApiCategory[]> {
  const data = await fetchJson<CategoriesResponse>('/categories.php');
  return data.categories;
}

// ── Districts ──

export interface ApiDistrict {
  id: number;
  name: string;
  name_mn: string;
  khoroos: { id: number; number: number; name?: string }[];
}

interface DistrictsResponse {
  districts: ApiDistrict[];
}

export async function fetchDistricts(): Promise<ApiDistrict[]> {
  const data = await fetchJson<DistrictsResponse>('/districts.php');
  return data.districts;
}

// ── Orders ──

export interface CreateOrderPayload {
  customer_name: string;
  customer_phone: string;
  fulfillment: 'delivery' | 'pickup';
  district_id?: number;
  khoroo_id?: number;
  address?: string;
  detail_address?: string;
  payment_method: 'qpay' | 'card' | 'cash' | 'transfer' | 'bonum' | 'storepay';
  notes?: string;
  save_address?: boolean;
  items: { product_id: number; quantity: number; variant_id?: number }[];
}

export interface CreateOrderResponse {
  success: boolean;
  order_number: string;
  total: number;
  subtotal: number;
  delivery_fee: number;
  cargo_fee: number;
}

export async function createOrder(payload: CreateOrderPayload, token?: string): Promise<CreateOrderResponse> {
  const headers: Record<string, string> = { 'Content-Type': 'application/json' };
  if (token) headers['Authorization'] = `Bearer ${token}`;
  const res = await fetch(`${API_BASE}/orders.php`, {
    method: 'POST',
    headers,
    body: JSON.stringify(payload),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Order failed');
  return data;
}

export async function cancelOrder(orderNumber: string): Promise<void> {
  await fetch(`${API_BASE}/orders.php?action=cancel`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_number: orderNumber }),
  });
}

export async function updateOrderPaymentMethod(orderNumber: string, paymentMethod: string): Promise<void> {
  await fetch(`${API_BASE}/orders.php?action=update-payment-method`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_number: orderNumber, payment_method: paymentMethod }),
  });
}

// ── Settings ──

declare global {
  interface Window { __SETTINGS__?: Record<string, string | number | boolean>; }
}

export async function fetchSettings(): Promise<Record<string, string | number | boolean>> {
  // Use server-injected settings if available (avoids flash)
  if (window.__SETTINGS__) {
    const settings = window.__SETTINGS__;
    delete window.__SETTINGS__;
    return settings;
  }
  const data = await fetchJson<{ settings: Record<string, string | number | boolean> }>('/settings.php');
  return data.settings;
}

// ── QPay ──

export interface QPayInvoiceResponse {
  success: boolean;
  invoice_id: string;
  qr_image: string;
  qr_text: string;
  urls: { name: string; description: string; logo: string; link: string }[];
}

export interface QPayCheckResponse {
  success: boolean;
  paid: boolean;
  payment_info?: Record<string, unknown>;
}

export async function createQPayInvoice(orderNumber: string, amount: number, description?: string): Promise<QPayInvoiceResponse> {
  const res = await fetch(`${API_BASE}/qpay.php?action=create-invoice`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_number: orderNumber, amount, description }),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'QPay invoice creation failed');
  return data;
}

export async function checkQPayPayment(invoiceId: string): Promise<QPayCheckResponse> {
  const res = await fetch(`${API_BASE}/qpay.php?action=check-payment`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ invoice_id: invoiceId }),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'QPay payment check failed');
  return data;
}

// ── Bonum ──

export interface BonumInvoiceResponse {
  success: boolean;
  invoice_id: string;
  follow_up_link: string;
}

export interface BonumCheckResponse {
  success: boolean;
  paid: boolean;
}

export async function createBonumInvoice(orderNumber: string, amount: number, description?: string): Promise<BonumInvoiceResponse> {
  const res = await fetch(`${API_BASE}/bonum.php?action=create-invoice`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_number: orderNumber, amount, description }),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Bonum invoice creation failed');
  return data;
}

export async function checkBonumPayment(invoiceId: string): Promise<BonumCheckResponse> {
  const res = await fetch(`${API_BASE}/bonum.php?action=check-payment`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ invoice_id: invoiceId }),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Bonum payment check failed');
  return data;
}

// ── StorePay ──

export interface StorePayInvoiceResponse {
  success: boolean;
  invoice_id: string;
}

export interface StorePayCheckResponse {
  success: boolean;
  paid: boolean;
}

export async function createStorepayInvoice(orderNumber: string, amount: number, mobileNumber: string, description?: string): Promise<StorePayInvoiceResponse> {
  const res = await fetch(`${API_BASE}/storepay.php?action=create-invoice`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_number: orderNumber, amount, mobile_number: mobileNumber, description }),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'StorePay invoice creation failed');
  return data;
}

export async function checkStorepayPayment(invoiceId: string): Promise<StorePayCheckResponse> {
  const res = await fetch(`${API_BASE}/storepay.php?action=check-payment`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ invoice_id: invoiceId }),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'StorePay payment check failed');
  return data;
}

// ── Mappers: Convert API data to frontend types ──

import { Product } from '../types';
import { ShopInfo } from '../data/shops';



export function mapApiProduct(p: ApiProduct): Product {
  return {
    id: String(p.id),
    slug: p.slug,
    name: p.name,
    nameMn: p.name_mn,
    category: p.category as Product['category'],
    shop: p.shop as Product['shop'],
    type: p.type,
    price: p.price,
    originalPrice: p.original_price ?? undefined,
    image: fixImageUrl(p.image) || 'https://placehold.co/400x400?text=No+Image',
    images: (p.images || []).map(i => fixImageUrl(i) || i),
    description: p.description,
    descriptionMn: p.description_mn,
    stock: p.stock,
    weightKg: p.weight_kg ?? undefined,
    hideCargoFee: !!(p.hide_cargo_fee),
    cargoIncludedInPrice: !!(p.cargo_included_in_price),
    preorderDate: p.preorder_date,
    orderStatus: p.order_status || 'open',
    rating: p.rating,
    reviews: p.reviews,
    hasVariants: !!(p.has_variants),
    variants: p.variants?.map(v => ({
      id: v.id,
      colorId: v.color_id,
      colorName: v.color_name,
      colorHex: v.color_hex,
      sizeId: v.size_id,
      sizeName: v.size_name,
      sku: v.sku,
      priceOverride: v.price_override,
      stock: v.stock,
    })),
    colorImages: p.color_images?.map(ci => ({
      colorId: ci.color_id,
      colorName: ci.color_name,
      colorHex: ci.color_hex,
      images: (ci.images || []).map(i => fixImageUrl(i) || i),
    })),
    priceTiers: p.price_tiers?.map(t => ({ minQty: t.min_qty, unitPrice: t.unit_price })),
  };
}

export function mapApiShop(s: ApiShop): ShopInfo {
  return {
    id: s.slug,
    name: s.name,
    nameMn: s.name_mn,
    description: s.description,
    descriptionMn: s.description_mn,
    hexColor: s.color || '#3B82F6',
    categories: s.categories,
  };
}

export function hexToLight(hex: string, alpha = 0.1): string {
  const r = parseInt(hex.slice(1, 3), 16);
  const g = parseInt(hex.slice(3, 5), 16);
  const b = parseInt(hex.slice(5, 7), 16);
  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

// ── Auth ──

async function postJson<T>(url: string, body: Record<string, unknown>, token?: string): Promise<T> {
  const headers: Record<string, string> = { 'Content-Type': 'application/json' };
  if (token) headers['Authorization'] = `Bearer ${token}`;
  const res = await fetch(`${API_BASE}${url}`, { method: 'POST', headers, body: JSON.stringify(body) });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || `API error: ${res.status}`);
  return data;
}

export interface AuthUser {
  id: number;
  phone: string;
  name: string;
  email?: string | null;
  google_connected?: boolean;
  facebook_connected?: boolean;
}

export async function authCheckPhone(phone: string): Promise<{ exists: boolean; has_password: boolean }> {
  return postJson('/auth/check-phone.php', { phone });
}

export async function authSendOtp(phone: string): Promise<{ success: boolean }> {
  return postJson('/auth/send-otp.php', { phone });
}

export async function authVerifyOtp(phone: string, code: string): Promise<{ verified: boolean; otp_token: string }> {
  return postJson('/auth/verify-otp.php', { phone, code });
}

export async function authRegister(phone: string, otpToken: string, password: string, name: string): Promise<{ success: boolean; token: string; user: AuthUser }> {
  return postJson('/auth/register.php', { phone, otp_token: otpToken, password, name });
}

export async function authRegisterDirect(phone: string, password: string, name: string): Promise<{ success: boolean; token: string; user: AuthUser }> {
  return postJson('/auth/register-direct.php', { phone, password, name });
}

export async function authLogin(phone: string, password: string): Promise<{ success: boolean; token: string; user: AuthUser }> {
  return postJson('/auth/login.php', { phone, password });
}

export async function authLoginWithIdentifier(identifier: string, password: string): Promise<{ success: boolean; token: string; user: AuthUser }> {
  return postJson('/auth/login.php', { identifier, password });
}

export async function authSendEmailOtp(email: string, purpose: 'register' | 'reset' | 'update'): Promise<{ success: boolean }> {
  return postJson('/auth/send-email-otp.php', { email, purpose });
}

export async function updatePhone(token: string, newPhone: string, otpCode: string): Promise<{ success: boolean; phone: string }> {
  return postJson('/auth/update-phone.php', { new_phone: newPhone, otp_code: otpCode }, token);
}

export async function updateEmail(token: string, newEmail: string, otpCode: string): Promise<{ success: boolean; email: string }> {
  return postJson('/auth/update-email.php', { new_email: newEmail, otp_code: otpCode }, token);
}

export async function authVerifyEmailOtp(email: string, otp: string): Promise<{ success: boolean; token: string }> {
  return postJson('/auth/verify-email-otp.php', { email, otp });
}

export async function authRegisterEmail(email: string, name: string, phone: string, password: string): Promise<{ success: boolean; token: string; user: AuthUser }> {
  return postJson('/auth/register-email.php', { email, name, phone, password });
}

export async function authResetPasswordEmail(email: string, otp: string, newPassword: string): Promise<{ success: boolean }> {
  return postJson('/auth/reset-password-email.php', { email, otp, new_password: newPassword });
}

export async function authResetPassword(phone: string, otpToken: string, password: string): Promise<{ success: boolean; token: string; user: AuthUser }> {
  return postJson('/auth/reset-password.php', { phone, otp_token: otpToken, password });
}

export async function authGetMe(token: string): Promise<{ user: AuthUser }> {
  const res = await fetch(`${API_BASE}/auth/me.php`, { headers: { 'Authorization': `Bearer ${token}` } });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Not authenticated');
  return data;
}

export async function authLogout(token: string): Promise<void> {
  await fetch(`${API_BASE}/auth/logout.php`, { method: 'POST', headers: { 'Authorization': `Bearer ${token}` } });
}

// Social login
export interface SocialLoginResponse {
  success: boolean;
  needs_phone?: boolean;
  social_profile?: {
    name: string;
    email: string | null;
    avatar: string | null;
    provider: 'google' | 'facebook';
  };
  token?: string;
  user?: AuthUser;
}

export async function authSocialLogin(provider: 'google' | 'facebook', token: string, phone?: string, otpToken?: string): Promise<SocialLoginResponse> {
  const body: Record<string, unknown> = { provider, token };
  if (phone) body.phone = phone;
  if (otpToken) body.otp_token = otpToken;
  return postJson('/auth/social-login.php', body);
}

// ── Customer Addresses ──

export interface ApiAddress {
  id: number;
  customer_id: number;
  label: string;
  district_id: number;
  khoroo_id: number;
  address: string;
  detail_address: string;
  is_default: number;
  district_name: string;
  khoroo_number: string;
  khoroo_name: string;
}

export async function fetchAddresses(token: string): Promise<ApiAddress[]> {
  const res = await fetch(`${API_BASE}/addresses.php`, { headers: { 'Authorization': `Bearer ${token}` } });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Failed to load addresses');
  return data.addresses;
}

export async function createAddress(token: string, addr: { district_id: number; khoroo_id: number; address: string; detail_address?: string; label?: string; is_default?: boolean }): Promise<ApiAddress> {
  const data = await postJson<{ success: boolean; address: ApiAddress }>('/addresses.php', addr as Record<string, unknown>, token);
  return data.address;
}

export async function deleteAddress(token: string, id: number): Promise<void> {
  const res = await fetch(`${API_BASE}/addresses.php?id=${id}`, { method: 'DELETE', headers: { 'Authorization': `Bearer ${token}` } });
  if (!res.ok) {
    const data = await res.json();
    throw new Error(data.error || 'Failed to delete address');
  }
}

// ── Customer Orders ──

export interface ApiOrderItem {
  product_id: number;
  product_name: string;
  product_name_mn: string;
  product_price: number;
  quantity: number;
  cargo_fee: number;
  cargo_fee_paid: number;
  variant_label: string | null;
  image: string | null;
}

export interface ApiOrder {
  id: number;
  order_number: string;
  status: string;
  fulfillment: string;
  customer_name: string;
  customer_phone: string;
  district_name: string;
  khoroo_number: string;
  khoroo_name: string;
  address: string;
  detail_address: string;
  subtotal: number;
  delivery_fee: number;
  cargo_fee: number;
  cargo_fee_paid: number;
  total: number;
  payment_method: string;
  payment_status: string;
  created_at: string;
  updated_at: string;
  items: ApiOrderItem[];
}

export async function fetchCustomerOrders(token: string): Promise<ApiOrder[]> {
  const res = await fetch(`${API_BASE}/customer-orders.php`, { headers: { 'Authorization': `Bearer ${token}` } });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Failed to load orders');
  return data.orders;
}

export async function fetchCustomerOrder(token: string, orderNumber: string): Promise<ApiOrder> {
  const res = await fetch(`${API_BASE}/customer-orders.php?order_number=${encodeURIComponent(orderNumber)}`, { headers: { 'Authorization': `Bearer ${token}` } });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Order not found');
  return data.order;
}

export async function updateOrderFulfillment(token: string, orderNumber: string, payload: { fulfillment: string; district_id?: number; khoroo_id?: number; address?: string; detail_address?: string }): Promise<void> {
  const res = await fetch(`${API_BASE}/customer-orders.php`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
    body: JSON.stringify({ order_number: orderNumber, ...payload }),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Failed to update');
}

// ── Public Order Tracking ──

export interface OrderStatusItem {
  product_name: string;
  variant_label: string | null;
  product_price: number;
  quantity: number;
  cargo_fee: number;
  cargo_fee_paid: number;
  delivery_status: 'pending' | 'delivered';
  image: string | null;
  slug: string | null;
}

export interface OrderStatusResponse {
  order_number: string;
  type: string;
  fulfillment: string;
  status: string;
  status_label: string;
  customer_name: string;
  customer_phone: string;
  district: string;
  district_mn: string;
  subtotal: number;
  delivery_fee: number;
  cargo_fee: number;
  cargo_fee_paid: number;
  total: number;
  payment_method: string;
  payment_status: string;
  created_at: string;
  updated_at: string;
  items: OrderStatusItem[];
  status_flow: string[];
}

export async function fetchOrderStatus(orderNumber: string): Promise<OrderStatusResponse | null> {
  const res = await fetch(`${API_BASE}/order-status.php?order_number=${encodeURIComponent(orderNumber)}`);
  if (!res.ok) return null;
  const data = await res.json();
  return data.order || null;
}

// ── Product Entry (for permitted customers) ──

export interface ProductEntryPayload {
  name?: string;
  name_mn: string;
  category_id: number;
  shop_id: number;
  type: 'ready' | 'preorder';
  price: number;
  original_price?: number;
  stock?: number;
  weight_kg?: number;
  preorder_date?: string;
  main_image_id?: number;
  image_ids?: number[];
  description_mn?: string;
  show_in_store?: number;
  order_status?: 'open' | 'closed';
  cargo_batch_id?: number | null;
  hide_cargo_fee?: number;
  cargo_included_in_price?: number;
}

export interface CargoBatch {
  id: number;
  name: string;
  cargo_rate_per_kg: number;
}

export async function checkProductEntryPermission(token: string): Promise<{ allowed: boolean; cargo_batches: CargoBatch[] }> {
  const res = await fetch(`${API_BASE}/product-entry.php`, { headers: { 'Authorization': `Bearer ${token}` } });
  if (!res.ok) return { allowed: false, cargo_batches: [] };
  return res.json();
}

export async function createProduct(token: string, data: ProductEntryPayload): Promise<{ success: boolean; id: number; slug: string }> {
  return postJson('/product-entry.php', data as unknown as Record<string, unknown>, token);
}

export interface ProductEditData {
  id: number;
  name: string;
  name_mn: string;
  category_id: number;
  shop_id: number;
  type: 'ready' | 'preorder';
  price: number;
  original_price: number | null;
  stock: number | null;
  weight_kg: number | null;
  description_mn: string;
  preorder_date: string | null;
  show_in_store: number;
  order_status: 'open' | 'closed';
  cargo_batch_id: number | null;
  hide_cargo_fee: number;
  cargo_included_in_price: number;
  main_image: { id: number; filename: string; original_name: string } | null;
  gallery_images: { id: number; filename: string; original_name: string }[];
}

export async function fetchProductForEdit(token: string, id: number): Promise<ProductEditData> {
  const res = await fetch(`${API_BASE}/product-entry.php?id=${id}`, { headers: { 'Authorization': `Bearer ${token}` } });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Failed to load product');
  return data.product;
}

export async function updateProduct(token: string, id: number, data: ProductEntryPayload): Promise<{ success: boolean; id: number; slug: string }> {
  const headers: Record<string, string> = { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` };
  const res = await fetch(`${API_BASE}/product-entry.php?id=${id}`, { method: 'PUT', headers, body: JSON.stringify(data) });
  const result = await res.json();
  if (!res.ok) throw new Error(result.error || `API error: ${res.status}`);
  return result;
}

function resizeImageFile(file: File, maxWidth = 1000, maxHeight = 1000, quality = 0.85): Promise<File> {
  return new Promise((resolve) => {
    if (!file.type.startsWith('image/') || file.type === 'image/gif') {
      return resolve(file);
    }
    const img = new Image();
    img.onload = () => {
      URL.revokeObjectURL(img.src);
      if (img.width <= maxWidth && img.height <= maxHeight) return resolve(file);
      const ratio = Math.min(maxWidth / img.width, maxHeight / img.height);
      const w = Math.round(img.width * ratio);
      const h = Math.round(img.height * ratio);
      const canvas = document.createElement('canvas');
      canvas.width = w;
      canvas.height = h;
      const ctx = canvas.getContext('2d')!;
      ctx.drawImage(img, 0, 0, w, h);
      const outputType = file.type === 'image/png' ? 'image/png' : 'image/jpeg';
      canvas.toBlob(
        (blob) => resolve(blob ? new File([blob], file.name, { type: outputType }) : file),
        outputType,
        quality,
      );
    };
    img.onerror = () => resolve(file);
    img.src = URL.createObjectURL(file);
  });
}

export async function uploadMediaFile(token: string, file: File): Promise<{ success: boolean; media: { id: number; filename: string; original_name: string } }> {
  const resized = await resizeImageFile(file);
  const formData = new FormData();
  formData.append('file', resized);
  const res = await fetch(`${API_BASE}/media-upload.php`, {
    method: 'POST',
    headers: { 'Authorization': `Bearer ${token}` },
    body: formData,
  });
  const result = await res.json();
  if (!res.ok) throw new Error(result.error || 'Upload failed');
  return result;
}

export async function fetchMyProducts(token: string): Promise<ApiProduct[]> {
  const res = await fetch(`${API_BASE}/my-products.php`, { headers: { 'Authorization': `Bearer ${token}` } });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Failed to load products');
  return data.products;
}
