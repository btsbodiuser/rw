import React, { useState, useMemo } from 'react';
import { useParams, useNavigate, Link } from 'react-router';
import { ProductCard } from '../components/ProductCard';
import { FilterDrawer } from '../components/FilterDrawer';
import { getCategoryName } from '../data/products';
import { setShopCache } from '../data/shops';
import { fetchProducts, fetchShops, mapApiProduct, mapApiShop, hexToLight } from '../services/api';
import { useApi } from '../hooks/useApi';
import { Filter, ChevronLeft, SlidersHorizontal } from 'lucide-react';

export const ShopPage = () => {
  const { shopId } = useParams<{ shopId: string }>();
  const navigate = useNavigate();
  const [categoryFilter, setCategoryFilter] = useState<string>('all');
  const [filter, setFilter] = useState<'all' | 'ready' | 'preorder'>('all');
  const [discountOnly, setDiscountOnly] = useState(false);
  const [newOnly, setNewOnly] = useState(false);
  const [sortBy, setSortBy] = useState<'popular' | 'price-low' | 'price-high'>('popular');
  const [isFilterOpen, setIsFilterOpen] = useState(false);

  const sortMap = { 'popular': 'popular', 'price-low': 'price_asc', 'price-high': 'price_desc' } as const;

  const { data: shopsData } = useApi(() => fetchShops(), []);
  const { data: productsData, loading: productsLoading } = useApi(() => {
    const params: Record<string, string> = { limit: '100', sort: sortMap[sortBy] };
    if (shopId) params.shop = shopId;
    if (filter !== 'all') params.type = filter;
    if (categoryFilter !== 'all') params.category = categoryFilter;
    if (discountOnly) params.discount = '1';
    if (newOnly) params.new = '1';
    return fetchProducts(params);
  }, [shopId, filter, categoryFilter, discountOnly, newOnly, sortBy]);

  const allShops = useMemo(() => {
    const mapped = (shopsData || []).map(mapApiShop);
    if (mapped.length > 0) setShopCache(mapped);
    return mapped;
  }, [shopsData]);

  const shopInfo = allShops.find(s => s.id === shopId);
  const sortedProducts = useMemo(() => (productsData?.products || []).map(mapApiProduct), [productsData]);

  // Get unique categories for this shop's products
  const categories = useMemo(() => Array.from(new Set(sortedProducts.map(p => p.category))), [sortedProducts]);
  const activeFiltersCount = 
    (categoryFilter !== 'all' ? 1 : 0) + 
    (filter !== 'all' ? 1 : 0) +
    (discountOnly ? 1 : 0) +
    (newOnly ? 1 : 0);

  if (!shopsData) {
    return (
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
        <p className="text-gray-500">Уншиж байна...</p>
      </div>
    );
  }

  if (!shopInfo) {
    return (
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
        <h1 className="text-2xl font-bold text-gray-900 mb-4">Дэлгүүр олдсонгүй</h1>
        <button
          onClick={() => navigate('/')}
          className="text-blue-600 hover:text-blue-700 font-medium"
        >
          Нүүр хуудас руу буцах
        </button>
      </div>
    );
  }

  const FilterContent = () => (
    <div className="space-y-6">
      {/* Category Filter */}
      <div>
        <label className="block text-sm font-semibold text-gray-900 mb-3">Ангилал</label>
        <div className="flex flex-wrap gap-2">
          <button
            onClick={() => setCategoryFilter('all')}
            className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
              categoryFilter === 'all'
                ? 'bg-blue-600 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            Бүгд
          </button>
          {categories.map((cat) => (
            <button
              key={cat}
              onClick={() => setCategoryFilter(cat)}
              className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                categoryFilter === cat
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
              }`}
            >
              {getCategoryName(cat)}
            </button>
          ))}
        </div>
      </div>

      {/* Type Filter */}
      <div>
        <label className="block text-sm font-semibold text-gray-900 mb-3">Төлөв</label>
        <div className="flex flex-wrap gap-2">
          <button
            onClick={() => setFilter('all')}
            className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
              filter === 'all'
                ? 'bg-blue-600 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            Бүгд
          </button>
          <button
            onClick={() => setFilter('ready')}
            className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
              filter === 'ready'
                ? 'bg-blue-600 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            Нөөцтэй
          </button>
          <button
            onClick={() => setFilter('preorder')}
            className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
              filter === 'preorder'
                ? 'bg-blue-600 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            Урьдчилсан захиалга
          </button>
        </div>
      </div>

      {/* Discount & New Filters */}
      <div>
        <label className="block text-sm font-semibold text-gray-900 mb-3">Нэмэлт</label>
        <div className="flex flex-wrap gap-2">
          <button
            onClick={() => setDiscountOnly(!discountOnly)}
            className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
              discountOnly
                ? 'bg-red-500 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            🏷️ Хямдралтай
          </button>
          <button
            onClick={() => setNewOnly(!newOnly)}
            className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
              newOnly
                ? 'bg-green-500 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            ✨ Шинэ
          </button>
        </div>
      </div>

      {/* Apply button for mobile */}
      <button
        onClick={() => setIsFilterOpen(false)}
        className="md:hidden w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors"
      >
        Хайлт хийх ({sortedProducts.length})
      </button>
    </div>
  );

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      {/* Back Button */}
      <button
        onClick={() => navigate('/')}
        className="flex items-center text-gray-600 hover:text-gray-900 mb-6 font-medium"
      >
        <ChevronLeft className="w-5 h-5" />
        Буцах
      </button>

      {/* Shop Header */}
      <div className="rounded-xl p-8 mb-6" style={{ backgroundColor: hexToLight(shopInfo.hexColor) }}>
        <h1 className="text-4xl font-bold mb-3" style={{ color: shopInfo.hexColor }}>
          {shopInfo.name}
        </h1>
        <p className="text-lg text-gray-700 mb-4">{shopInfo.descriptionMn}</p>
        <p className="text-sm text-gray-600">{sortedProducts.length} бүтээгдэхүүн</p>
      </div>

      {/* Mobile Filter Button */}
      <div className="md:hidden mb-4 flex gap-3">
        <button
          onClick={() => setIsFilterOpen(true)}
          className="flex-1 bg-white border border-gray-300 rounded-lg px-4 py-3 flex items-center justify-center gap-2 font-medium text-gray-700 hover:bg-gray-50 transition-colors"
        >
          <SlidersHorizontal className="w-5 h-5" />
          Шүүлтүүр
          {activeFiltersCount > 0 && (
            <span className="bg-blue-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">
              {activeFiltersCount}
            </span>
          )}
        </button>
        <select
          value={sortBy}
          onChange={(e) => setSortBy(e.target.value as any)}
          className="bg-white border border-gray-300 rounded-lg px-4 py-3 font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="popular">Алдартай</option>
          <option value="price-low">Үнэ ↑</option>
          <option value="price-high">Үнэ ↓</option>
        </select>
      </div>

      {/* Desktop Filters */}
      <div className="hidden md:block bg-white rounded-lg shadow-sm p-6 mb-6">
        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-2">
            <Filter className="w-5 h-5 text-gray-500" />
            <span className="font-semibold text-gray-900">Шүүлтүүр</span>
          </div>
          <div className="flex items-center gap-2">
            <span className="text-sm text-gray-700 font-medium">Эрэмбэлэх:</span>
            <select
              value={sortBy}
              onChange={(e) => setSortBy(e.target.value as any)}
              className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="popular">Алдартай</option>
              <option value="price-low">Үнэ: Бага → Их</option>
              <option value="price-high">Үнэ: Их → Бага</option>
            </select>
          </div>
        </div>
        <FilterContent />
      </div>

      {/* Mobile Filter Drawer */}
      <FilterDrawer isOpen={isFilterOpen} onClose={() => setIsFilterOpen(false)}>
        <FilterContent />
      </FilterDrawer>

      {/* Products Grid */}
      {productsLoading ? (
        <div className="text-center py-16">
          <p className="text-gray-500 text-lg">Уншиж байна...</p>
        </div>
      ) : sortedProducts.length > 0 ? (
        <div className="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
          {sortedProducts.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </div>
      ) : (
        <div className="text-center py-16">
          <p className="text-gray-500 text-lg">Бүтээгдэхүүн олдсонгүй.</p>
        </div>
      )}
    </div>
  );
};