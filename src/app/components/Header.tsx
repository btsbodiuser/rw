import React, { useState, useMemo, useRef, useEffect } from 'react';
import { Link, useNavigate } from 'react-router';
import { ShoppingCart, User, Menu, Search, Package, LogOut, X, PlusCircle } from 'lucide-react';
import { useApp } from '../context/AppContext';
import { fetchCategories, fetchSettings } from '../services/api';
import { useApi } from '../hooks/useApi';

export const Header = () => {
  const { cartCount, isAuthenticated, authUser, logout, canAddProduct } = useApp();
  const navigate = useNavigate();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const searchInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (searchOpen && searchInputRef.current) {
      searchInputRef.current.focus();
    }
  }, [searchOpen]);

  const { data: categoriesData } = useApi(() => fetchCategories(), []);
  const { data: settingsData } = useApi(() => fetchSettings(), []);

  const s = (key: string, fallback: string) => String(settingsData?.[key] ?? fallback);

  const categories = useMemo(() => {
    const items: { id: string; name: string; path: string }[] = [
      { id: 'all-items', name: 'Бүх бараа', path: '/all-items' },
    ];
    if (categoriesData) {
      categoriesData.forEach((cat) => {
        items.push({ id: cat.slug, name: cat.name_mn || cat.name, path: `/category/${cat.slug}` });
      });
    }
    return items;
  }, [categoriesData]);

  return (
    <header className="bg-white shadow-sm sticky top-0 z-50">
      {/* Top Bar */}
      <div className="bg-blue-600 text-white py-2">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center text-sm">
          <div className="flex items-center gap-2">
            <Package className="w-4 h-4" />
            <span className="hidden xs:inline">{s('top_bar_text', '50,000₮-с дээш захиалгад хүргэлт үнэгүй')}</span>
            <span className="xs:hidden">{s('top_bar_text_short', 'Үнэгүй хүргэлт 50,000₮+')}</span>
          </div>
          <div>
            <a href={`tel:${s('phone', '7711-1234').replace(/[\s-+]/g, '')}`} className="hover:underline">
              <span className="hidden sm:inline">Холбоо барих: </span>{s('phone', '7711-1234')}
            </a>
          </div>
        </div>
      </div>

      {/* Main Header */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16">
          {/* Logo */}
          <Link to="/" className="flex items-center space-x-2">
            {s('site_logo', '') ? (
              <img
                src={`${import.meta.env.BASE_URL}backend/uploads/media/${s('site_logo', '')}`}
                alt={s('site_name', 'Runners World')}
                className="h-10 w-auto object-contain"
              />
            ) : (
              <div className="bg-blue-600 text-white px-3 py-1.5 rounded-lg font-bold text-lg">
                {s('site_name', 'Runners World').charAt(0)}
              </div>
            )}
            <div>
              <div className="font-bold text-xl text-gray-900">{s('site_name', 'Runners World')}</div>
              <div className="text-xs text-gray-500 -mt-1">{s('site_slogan', 'Дэлгүүр')}</div>
            </div>
          </Link>

          {/* Desktop Navigation */}
          <nav className="hidden md:flex items-center space-x-8">
            {categories.map((cat) => (
              <Link
                key={cat.id}
                to={cat.path}
                className="text-gray-700 hover:text-blue-600 transition-colors font-medium"
              >
                {cat.name}
              </Link>
            ))}
          </nav>

          {/* Right Actions */}
          <div className="flex items-center space-x-4">
            <button
              onClick={() => setSearchOpen(!searchOpen)}
              className="hidden sm:block p-2 text-gray-700 hover:text-blue-600 transition-colors"
            >
              {searchOpen ? <X className="w-5 h-5" /> : <Search className="w-5 h-5" />}
            </button>
            
            {isAuthenticated && canAddProduct && (
              <button
                onClick={() => navigate('/product-entry')}
                className="hidden sm:flex items-center gap-1.5 px-3 py-1.5 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition-colors"
              >
                <PlusCircle className="w-4 h-4" />
                Бараа нэмэх
              </button>
            )}

            <button
              onClick={() => navigate(isAuthenticated ? '/profile' : '/login')}
              className="p-2 text-gray-700 hover:text-blue-600 transition-colors flex items-center gap-1"
            >
              {isAuthenticated ? (
                <>
                  <span className="hidden sm:inline text-sm font-medium">{authUser?.name || authUser?.phone}</span>
                  <User className="w-5 h-5" />
                </>
              ) : (
                <>
                  <span className="hidden sm:inline text-sm">Нэвтрэх</span>
                  <User className="w-5 h-5" />
                </>
              )}
            </button>

            {isAuthenticated && (
              <button
                onClick={() => { logout(); navigate('/'); }}
                className="hidden sm:block p-2 text-gray-400 hover:text-red-500 transition-colors"
                title="Гарах"
              >
                <LogOut className="w-4 h-4" />
              </button>
            )}

            <button
              onClick={() => navigate('/cart')}
              className="relative p-2 text-gray-700 hover:text-blue-600 transition-colors"
            >
              <ShoppingCart className="w-5 h-5" />
              {cartCount > 0 && (
                <span className="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-medium">
                  {cartCount}
                </span>
              )}
            </button>

            <button
              onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              className="md:hidden p-2 text-gray-700"
            >
              <Menu className="w-5 h-5" />
            </button>
          </div>
        </div>
      </div>

      {/* Search Bar */}
      {searchOpen && (
        <div className="border-t bg-white">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <form
              onSubmit={(e) => {
                e.preventDefault();
                const q = searchQuery.trim();
                if (q) {
                  navigate(`/all-items?search=${encodeURIComponent(q)}`);
                  setSearchOpen(false);
                  setSearchQuery('');
                }
              }}
              className="flex items-center gap-2"
            >
              <div className="relative flex-1">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                <input
                  ref={searchInputRef}
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Бараа хайх..."
                  className="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
              <button
                type="submit"
                className="px-6 py-2.5 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors"
              >
                Хайх
              </button>
            </form>
          </div>
        </div>
      )}

      {/* Mobile Menu */}
      {mobileMenuOpen && (
        <div className="md:hidden border-t bg-white">
          <nav className="px-4 py-4 space-y-3">
            {/* Mobile Search */}
            <form
              onSubmit={(e) => {
                e.preventDefault();
                const q = searchQuery.trim();
                if (q) {
                  navigate(`/all-items?search=${encodeURIComponent(q)}`);
                  setMobileMenuOpen(false);
                  setSearchQuery('');
                }
              }}
              className="flex items-center gap-2"
            >
              <div className="relative flex-1">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                <input
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Бараа хайх..."
                  className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
              <button type="submit" className="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium">
                <Search className="w-4 h-4" />
              </button>
            </form>
            {categories.map((cat) => (
              <Link
                key={cat.id}
                to={cat.path}
                onClick={() => setMobileMenuOpen(false)}
                className="block py-2 text-gray-700 hover:text-blue-600 font-medium"
              >
                {cat.name}
              </Link>
            ))}
            {isAuthenticated && canAddProduct && (
              <Link
                to="/product-entry"
                onClick={() => setMobileMenuOpen(false)}
                className="flex items-center gap-2 py-2 text-green-600 hover:text-green-700 font-medium"
              >
                <PlusCircle className="w-4 h-4" />
                Бараа нэмэх
              </Link>
            )}
          </nav>
        </div>
      )}
    </header>
  );
};