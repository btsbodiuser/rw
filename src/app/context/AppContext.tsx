import React, { createContext, useContext, useState, useEffect, ReactNode, useCallback } from 'react';
import { CartItem, User, Address } from '../types';
import { Product } from '../types';
import { authGetMe, authLoginWithIdentifier, authLogout, AuthUser, checkProductEntryPermission } from '../services/api';
import { getEffectivePrice } from '../utils/helpers';

interface AppContextType {
  cart: CartItem[];
  addToCart: (item: CartItem) => void;
  removeFromCart: (productId: string, variantId?: number) => void;
  updateCartQuantity: (productId: string, quantity: number, variantId?: number) => void;
  clearCart: () => void;
  cartTotal: number;
  cartCount: number;
  user: User | null;
  authUser: AuthUser | null;
  authToken: string | null;
  login: (identifier: string, password: string) => Promise<boolean>;
  loginWithToken: (token: string, user: AuthUser) => void;
  logout: () => void;
  updateUserProfile: (name: string) => void;
  updateUserPhone: (phone: string) => void;
  updateUserEmail: (email: string) => void;
  isAuthenticated: boolean;
  authLoading: boolean;
  addAddress: (address: Omit<Address, 'id'>) => void;
  updateAddress: (id: string, address: Partial<Address>) => void;
  deleteAddress: (id: string) => void;
  recentlyViewed: Product[];
  addRecentlyViewed: (product: Product) => void;
  clearRecentlyViewed: () => void;
  canAddProduct: boolean;
}

const AppContext = createContext<AppContextType | undefined>(undefined);

export const useApp = () => {
  const context = useContext(AppContext);
  if (!context) {
    throw new Error('useApp must be used within AppProvider');
  }
  return context;
};

export const AppProvider = ({ children }: { children: ReactNode }) => {
  const [cart, setCart] = useState<CartItem[]>([]);
  const [user, setUser] = useState<User | null>(null);
  const [authUser, setAuthUser] = useState<AuthUser | null>(null);
  const [authToken, setAuthToken] = useState<string | null>(null);
  const [authLoading, setAuthLoading] = useState(true);
  const [recentlyViewed, setRecentlyViewed] = useState<Product[]>([]);
  const [canAddProduct, setCanAddProduct] = useState(false);

  // Load cart from localStorage
  useEffect(() => {
    const savedCart = localStorage.getItem('guzeelzgene-cart');
    if (savedCart) {
      setCart(JSON.parse(savedCart));
    }
    const savedRecentlyViewed = localStorage.getItem('guzeelzgene-recently-viewed');
    if (savedRecentlyViewed) {
      setRecentlyViewed(JSON.parse(savedRecentlyViewed));
    }
    // Restore auth from token
    const savedToken = localStorage.getItem('guzeelzgene-auth-token');
    if (savedToken) {
      authGetMe(savedToken)
        .then(({ user: u }) => {
          setAuthToken(savedToken);
          setAuthUser(u);
          setUser({ phone: u.phone, name: u.name, addresses: [] });
          checkProductEntryPermission(savedToken)
            .then(({ allowed }) => setCanAddProduct(allowed))
            .catch(() => setCanAddProduct(false));
        })
        .catch(() => {
          localStorage.removeItem('guzeelzgene-auth-token');
        })
        .finally(() => setAuthLoading(false));
    } else {
      setAuthLoading(false);
    }
  }, []);

  // Save cart to localStorage
  useEffect(() => {
    localStorage.setItem('guzeelzgene-cart', JSON.stringify(cart));
  }, [cart]);

  // Save recently viewed to localStorage
  useEffect(() => {
    localStorage.setItem('guzeelzgene-recently-viewed', JSON.stringify(recentlyViewed));
  }, [recentlyViewed]);

  const addToCart = (item: CartItem) => {
    setCart((prev) => {
      const existing = prev.find((i) => i.product.id === item.product.id && (i.variantId || undefined) === (item.variantId || undefined));
      if (existing) {
        return prev.map((i) =>
          i.product.id === item.product.id && (i.variantId || undefined) === (item.variantId || undefined)
            ? { ...i, quantity: i.quantity + item.quantity }
            : i
        );
      }
      return [...prev, item];
    });
  };

  const removeFromCart = (productId: string, variantId?: number) => {
    setCart((prev) => prev.filter((i) => !(i.product.id === productId && (i.variantId || undefined) === (variantId || undefined))));
  };

  const updateCartQuantity = (productId: string, quantity: number, variantId?: number) => {
    if (quantity <= 0) {
      removeFromCart(productId, variantId);
      return;
    }
    setCart((prev) =>
      prev.map((i) =>
        i.product.id === productId && (i.variantId || undefined) === (variantId || undefined) ? { ...i, quantity } : i
      )
    );
  };

  const clearCart = () => {
    setCart([]);
  };

  const cartTotal = cart.reduce(
    (sum, item) => sum + getEffectivePrice(item.product, item.quantity) * item.quantity,
    0
  );

  const cartCount = cart.reduce((sum, item) => sum + item.quantity, 0);

  const login = async (identifier: string, password: string): Promise<boolean> => {
    try {
      const result = await authLoginWithIdentifier(identifier, password);
      setAuthToken(result.token);
      setAuthUser(result.user);
      setUser({ phone: result.user.phone, name: result.user.name, addresses: [] });
      localStorage.setItem('guzeelzgene-auth-token', result.token);
      checkProductEntryPermission(result.token)
        .then(({ allowed }) => setCanAddProduct(allowed))
        .catch(() => setCanAddProduct(false));
      return true;
    } catch {
      return false;
    }
  };

  const loginWithToken = (token: string, u: AuthUser) => {
    setAuthToken(token);
    setAuthUser(u);
    setUser({ phone: u.phone, name: u.name, addresses: [] });
    localStorage.setItem('guzeelzgene-auth-token', token);
    checkProductEntryPermission(token)
      .then(({ allowed }) => setCanAddProduct(allowed))
      .catch(() => setCanAddProduct(false));
  };

  const logout = () => {
    if (authToken) authLogout(authToken).catch(() => {});
    setUser(null);
    setAuthUser(null);
    setAuthToken(null);
    setCanAddProduct(false);
    localStorage.removeItem('guzeelzgene-auth-token');
    localStorage.removeItem('guzeelzgene-user');
    sessionStorage.removeItem('guzeelzgene-pending-payment');
  };

  const updateUserProfile = (name: string) => {
    if (!user) return;
    setUser({ ...user, name });
    setAuthUser(prev => prev ? { ...prev, name } : prev);
  };

  const updateUserPhone = (phone: string) => {
    if (!user) return;
    setUser({ ...user, phone });
    setAuthUser(prev => prev ? { ...prev, phone } : prev);
  };

  const updateUserEmail = (email: string) => {
    setAuthUser(prev => prev ? { ...prev, email } : prev);
  };

  const addAddress = (address: Omit<Address, 'id'>) => {
    if (!user) return;
    const newAddress: Address = {
      ...address,
      id: Date.now().toString(),
    };
    setUser({
      ...user,
      addresses: [...user.addresses, newAddress],
    });
  };

  const updateAddress = (id: string, addressUpdate: Partial<Address>) => {
    if (!user) return;
    setUser({
      ...user,
      addresses: user.addresses.map((addr) =>
        addr.id === id ? { ...addr, ...addressUpdate } : addr
      ),
    });
  };

  const deleteAddress = (id: string) => {
    if (!user) return;
    setUser({
      ...user,
      addresses: user.addresses.filter((addr) => addr.id !== id),
    });
  };

  const addRecentlyViewed = useCallback((product: Product) => {
    setRecentlyViewed((prev) => {
      const existingIndex = prev.findIndex((p) => p.id === product.id);
      if (existingIndex !== -1) {
        const updatedList = [...prev];
        updatedList.splice(existingIndex, 1);
        return [product, ...updatedList].slice(0, 20);
      }
      return [product, ...prev].slice(0, 20);
    });
  }, []);

  const clearRecentlyViewed = useCallback(() => {
    setRecentlyViewed([]);
    localStorage.removeItem('guzeelzgene-recently-viewed');
  }, []);

  return (
    <AppContext.Provider
      value={{
        cart,
        addToCart,
        removeFromCart,
        updateCartQuantity,
        clearCart,
        cartTotal,
        cartCount,
        user,
        authUser,
        authToken,
        login,
        loginWithToken,
        logout,
        updateUserProfile,
        updateUserPhone,
        updateUserEmail,
        isAuthenticated: !!authUser,
        authLoading,
        addAddress,
        updateAddress,
        deleteAddress,
        recentlyViewed,
        addRecentlyViewed,
        clearRecentlyViewed,
        canAddProduct,
      }}
    >
      {children}
    </AppContext.Provider>
  );
};