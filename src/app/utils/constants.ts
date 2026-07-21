/**
 * Global Constants for Guzeelzgene
 */

// Company Information
export const COMPANY_INFO = {
  name: 'Guzeelzgene',
  fullName: 'Guzeelzgene',
  nameMn: 'Guzeelzgene',
  email: 'support@guzeelzgeneshop.mn',
  phone: '77111234',
  phoneDisplay: '7711-1234',
  workingHours: '9:00 - 18:00',
  workingDaysMn: 'Даваа - Баасан',
} as const;

// Shipping Configuration
export const SHIPPING = {
  freeShippingThreshold: 50000,
  defaultShippingCost: 5000,
  city: 'Улаанбаатар',
  deliveryDaysReady: '1-2 хоног',
  deliveryDaysPreorder: '7-14 хоног',
} as const;

// Payment Methods
export const PAYMENT_METHODS = {
  qpay: {
    id: 'qpay',
    name: 'QPay',
    nameMn: 'QPay',
    description: 'QR код уншуулж төлөх',
  },
  card: {
    id: 'card',
    name: 'Credit/Debit Card',
    nameMn: 'Картаар төлөх',
    description: 'Виза, МастерКард',
  },
  cod: {
    id: 'cod',
    name: 'Cash on Delivery',
    nameMn: 'Бэлнээр төлөх',
    description: 'Хүргэлтийн үед төлөх',
  },
} as const;

// Product Categories
export const CATEGORIES = {
  'home-appliance': {
    id: 'home-appliance',
    name: 'Home Appliances',
    nameMn: 'Гэр ахуйн бараа',
    icon: '🏠',
  },
  'beauty': {
    id: 'beauty',
    name: 'Beauty',
    nameMn: 'Гоо сайхан',
    icon: '💄',
  },
  'supplement': {
    id: 'supplement',
    name: 'Supplements',
    nameMn: 'Эрүүл мэндийн бүтээгдэхүүн',
    icon: '💊',
  },
} as const;

// Product Types
export const PRODUCT_TYPES = {
  ready: {
    id: 'ready',
    name: 'Ready Stock',
    nameMn: 'Бэлэн бараа',
    badge: 'Бэлэн',
    color: 'green',
  },
  preorder: {
    id: 'preorder',
    name: 'Pre-order',
    nameMn: 'Урьдчилсан захиалга',
    badge: 'Урьдчилсан',
    color: 'orange',
  },
} as const;

// Order Status
export const ORDER_STATUS = {
  pending: {
    id: 'pending',
    nameMn: 'Хүлээгдэж байна',
    color: 'yellow',
  },
  confirmed: {
    id: 'confirmed',
    nameMn: 'Баталгаажсан',
    color: 'blue',
  },
  processing: {
    id: 'processing',
    nameMn: 'Бэлтгэж байна',
    color: 'purple',
  },
  shipped: {
    id: 'shipped',
    nameMn: 'Хүргэлтэнд гарсан',
    color: 'indigo',
  },
  delivered: {
    id: 'delivered',
    nameMn: 'Хүргэгдсэн',
    color: 'green',
  },
  partially_delivered: {
    id: 'partially_delivered',
    nameMn: 'Хэсэгчлэн хүргэсэн',
    color: 'amber',
  },
  cancelled: {
    id: 'cancelled',
    nameMn: 'Цуцлагдсан',
    color: 'red',
  },
} as const;

// Social Media Links
export const SOCIAL_LINKS = {
  facebook: '#',
  instagram: '#',
  twitter: '#',
} as const;

// API Configuration
export const API_CONFIG = {
  timeout: 30000,
  retryAttempts: 3,
} as const;

// Validation Rules
export const VALIDATION = {
  phoneLength: 8,
  passwordMinLength: 6,
  nameMinLength: 2,
  addressMinLength: 10,
} as const;

// Pagination
export const PAGINATION = {
  defaultPageSize: 12,
  pageSizeOptions: [12, 24, 48],
} as const;

// Local Storage Keys
export const STORAGE_KEYS = {
  cart: 'guzeelzgene_cart',
  user: 'guzeelzgene_user',
  wishlist: 'guzeelzgene_wishlist',
  recentViews: 'guzeelzgene_recent_views',
} as const;

// Animation Durations (in milliseconds)
export const ANIMATION = {
  fast: 150,
  normal: 300,
  slow: 500,
} as const;

// Breakpoints (matching Tailwind CSS)
export const BREAKPOINTS = {
  sm: 640,
  md: 768,
  lg: 1024,
  xl: 1280,
  '2xl': 1536,
} as const;
