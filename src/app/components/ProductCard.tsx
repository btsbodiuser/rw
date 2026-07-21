import React from 'react';
import { Link } from 'react-router';
import { Star, Package, Clock, XCircle } from 'lucide-react';
import { Product } from '../types';
import { getShopInfo } from '../data/shops';
import { hexToLight } from '../services/api';

interface ProductCardProps {
  product: Product;
}

export const ProductCard = ({ product }: ProductCardProps) => {
  const discount = product.originalPrice
    ? Math.round(((product.originalPrice - product.price) / product.originalPrice) * 100)
    : 0;

  const shopInfo = getShopInfo(product.shop);

  return (
    <Link to={`/product/${product.slug}`} className="group">
      <div className="bg-white rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-shadow">
        <div className="relative aspect-square overflow-hidden bg-gray-100">
          <img
            src={product.image}
            alt={product.name}
            className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
          />
          {shopInfo && (
            <div
              className="absolute top-2 right-2 px-2 py-1 rounded text-xs font-bold"
              style={{ backgroundColor: hexToLight(shopInfo.hexColor), color: shopInfo.hexColor }}
            >
              {shopInfo.name}
            </div>
          )}
          {product.type === 'preorder' && (
            <div className="absolute top-2 left-2 bg-orange-500 text-white px-2 py-1 rounded text-xs font-medium flex items-center gap-1">
              <Clock className="w-3 h-3" />
              Урьдчилсан захиалга
            </div>
          )}
          {product.type === 'ready' && (
            <div className="absolute top-2 left-2 bg-green-600 text-white px-2 py-1 rounded text-xs font-medium flex items-center gap-1">
              <Package className="w-3 h-3" />
              Нөөцтэй
            </div>
          )}
          {product.orderStatus === 'closed' && (
            <div className="absolute top-10 left-2 bg-red-500 text-white px-2 py-1 rounded text-xs font-medium flex items-center gap-1">
              <XCircle className="w-3 h-3" />
              Захиалга хаагдсан
            </div>
          )}
          {discount > 0 && (
            <div className="absolute bottom-2 right-2 bg-red-500 text-white px-2 py-1 rounded text-xs font-bold">
              {discount}% хямдрал
            </div>
          )}
          {product.type === 'ready' && product.stock && product.stock < 10 && (
            <div className="absolute bottom-2 left-2 bg-blue-600 text-white px-2 py-1 rounded text-xs flex items-center gap-1">
              <Package className="w-3 h-3" />
              Зөвхөн {product.stock} ш
            </div>
          )}
        </div>

        <div className="p-4">
          <h3 className="font-medium text-gray-900 line-clamp-2 mb-1">
            {product.nameMn}
          </h3>
          <p className="text-sm text-gray-500 mb-2 line-clamp-1">{product.name}</p>

          <div className="flex items-center gap-1 mb-2">
            <Star className="w-4 h-4 fill-yellow-400 text-yellow-400" />
            <span className="text-sm font-medium">{product.rating}</span>
            <span className="text-sm text-gray-400">({product.reviews})</span>
          </div>

          <div className="flex items-baseline gap-2">
            {product.originalPrice && (
              <span className="text-sm text-gray-400 line-through">
                {product.originalPrice.toLocaleString()}₮
              </span>
            )}
            <span className="text-lg font-bold text-gray-900">
              {product.price.toLocaleString()}₮
            </span>
          </div>

          {product.priceTiers && product.priceTiers.length > 0 && !product.hasVariants && (
            <div className="mt-1.5 inline-block text-[11px] font-medium bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded">
              {product.priceTiers[0].minQty}+ авбал хямд
            </div>
          )}

          {product.type === 'preorder' && product.preorderDate && (
            <div className="mt-2 text-xs text-orange-600">
              Хүргэх: {new Date(product.preorderDate).toLocaleDateString('mn-MN', {
                month: 'long',
                day: 'numeric'
              })}
            </div>
          )}
        </div>
      </div>
    </Link>
  );
};