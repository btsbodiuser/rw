import React, { useState, useMemo } from 'react';
import { useParams } from 'react-router';
import { Link } from 'react-router';
import { ProductCard } from '../components/ProductCard';
import { FilterDrawer } from '../components/FilterDrawer';
import { getCategoryName, setCategoryNames } from '../data/products';
import { fetchProducts, fetchShops, fetchCategories, mapApiProduct, mapApiShop } from '../services/api';
import { setShopCache } from '../data/shops';
import { useApi } from '../hooks/useApi';
import { Filter, Store, SlidersHorizontal } from 'lucide-react';

export const CategoryPage = () => {
  const { categoryId } = useParams<{ categoryId: string }>();
  const [filter, setFilter] = useState<'all' | 'ready' | 'preorder'>('all');
  const [shopFilter, setShopFilter] = useState<string>('all');
  const [discountOnly, setDiscountOnly] = useState(false);
  const [newOnly, setNewOnly] = useState(false);
  const [sortBy, setSortBy] = useState<'popular' | 'price-low' | 'price-high'>('popular');
  const [isFilterOpen, setIsFilterOpen] = useState(false);

  const sortMap = { 'popular': 'popular', 'price-low': 'price_asc', 'price-high': 'price_desc' } as const;

  const { data: productsData, loading: productsLoading } = useApi(() => {
    const params: Record<string, string> = { limit: '100', sort: sortMap[sortBy] };
    if (categoryId) params.category = categoryId;
    if (filter !== 'all') params.type = filter;
    if (shopFilter !== 'all') params.shop = shopFilter;
    if (discountOnly) params.discount = '1';
    if (newOnly) params.new = '1';
    return fetchProducts(params);
  }, [categoryId, filter, shopFilter, discountOnly, newOnly, sortBy]);

  const { data: shopsData } = useApi(() => fetchShops(), []);
  const { data: categoriesData } = useApi(() => fetchCategories(), []);

  const sortedProducts = useMemo(() => (productsData?.products || []).map(mapApiProduct), [productsData]);
  const availableShops = useMemo(() => {
    const mapped = (shopsData || []).map(mapApiShop);
    if (mapped.length > 0) setShopCache(mapped);
    return mapped.filter(s => s.categories.includes(categoryId || ''));
  }, [shopsData, categoryId]);

  // Populate category name cache from API
  useMemo(() => {
    const cats = (categoriesData || []).map(c => ({ id: c.slug, name: c.name_mn || c.name }));
    if (cats.length > 0) {
      const map: Record<string, string> = {};
      cats.forEach(c => { map[c.id] = c.name; });
      setCategoryNames(map);
    }
  }, [categoriesData]);

  const categoryName = getCategoryName(categoryId || '');
  const activeFiltersCount = 
    (shopFilter !== 'all' ? 1 : 0) + 
    (filter !== 'all' ? 1 : 0) +
    (discountOnly ? 1 : 0) +
    (newOnly ? 1 : 0);

  const FilterContent = () => (
    <div className="space-y-6">
      {/* Shop Filter */}
      <div>
        <label className="block text-sm font-semibold text-gray-900 mb-3">Дэлгүүр</label>
        <div className="flex flex-wrap gap-2">
          <button
            onClick={() => setShopFilter('all')}
            className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
              shopFilter === 'all'
                ? 'bg-blue-600 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            Бүгд
          </button>
          {availableShops.map((shop) => (
            <button
              key={shop.id}
              onClick={() => setShopFilter(shop.id)}
              className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                shopFilter === shop.id
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
              }`}
            >
              {shop.name}
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
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-3xl sm:text-4xl font-bold text-gray-900 mb-2">
          {categoryName}
        </h1>
        <p className="text-sm text-gray-500">
          {sortedProducts.length} бүтээгдэхүүн
        </p>
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

      <div className="md:flex md:gap-6">
        {/* Desktop Sidebar Filters */}
        <div className="hidden md:block md:w-64 lg:w-72 flex-shrink-0">
          <div className="bg-white rounded-lg shadow-sm p-5 sticky top-4">
            <div className="flex items-center justify-between mb-5">
              <div className="flex items-center gap-2">
                <Filter className="w-5 h-5 text-gray-500" />
                <span className="font-semibold text-gray-900">Шүүлтүүр</span>
              </div>
            </div>
            <div className="mb-5">
              <span className="text-sm text-gray-700 font-medium">Эрэмбэлэх:</span>
              <select
                value={sortBy}
                onChange={(e) => setSortBy(e.target.value as any)}
                className="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="popular">Алдартай</option>
                <option value="price-low">Үнэ: Бага → Их</option>
                <option value="price-high">Үнэ: Их → Бага</option>
              </select>
            </div>
            <FilterContent />
          </div>
        </div>

        {/* Mobile Filter Drawer */}
        <FilterDrawer isOpen={isFilterOpen} onClose={() => setIsFilterOpen(false)}>
          <FilterContent />
        </FilterDrawer>

        {/* Products Grid */}
        <div className="flex-1 min-w-0">
          {productsLoading ? (
            <div className="text-center py-16">
              <p className="text-gray-500 text-lg">Уншиж байна...</p>
            </div>
          ) : sortedProducts.length > 0 ? (
            <div className="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 gap-6">
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
      </div>
    </div>
  );
};