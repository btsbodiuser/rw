import React, { useState, useEffect, useMemo, useCallback, useRef } from 'react';
import { useParams, useNavigate } from 'react-router';
import { ProductCard } from '../components/ProductCard';
import { getShopInfo } from '../data/shops';
import { fetchProduct, mapApiProduct, hexToLight } from '../services/api';
import { useApi } from '../hooks/useApi';
import { Star, ShoppingCart, Package, Clock, Truck, Shield, ChevronLeft, ChevronRight, Store, Share2, Link } from 'lucide-react';
import { useApp } from '../context/AppContext';
import { toast } from 'sonner';
import { ProductVariant } from '../types';
import { getEffectivePrice, formatPriceTiers } from '../utils/helpers';

export const ProductDetailPage = () => {
  const { productId } = useParams<{ productId: string }>();
  const navigate = useNavigate();
  const { addToCart, addRecentlyViewed, cart } = useApp();
  const [quantity, setQuantity] = useState(1);
  const [galleryIndex, setGalleryIndex] = useState(0);
  const touchStartX = useRef<number | null>(null);
  const [selectedColorId, setSelectedColorId] = useState<number | null>(null);
  const [selectedSizeId, setSelectedSizeId] = useState<number | null>(null);

  const { data: apiProduct, loading } = useApi(
    () => fetchProduct(productId || ''),
    [productId]
  );
  const product = useMemo(() => apiProduct ? mapApiProduct(apiProduct) : null, [apiProduct]);

  // Variant helpers
  const uniqueColors = useMemo(() => {
    if (!product?.variants) return [];
    const seen = new Map<number, { id: number; name: string; hex: string }>();
    product.variants.forEach(v => {
      if (v.colorId && !seen.has(v.colorId)) {
        seen.set(v.colorId, { id: v.colorId, name: v.colorName || '', hex: v.colorHex || '#ccc' });
      }
    });
    return Array.from(seen.values());
  }, [product?.variants]);

  const uniqueSizes = useMemo(() => {
    if (!product?.variants) return [];
    const filtered = selectedColorId
      ? product.variants.filter(v => v.colorId === selectedColorId)
      : product.variants;
    const seen = new Map<number, { id: number; name: string }>();
    filtered.forEach(v => {
      if (v.sizeId && !seen.has(v.sizeId)) {
        seen.set(v.sizeId, { id: v.sizeId, name: v.sizeName || '' });
      }
    });
    return Array.from(seen.values());
  }, [product?.variants, selectedColorId]);

  const selectedVariant: ProductVariant | null = useMemo(() => {
    if (!product?.hasVariants || !product.variants) return null;
    return product.variants.find(v =>
      (v.colorId || null) === (selectedColorId || null) &&
      (v.sizeId || null) === (selectedSizeId || null)
    ) || null;
  }, [product?.variants, selectedColorId, selectedSizeId]);

  const effectivePrice = selectedVariant?.priceOverride
    ?? (product ? getEffectivePrice(product, quantity) : 0);
  const tierRows = useMemo(() =>
    product && !product.hasVariants ? formatPriceTiers(product.price, product.priceTiers) : [],
    [product]
  );
  // null = unlimited (preorder without a cap); number = limited stock
  const effectiveStock: number | null = selectedVariant ? selectedVariant.stock : (product?.stock ?? null);
  const stockToCheck: number | null = product?.hasVariants ? effectiveStock : (product?.stock ?? null);

  // Auto-select first color when product loads
  useEffect(() => {
    if (product?.hasVariants && uniqueColors.length > 0 && !selectedColorId) {
      setSelectedColorId(uniqueColors[0].id);
    }
  }, [product?.hasVariants, uniqueColors.length]);

  // Auto-select first size when color changes
  useEffect(() => {
    if (product?.hasVariants && uniqueSizes.length > 0) {
      setSelectedSizeId(uniqueSizes[0].id);
    } else if (product?.hasVariants && uniqueSizes.length === 0) {
      setSelectedSizeId(null);
    }
  }, [selectedColorId, uniqueSizes.length]);

  const isVideo = (url: string) => /\.(mp4|webm)(\?.*)?$/i.test(url);

  // Build gallery image list
  const allImages = useMemo(() => {
    if (!product) return [];
    return [product.image, ...product.images, ...(product.colorImages?.flatMap(ci => ci.images) || [])];
  }, [product?.image, product?.images, product?.colorImages]);

  // Swap to first color image when color changes
  useEffect(() => {
    if (product?.colorImages && selectedColorId) {
      const colorImg = product.colorImages.find(ci => ci.colorId === selectedColorId);
      if (colorImg && colorImg.images.length > 0) {
        const idx = allImages.indexOf(colorImg.images[0]);
        if (idx >= 0) { setGalleryIndex(idx); return; }
      }
    }
    setGalleryIndex(0);
  }, [selectedColorId, product?.colorImages, allImages]);

  // Add to recently viewed when product is loaded
  useEffect(() => {
    if (product && productId) {
      addRecentlyViewed(product);
    }
  }, [productId, product?.id, addRecentlyViewed]);

  if (loading) {
    return (
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
        <p className="text-gray-500 text-lg">Уншиж байна...</p>
      </div>
    );
  }

  if (!product) {
    return (
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
        <h1 className="text-2xl font-bold text-gray-900 mb-4">Бүтээгдэхүүн олдсонгүй</h1>
        <button
          onClick={() => navigate('/')}
          className="text-blue-600 hover:text-blue-700 font-medium"
        >
          Нүүр хуудас руу буцах
        </button>
      </div>
    );
  }

  const shopInfo = getShopInfo(product.shop);

  const handleShare = async () => {
    const url = window.location.href;
    if (navigator.share) {
      try {
        await navigator.share({ title: product.nameMn, url });
      } catch {
        // user cancelled
      }
    } else {
      await navigator.clipboard.writeText(url);
      toast.success('Холбоос хуулагдлаа!');
    }
  };

  const handleAddToCart = () => {
    if (product.hasVariants && !selectedVariant) {
      toast.error('Хувилбар сонгоно уу', {
        description: 'Өнгө болон хэмжээгээ сонгоно уу.',
      });
      return;
    }
    if (stockToCheck !== null) {
      const variantId = selectedVariant?.id;
      const alreadyInCart = cart.find(i =>
        i.product.id === product.id && (i.variantId || undefined) === (variantId || undefined)
      )?.quantity || 0;
      const remaining = stockToCheck - alreadyInCart;
      if (remaining <= 0) {
        toast.error('Сагсанд аль хэдийн дээд хэмжээгээр нэмэгдсэн байна', {
          description: `Нийт боломжит тоо: ${stockToCheck} ширхэг.`,
        });
        return;
      }
      if (quantity > remaining) {
        toast.error('Нөөц хүрэлцэхгүй байна', {
          description: `Зөвхөн ${remaining} ширхэг нэмэх боломжтой (сагсанд ${alreadyInCart} байна).`,
        });
        return;
      }
    }
    const variantLabel = selectedVariant
      ? [
          product.variants?.find(v => v.id === selectedVariant.id)?.colorName,
          product.variants?.find(v => v.id === selectedVariant.id)?.sizeName,
        ].filter(Boolean).join(' ')
      : undefined;
    const cartProduct = selectedVariant?.priceOverride
      ? { ...product, price: selectedVariant.priceOverride }
      : product;
    addToCart({
      product: cartProduct,
      quantity,
      variantId: selectedVariant?.id,
      variantLabel,
    });
    toast.success('Сагсанд нэмэгдлээ!', {
      description: `${quantity}x ${product.nameMn}${variantLabel ? ` (${variantLabel})` : ''}`,
    });
  };

  const discount = product.originalPrice
    ? Math.round(((product.originalPrice - product.price) / product.originalPrice) * 100)
    : 0;

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      {/* Back Button & Share */}
      <div className="flex items-center justify-between mb-6">
        <button
          onClick={() => navigate(-1)}
          className="flex items-center text-gray-600 hover:text-gray-900 font-medium"
        >
          <ChevronLeft className="w-5 h-5" />
          Буцах
        </button>
        <button
          onClick={handleShare}
          className="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors"
        >
          <Share2 className="w-4 h-4" />
          Хуваалцах
        </button>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">
        {/* Product Image */}
        <div className="relative">
          <div
            className="aspect-square rounded-xl overflow-hidden bg-gray-100 sticky top-24 relative select-none"
            onTouchStart={e => { touchStartX.current = e.touches[0].clientX; }}
            onTouchEnd={e => {
              if (touchStartX.current === null) return;
              const diff = e.changedTouches[0].clientX - touchStartX.current;
              touchStartX.current = null;
              if (Math.abs(diff) > 50) {
                if (diff > 0) setGalleryIndex(i => i > 0 ? i - 1 : allImages.length - 1);
                else setGalleryIndex(i => i < allImages.length - 1 ? i + 1 : 0);
              }
            }}
          >
            {isVideo(allImages[galleryIndex] || '') ? (
              <video
                key={allImages[galleryIndex]}
                src={allImages[galleryIndex]}
                className="w-full h-full object-cover"
                autoPlay
                muted
                loop
                playsInline
              />
            ) : (
              <img
                src={allImages[galleryIndex] || product.image}
                alt={product.name}
                className="w-full h-full object-cover"
              />
            )}
            {product.type === 'preorder' && (
              <div className="absolute top-4 left-4 bg-orange-500 text-white px-3 py-1.5 rounded-lg font-medium flex items-center gap-2">
                <Clock className="w-4 h-4" />
                Урьдчилсан захиалга
              </div>
            )}
            {discount > 0 && (
              <div className="absolute top-4 right-4 bg-red-500 text-white px-3 py-1.5 rounded-lg font-bold">
                {discount}% хямдрал
              </div>
            )}
            {allImages.length > 1 && (
              <>
                <button
                  type="button"
                  onClick={(e) => { e.stopPropagation(); setGalleryIndex(i => i > 0 ? i - 1 : allImages.length - 1); }}
                  className="absolute left-2 top-1/2 -translate-y-1/2 z-10 w-10 h-10 rounded-full bg-white/90 shadow-md flex items-center justify-center text-gray-700 hover:bg-white active:scale-95 transition-all"
                >
                  <ChevronLeft className="w-5 h-5" />
                </button>
                <button
                  type="button"
                  onClick={(e) => { e.stopPropagation(); setGalleryIndex(i => i < allImages.length - 1 ? i + 1 : 0); }}
                  className="absolute right-2 top-1/2 -translate-y-1/2 z-10 w-10 h-10 rounded-full bg-white/90 shadow-md flex items-center justify-center text-gray-700 hover:bg-white active:scale-95 transition-all"
                >
                  <ChevronRight className="w-5 h-5" />
                </button>
                <div className="absolute bottom-3 left-1/2 -translate-x-1/2 z-10 bg-black/50 text-white text-xs px-2.5 py-1 rounded-full">
                  {galleryIndex + 1} / {allImages.length}
                </div>
              </>
            )}
          </div>
          {allImages.length > 1 && (
            <div className="flex gap-2 mt-3 overflow-x-auto pb-2">
              {allImages.map((img, i) => (
                <button
                  key={i}
                  onClick={() => setGalleryIndex(i)}
                  className={`flex-shrink-0 w-16 h-16 rounded-lg overflow-hidden border-2 relative ${galleryIndex === i ? 'border-blue-500' : 'border-gray-200'}`}
                >
                  {isVideo(img) ? (
                    <>
                      <video src={img} className="w-full h-full object-cover" muted playsInline preload="metadata" />
                      <div className="absolute inset-0 flex items-center justify-center bg-black/20">
                        <svg className="w-6 h-6 text-white drop-shadow" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                      </div>
                    </>
                  ) : (
                    <img src={img} alt="" className="w-full h-full object-cover" />
                  )}
                </button>
              ))}
            </div>
          )}
        </div>

        {/* Product Info */}
        <div>
          {/* Shop Badge */}
          {shopInfo && (
            <button
              onClick={() => navigate(`/shop/${product.shop}`)}
              className="px-4 py-2 rounded-lg font-semibold mb-4 inline-flex items-center gap-2 hover:shadow-md transition-shadow"
              style={{ backgroundColor: hexToLight(shopInfo.hexColor), color: shopInfo.hexColor }}
            >
              <Store className="w-4 h-4" />
              {shopInfo.name}
            </button>
          )}

          <div className="mb-4">
            <h1 className="text-3xl sm:text-4xl font-bold text-gray-900 mb-2">
              {product.nameMn}
            </h1>
            <p className="text-xl text-gray-600">{product.name}</p>
          </div>

          {/* Rating */}
          <div className="flex items-center gap-2 mb-6">
            <div className="flex items-center gap-1">
              {[...Array(5)].map((_, i) => (
                <Star
                  key={i}
                  className={`w-5 h-5 ${
                    i < Math.floor(product.rating)
                      ? 'fill-yellow-400 text-yellow-400'
                      : 'text-gray-300'
                  }`}
                />
              ))}
            </div>
            <span className="font-medium">{product.rating}</span>
            <span className="text-gray-400">({product.reviews} үнэлгээ)</span>
          </div>

          {/* Price */}
          <div className="mb-6">
            {product.originalPrice && (
              <div className="text-lg text-gray-400 line-through mb-1">
                {product.originalPrice.toLocaleString()}₮
              </div>
            )}
            <div className="text-4xl font-bold text-gray-900">
              {effectivePrice.toLocaleString()}₮
            </div>
            {selectedVariant?.priceOverride && selectedVariant.priceOverride !== product.price && (
              <div className="text-sm text-gray-400 line-through">
                {product.price.toLocaleString()}₮
              </div>
            )}
            {!product.hasVariants && effectivePrice < product.price && (
              <div className="text-sm text-gray-400 line-through">
                {product.price.toLocaleString()}₮
              </div>
            )}
          </div>

          {/* Price tiers — quantity-based bulk discount table */}
          {tierRows.length > 0 && (
            <div className="mb-6 border border-blue-100 bg-blue-50/40 rounded-lg p-3">
              <div className="text-sm font-medium text-blue-900 mb-2">Олноор авбал хямд</div>
              <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                {tierRows.map(row => {
                  const active = quantity >= row.minQty &&
                    (tierRows.find(r => r.minQty > row.minQty && quantity >= r.minQty) === undefined);
                  return (
                    <div
                      key={row.minQty}
                      className={`text-sm rounded-md px-2 py-1.5 flex items-center justify-between ${
                        active ? 'bg-blue-600 text-white font-semibold' : 'bg-white text-gray-700 border border-blue-100'
                      }`}
                    >
                      <span>{row.label}</span>
                      <span>{row.price}</span>
                    </div>
                  );
                })}
              </div>
            </div>
          )}

          {/* Variant Selector */}
          {product.hasVariants && (
            <div className="mb-6 space-y-4">
              {/* Color Picker */}
              {uniqueColors.length > 0 && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Өнгө: <span className="font-normal text-gray-500">{uniqueColors.find(c => c.id === selectedColorId)?.name}</span>
                  </label>
                  <div className="flex flex-wrap gap-2">
                    {uniqueColors.map(color => (
                      <button
                        key={color.id}
                        onClick={() => setSelectedColorId(color.id)}
                        className={`w-10 h-10 rounded-full border-2 transition-all ${
                          selectedColorId === color.id
                            ? 'border-blue-600 ring-2 ring-blue-200 scale-110'
                            : 'border-gray-300 hover:border-gray-400'
                        }`}
                        style={{ backgroundColor: color.hex }}
                        title={color.name}
                      />
                    ))}
                  </div>
                </div>
              )}

              {/* Size Picker */}
              {uniqueSizes.length > 0 && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Хэмжээ:
                  </label>
                  <div className="flex flex-wrap gap-2">
                    {uniqueSizes.map(size => {
                      const variant = product.variants?.find(v => v.colorId === selectedColorId && v.sizeId === size.id);
                      const outOfStock = product.type === 'ready' && variant && variant.stock !== null && variant.stock <= 0;
                      return (
                        <button
                          key={size.id}
                          onClick={() => !outOfStock && setSelectedSizeId(size.id)}
                          disabled={outOfStock}
                          className={`px-4 py-2 rounded-lg border text-sm font-medium transition-all ${
                            selectedSizeId === size.id
                              ? 'border-blue-600 bg-blue-50 text-blue-700'
                              : outOfStock
                                ? 'border-gray-200 bg-gray-100 text-gray-400 cursor-not-allowed line-through'
                                : 'border-gray-300 hover:border-gray-400 text-gray-700'
                          }`}
                        >
                          {size.name}
                        </button>
                      );
                    })}
                  </div>
                </div>
              )}

              {/* Variant Stock Info */}
              {selectedVariant && product.type === 'ready' && (
                <div className={`text-sm font-medium ${effectiveStock !== null && effectiveStock > 0 ? 'text-green-600' : 'text-red-600'}`}>
                  {effectiveStock !== null && effectiveStock > 0 ? `Нөөцтэй (${effectiveStock} ширхэг)` : 'Дууссан'}
                </div>
              )}
              {selectedVariant && product.type === 'preorder' && effectiveStock !== null && (
                <div className={`text-sm font-medium ${effectiveStock > 0 ? 'text-orange-600' : 'text-red-600'}`}>
                  {effectiveStock > 0 ? `${effectiveStock} ширхэг үлдсэн` : 'Захиалга дууссан'}
                </div>
              )}
            </div>
          )}

          {/* Description */}
          {product.descriptionMn && product.descriptionMn.trim() !== "" && (
            <div className="mb-6 p-4 bg-gray-50 rounded-lg">
              <p className="text-gray-700">
                {product.descriptionMn.split('\n').map((line, i) => (
                  <span key={i}>
                    {line}
                    <br />
                  </span>
                ))}
              </p>
            </div>
          )}

          {/* Stock/Pre-order Info */}
          {product.type === 'ready' && product.stock ? (
            <>
              {/*
              <div className="mb-6 flex items-center gap-2 text-green-600">
                <Package className="w-5 h-5" />
                <span className="font-medium">Нөөцтэй ({product.stock} ширхэг)</span>
              </div>
              */}
            </>
          ) : product.type === 'preorder' && product.preorderDate ? (
            <div className="mb-6 p-4 bg-orange-50 border border-orange-200 rounded-lg">
              <div className="flex items-center gap-2 text-orange-700 font-medium mb-1">
                <Clock className="w-5 h-5" />
                Урьдчилсан захиалга
              </div>
              <p className="text-sm text-orange-600">
                Хүргэх огноо: {new Date(product.preorderDate).toLocaleDateString('mn-MN', {
                  year: 'numeric',
                  month: 'long',
                  day: 'numeric'
                })}
              </p>
            </div>
          ) : null}

          {/* Quantity */}
          {product.orderStatus === 'closed' ? (
            <div className="mb-8 p-4 bg-red-50 border border-red-200 rounded-lg">
              <div className="flex items-center gap-2 text-red-700 font-medium">
                <Package className="w-5 h-5" />
                Захиалга хаагдсан
              </div>
              <p className="text-sm text-red-600 mt-1">
                Энэ бүтээгдэхүүний захиалга одоогоор хаалттай байна.
              </p>
            </div>
          ) : (
          <>
          <div className="mb-6">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Тоо ширхэг
            </label>
            <div className="flex items-center gap-3">
              <button
                onClick={() => setQuantity(Math.max(1, quantity - 1))}
                className="w-10 h-10 rounded-lg border border-gray-300 hover:bg-gray-50 font-medium"
              >
                -
              </button>
              <input
                type="number"
                value={quantity}
                onChange={(e) => {
                  const maxQty = stockToCheck ?? Infinity;
                  setQuantity(Math.min(maxQty, Math.max(1, parseInt(e.target.value) || 1)));
                }}
                className="w-20 h-10 text-center border border-gray-300 rounded-lg font-medium"
                min="1"
                max={stockToCheck ?? undefined}
              />
              <button
                onClick={() => {
                  const maxQty = stockToCheck ?? Infinity;
                  setQuantity(Math.min(maxQty, quantity + 1));
                }}
                className="w-10 h-10 rounded-lg border border-gray-300 hover:bg-gray-50 font-medium"
              >
                +
              </button>
            </div>
          </div>

          {/* Add to Cart Button */}
          <button
            onClick={handleAddToCart}
            className="w-full bg-blue-600 text-white py-4 rounded-lg font-semibold text-lg hover:bg-blue-700 transition-colors flex items-center justify-center gap-2 mb-4"
          >
            <ShoppingCart className="w-5 h-5" />
            Сагсанд нэмэх
          </button>

          <button
            onClick={() => {
              handleAddToCart();
              navigate('/cart');
            }}
            className="w-full bg-gray-900 text-white py-4 rounded-lg font-semibold text-lg hover:bg-gray-800 transition-colors mb-8"
          >
            Худалдаж авах
          </button>
          </>
          )}

          {/* Features */}
          <div className="space-y-4 border-t pt-6">
            <div className="flex items-center gap-3 text-gray-700">
              <Truck className="w-5 h-5 text-blue-600" />
              <span>Шуурхай хүргэлт </span>
            </div>
            <div className="flex items-center gap-3 text-gray-700">
              <Shield className="w-5 h-5 text-blue-600" />
              <span>Баталгаатай бүтэээгдэхүүн</span>
            </div>
            <div className="flex items-center gap-3 text-gray-700">
              <Package className="w-5 h-5 text-blue-600" />
              <span>100 хувь сэтгэл ханамж</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};