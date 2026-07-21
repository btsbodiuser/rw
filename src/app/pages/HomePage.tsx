import React from 'react';
import { Link } from 'react-router';
import { ProductCard } from '../components/ProductCard';
import { fetchProducts, fetchShops, fetchSettings, fetchCategories, mapApiProduct, mapApiShop, hexToLight, fixImageUrl } from '../services/api';
import { setShopCache } from '../data/shops';
import { useApi } from '../hooks/useApi';
import { ChevronRight, TrendingUp, Package, Sparkles, Clock, X as XIcon } from 'lucide-react';
import { useApp } from '../context/AppContext';

export const HomePage = () => {
  const { data: featuredData } = useApi(() => fetchProducts({ sort: 'popular', limit: '200' }), []);
  const { data: shopsData } = useApi(() => fetchShops(), []);
  const { data: settingsData } = useApi(() => fetchSettings(), []);
  const { data: categoriesData } = useApi(() => fetchCategories(), []);
  const { recentlyViewed, clearRecentlyViewed } = useApp();

  const s = (key: string, fallback: string) => String(settingsData?.[key] ?? fallback);
  const isCategoryGridEnabled = settingsData?.home_categories_enabled !== false && settingsData?.home_categories_enabled !== '0' && settingsData?.home_categories_enabled !== 0;
  const isReadyCardEnabled = settingsData?.home_category_ready_enabled !== false && settingsData?.home_category_ready_enabled !== '0' && settingsData?.home_category_ready_enabled !== 0;
  const isPreorderCardEnabled = settingsData?.home_category_preorder_enabled !== false && settingsData?.home_category_preorder_enabled !== '0' && settingsData?.home_category_preorder_enabled !== 0;
  const isDiscountCardEnabled = settingsData?.home_category_discount_enabled !== false && settingsData?.home_category_discount_enabled !== '0' && settingsData?.home_category_discount_enabled !== 0;
  const isNewCardEnabled = settingsData?.home_category_new_enabled !== false && settingsData?.home_category_new_enabled !== '0' && settingsData?.home_category_new_enabled !== 0;
  const hasCategoryCards = isReadyCardEnabled || isPreorderCardEnabled || isDiscountCardEnabled || isNewCardEnabled;

  const getCategoryCardImage = (settingKey: string, fallback: string): string => {
    const value = settingsData?.[settingKey];
    if (typeof value === 'string' && value.trim()) {
      let imagePath = value;
      if (!imagePath.startsWith('http')) {
        if (!imagePath.startsWith('/backend/')) {
          imagePath = imagePath.startsWith('backend/') ? `/${imagePath}` : `/backend/uploads/media/${imagePath}`;
        }
      }
      return fixImageUrl(imagePath) || fallback;
    }
    return fallback;
  };

  const allProducts = (featuredData?.products || []).map(mapApiProduct);
  const featuredProducts = allProducts.filter((p) => p.originalPrice).slice(0, 8);
  const newProducts = allProducts.filter((p) => p.type === 'preorder');

  const shops = (shopsData || []).map(mapApiShop);
  const categories = categoriesData || [];
  const shouldRenderCategoryGrid = isCategoryGridEnabled && (categories.length > 0 || hasCategoryCards);
  // Update the shop cache so getShopInfo works elsewhere
  if (shops.length > 0) setShopCache(shops);

  return (
    <div>
      {/* Hero Section */}
      <section className="bg-gradient-to-r from-blue-600 to-blue-800 text-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
          <div className="max-w-7xl">
            <h1 className="text-3xl sm:text-4xl lg:text-5xl font-bold mb-4">
              {s('hero_title', 'Чанартай бүтээгдэхүүн')}
              <span className="block text-blue-200 mt-2">{s('hero_subtitle', 'Танай гэрт хүргэнэ')}</span>
            </h1>
            <p className="text-base sm:text-lg text-blue-100 mb-6">
              {s('hero_description', 'Солонгосын томоохон дэлгүүрүүдээс шууд авчирсан гэр ахуйн бараа, гоо сайхны бүтээгдэхүүн, эрүүл мэндийн нэмэлт бүтээгдэхүүн')}
            </p>
            <div className="flex flex-wrap gap-4">
              <Link
                to="/all-items?sort=newest"
                className="bg-white text-blue-600 px-6 py-3 rounded-lg font-semibold hover:bg-blue-50 transition-colors inline-flex items-center gap-2"
              >
                <Package className="w-5 h-5" />
                {s('hero_btn1_text', 'Бүх бараа үзэх')}
              </Link>
              <Link
                to="/all-items?type=preorder&sort=newest"
                className="bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-600 transition-colors inline-flex items-center gap-2"
              >
                <Sparkles className="w-5 h-5" />
                {s('hero_btn2_text', 'Урьдчилсан захиалга')}
              </Link>
              <Link
                to="/all-items?type=ready&sort=newest"
                className="bg-green-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-500 transition-colors inline-flex items-center gap-2"
              >
                <Package className="w-5 h-5" />
                {s('hero_btn3_text', 'Бэлэн бараа')}
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* Trust Section - Korean Retailers */}
      <section className="bg-white py-12 border-b">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-8">
            <h2 className="text-2xl sm:text-3xl font-bold text-gray-900 mb-3">
              {s('shops_title', 'Солонгосын шилдэг дэлгүүрүүдээс шууд авчирна')}
            </h2>
            <p className="text-gray-600 max-w-2xl mx-auto">
              {s('shops_description', 'Бид Солонгосын хамгийн том, итгэлтэй дэлгүүрүүдээс жинхэнэ бүтээгдэхүүнийг шууд авчирч, Улаанбаатар хотод танд хүргэнэ')}
            </p>
          </div>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
            {shops.map((shop) => (
              <Link
                key={shop.id}
                to={`/shop/${shop.id}`}
                className="rounded-xl p-4 md:p-6 text-center hover:shadow-lg transition-shadow group"
                style={{ background: `linear-gradient(to bottom right, ${hexToLight(shop.hexColor, 0.08)}, ${hexToLight(shop.hexColor, 0.18)})` }}
              >
                <div className="text-2xl md:text-3xl font-bold mb-2 group-hover:scale-110 transition-transform" style={{ color: shop.hexColor }}>
                  {shop.name}
                </div>
                <p className="text-xs md:text-sm text-gray-700">{shop.descriptionMn.split(' ').slice(0, 3).join(' ')}</p>
              </Link>
            ))}
          </div>
        </div>
      </section>

      {/* Categories Grid */}
      {shouldRenderCategoryGrid && (
      <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <h2 className="text-2xl sm:text-3xl font-bold text-gray-900 mb-8">Ангилал</h2>
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 md:gap-6">
          {categories.map((cat) => (
            <Link
              key={cat.id}
              to={`/category/${cat.slug}`}
              className="group relative h-48 md:h-56 rounded-xl overflow-hidden shadow-lg hover:shadow-xl transition-shadow col-span-2"
            >
              {cat.image ? (
                <img
                  src={fixImageUrl(cat.image) || ''}
                  alt={cat.name_mn || cat.name}
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                />
              ) : (
                <div className="w-full h-full bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center">
                  <span className="text-5xl">{cat.icon || '📦'}</span>
                </div>
              )}
              <div className="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent flex flex-col justify-end p-4 md:p-6">
                <h3 className="text-white text-xl md:text-2xl font-bold mb-2">{cat.name_mn || cat.name}</h3>
                <div className="flex items-center text-white font-medium text-sm md:text-base">
                  Үзэх <ChevronRight className="w-4 h-4 md:w-5 md:h-5 ml-1" />
                </div>
              </div>
            </Link>
          ))}

          {isReadyCardEnabled && (
          <Link
            to="/all-items?type=ready"
            className="group relative h-48 md:h-56 rounded-xl overflow-hidden shadow-lg hover:shadow-xl transition-shadow col-span-1"
          >
            <img
              src={getCategoryCardImage('home_category_ready_image', 'https://images.unsplash.com/photo-1586495777744-4413f21062fa?w=800')}
              alt="Ready Stock"
              className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            />
            <div className="absolute inset-0 bg-gradient-to-t from-green-900/70 to-transparent flex flex-col justify-end p-4">
              <h3 className="text-white text-lg md:text-xl font-bold mb-1">Бэлэн бараа</h3>
              <div className="flex items-center text-white font-medium text-xs md:text-sm">
                Үзэх <ChevronRight className="w-4 h-4 ml-1" />
              </div>
            </div>
          </Link>
          )}

          {isPreorderCardEnabled && (
          <Link
            to="/all-items?type=preorder&sort=newest"
            className="group relative h-48 md:h-56 rounded-xl overflow-hidden shadow-lg hover:shadow-xl transition-shadow col-span-1"
          >
            <img
              src={getCategoryCardImage('home_category_preorder_image', 'https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?w=800')}
              alt="Preorder"
              className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            />
            <div className="absolute inset-0 bg-gradient-to-t from-orange-900/70 to-transparent flex flex-col justify-end p-4">
              <h3 className="text-white text-lg md:text-xl font-bold mb-1">Урьдчилсан захиалга</h3>
              <div className="flex items-center text-white font-medium text-xs md:text-sm">
                Үзэх <ChevronRight className="w-4 h-4 ml-1" />
              </div>
            </div>
          </Link>
          )}

          {isDiscountCardEnabled && (
          <Link
            to="/all-items?discount=true"
            className="group relative h-48 md:h-56 rounded-xl overflow-hidden shadow-lg hover:shadow-xl transition-shadow col-span-1"
          >
            <img
              src={getCategoryCardImage('home_category_discount_image', 'https://images.unsplash.com/photo-1607083206968-13611e3d76db?w=800')}
              alt="Discounts"
              className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            />
            <div className="absolute inset-0 bg-gradient-to-t from-red-900/70 to-transparent flex flex-col justify-end p-4">
              <h3 className="text-white text-lg md:text-xl font-bold mb-1">Хямдрал</h3>
              <div className="flex items-center text-white font-medium text-xs md:text-sm">
                Үзэх <ChevronRight className="w-4 h-4 ml-1" />
              </div>
            </div>
          </Link>
          )}

          {isNewCardEnabled && (
          <Link
            to="/all-items?new=true"
            className="group relative h-48 md:h-56 rounded-xl overflow-hidden shadow-lg hover:shadow-xl transition-shadow col-span-1"
          >
            <img
              src={getCategoryCardImage('home_category_new_image', 'https://images.unsplash.com/photo-1600269452121-4f2416e55c28?w=800')}
              alt="New Arrivals"
              className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            />
            <div className="absolute inset-0 bg-gradient-to-t from-purple-900/70 to-transparent flex flex-col justify-end p-4">
              <h3 className="text-white text-lg md:text-xl font-bold mb-1">Шинэ бараа</h3>
              <div className="flex items-center text-white font-medium text-xs md:text-sm">
                Үзэх <ChevronRight className="w-4 h-4 ml-1" />
              </div>
            </div>
          </Link>
          )}
        </div>
      </section>
      )}

      {/* Featured Products */}
      <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div className="flex items-center justify-between mb-8">
          <div className="flex items-center gap-3">
            <TrendingUp className="w-7 h-7 text-red-500" />
            <h2 className="text-2xl sm:text-3xl font-bold text-gray-900">Онцлох хямдрал</h2>
          </div>
          <Link to="/all-items" className="text-blue-600 hover:text-blue-700 font-medium flex items-center">
            Бүгдийг үзэх <ChevronRight className="w-5 h-5" />
          </Link>
        </div>
        <div className="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          {featuredProducts.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </div>
      </section>

      {/* Pre-order Section */}
      {newProducts.length > 0 && (
        <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
          <div className="flex items-center justify-between mb-8">
            <div className="flex items-center gap-3">
              <Sparkles className="w-7 h-7 text-orange-500" />
              <h2 className="text-2xl sm:text-3xl font-bold text-gray-900">Урьдчилсан захиалга</h2>
            </div>
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {newProducts.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        </section>
      )}

      {/* Recently Viewed Section */}
      {recentlyViewed.length > 0 && (
        <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 bg-gray-50 rounded-2xl">
          <div className="flex items-center justify-between mb-8">
            <div className="flex items-center gap-3">
              <Clock className="w-7 h-7 text-purple-500" />
              <h2 className="text-2xl sm:text-3xl font-bold text-gray-900">Саяхан үзсэн</h2>
            </div>
            <button
              onClick={clearRecentlyViewed}
              className="text-sm text-gray-500 hover:text-red-500 transition-colors flex items-center gap-1"
            >
              <XIcon className="w-4 h-4" />
              Цэвэрлэх
            </button>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {recentlyViewed.slice(0, 4).map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        </section>
      )}

      {/* Features */}
      <section className="bg-white py-12 mt-12">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
            <div>
              <div className="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                <Package className="w-8 h-8 text-blue-600" />
              </div>
              <h3 className="font-bold text-lg mb-2">{s('feature1_title', 'Үнэгүй хүргэлт')}</h3>
              <p className="text-gray-600 text-sm">{s('feature1_desc', '50,000₮-с дээш захиалгад')}</p>
            </div>
            <div>
              <div className="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                <Sparkles className="w-8 h-8 text-blue-600" />
              </div>
              <h3 className="font-bold text-lg mb-2">{s('feature2_title', 'Жинхэнэ бараа')}</h3>
              <p className="text-gray-600 text-sm">{s('feature2_desc', 'Солонгосоос шууд авчирсан')}</p>
            </div>
            <div>
              <div className="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                <TrendingUp className="w-8 h-8 text-blue-600" />
              </div>
              <h3 className="font-bold text-lg mb-2">{s('feature3_title', 'Шилдэг үнэ')}</h3>
              <p className="text-gray-600 text-sm">{s('feature3_desc', 'Өрсөлдөхүйц үнэ баталгаатай')}</p>
            </div>
          </div>
        </div>
      </section>
    </div>
  );
};