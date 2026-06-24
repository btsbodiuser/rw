import React from 'react';
import { useNavigate } from 'react-router';
import { useApp } from '../context/AppContext';
import { Trash2, ShoppingBag, Plus, Minus } from 'lucide-react';
import { toast } from 'sonner';
import { fetchSettings } from '../services/api';
import { useApi } from '../hooks/useApi';
import { getEffectivePrice } from '../utils/helpers';

export const CartPage = () => {
  const navigate = useNavigate();
  const { cart, updateCartQuantity, removeFromCart, cartTotal, cartCount } = useApp();

  const { data: settingsData } = useApi(() => fetchSettings(), []);
  const cargoRate = typeof settingsData?.cargo_rate_per_kg === 'number' ? settingsData.cargo_rate_per_kg : 0;

  const hasCargoItems = cart.some(item => item.product.type === 'preorder' && item.product.weightKg && cargoRate > 0);

  const cargoFee = cart.reduce((sum, item) => {
    if (item.product.type === 'preorder' && item.product.weightKg && !item.product.hideCargoFee) {
      return sum + item.product.weightKg * cargoRate * item.quantity;
    }
    return sum;
  }, 0);

  const total = cartTotal + cargoFee;

  const handleRemoveItem = (productName: string, productId: string, variantId?: number) => {
    removeFromCart(productId, variantId);
    toast.success('Бүтээгдэхүүн хасагдлаа', {
      description: productName,
    });
  };

  const handleUpdateQuantity = (productId: string, newQuantity: number, productName: string, variantId?: number) => {
    const item = cart.find(i => i.product.id === productId && (i.variantId || undefined) === (variantId || undefined));
    const oldQuantity = item?.quantity || 0;

    if (item && newQuantity > oldQuantity) {
      const cap = item.product.hasVariants
        ? (item.product.variants?.find(v => v.id === variantId)?.stock ?? null)
        : (item.product.stock ?? null);
      if (cap !== null && newQuantity > cap) {
        toast.error('Нөөц хүрэлцэхгүй байна', {
          description: `Зөвхөн ${cap} ширхэг үлдсэн байна.`,
        });
        return;
      }
    }

    updateCartQuantity(productId, newQuantity, variantId);
    if (newQuantity > oldQuantity) {
      toast.success('Тоо ширхэг нэмэгдлээ', {
        description: `${productName}: ${newQuantity}`,
      });
    } else if (newQuantity < oldQuantity) {
      toast.info('Тоо ширхэг хасагдлаа', {
        description: `${productName}: ${newQuantity}`,
      });
    }
  };

  if (cart.length === 0) {
    return (
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div className="text-center">
          <ShoppingBag className="w-20 h-20 text-gray-300 mx-auto mb-4" />
          <h2 className="text-2xl font-bold text-gray-900 mb-2">Таны сагс хоосон байна</h2>
          <p className="text-gray-600 mb-6">Бүтээгдэхүүн нэмж эхлээрэй!</p>
          <button
            onClick={() => navigate('/')}
            className="bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors"
          >
            Дэлгүүр үзэх
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <h1 className="text-3xl font-bold text-gray-900 mb-8">Миний сагс</h1>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Cart Items */}
        <div className="lg:col-span-2 space-y-4">
          {cart.map((item) => {
            const unitPrice = getEffectivePrice(item.product, item.quantity);
            return (
            <div key={`${item.product.id}-${item.variantId || ''}`} className="bg-white rounded-lg shadow-sm p-4">
              <div className="flex gap-4">
                {/* Product Image */}
                <img
                  src={item.product.image}
                  alt={item.product.name}
                  className="w-24 h-24 object-cover rounded-lg flex-shrink-0"
                />

                {/* Product Info */}
                <div className="flex-1 min-w-0">
                  <h3 className="font-semibold text-gray-900 mb-1 line-clamp-1">
                    {item.product.nameMn}
                  </h3>
                  {item.variantLabel && (
                    <p className="text-sm text-gray-500 mb-1">{item.variantLabel}</p>
                  )}
                  {/* <p className="text-sm text-gray-600 mb-2 line-clamp-1">{item.product.name}</p> */}
                  
                  {item.product.type === 'preorder' && (
                    <span className="inline-block text-xs bg-orange-100 text-orange-700 px-2 py-1 rounded mb-2">
                      Урьдчилсан захиалга
                    </span>
                  )}

                  <div className="flex items-center justify-between mt-3">
                    {/* Quantity Controls */}
                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => handleUpdateQuantity(item.product.id, item.quantity - 1, item.product.nameMn, item.variantId)}
                        className="w-8 h-8 rounded border border-gray-300 hover:bg-gray-50 flex items-center justify-center"
                      >
                        <Minus className="w-4 h-4" />
                      </button>
                      <span className="w-12 text-center font-medium">{item.quantity}</span>
                      <button
                        onClick={() => handleUpdateQuantity(item.product.id, item.quantity + 1, item.product.nameMn, item.variantId)}
                        className="w-8 h-8 rounded border border-gray-300 hover:bg-gray-50 flex items-center justify-center"
                      >
                        <Plus className="w-4 h-4" />
                      </button>
                    </div>

                    {/* Price */}
                    <div className="text-right">
                      <div className="font-bold text-lg text-gray-900">
                        {(unitPrice * item.quantity).toLocaleString()}₮
                      </div>
                      <div className="text-sm text-gray-500">
                        {unitPrice.toLocaleString()}₮ / ш
                      </div>
                      {unitPrice < item.product.price && (
                        <div className="text-xs text-green-600 mt-0.5">
                          Олноор авсан хямдрал
                        </div>
                      )}
                      {item.product.type === 'preorder' && item.product.weightKg && cargoRate > 0 && (
                        <div className="text-xs text-orange-600 mt-1">
                          {item.product.hideCargoFee
                            ? 'Ачаа ирэхэд төлнө'
                            : `+ ачааны ${(item.product.weightKg * cargoRate * item.quantity).toLocaleString()}₮`}
                        </div>
                      )}
                    </div>
                  </div>
                </div>

                {/* Remove Button */}
                <button
                  onClick={() => handleRemoveItem(item.product.nameMn, item.product.id, item.variantId)}
                  className="flex-shrink-0 text-red-500 hover:text-red-600 p-2"
                >
                  <Trash2 className="w-5 h-5" />
                </button>
              </div>
            </div>
            );
          })}
        </div>

        {/* Order Summary */}
        <div className="lg:col-span-1">
          <div className="bg-white rounded-lg shadow-sm p-6 sticky top-24">
            <h2 className="text-xl font-bold text-gray-900 mb-4">Захиалгын дүн</h2>

            <div className="space-y-3 mb-6">
              <div className="flex justify-between text-gray-700">
                <span>Нийт ({cartCount} бараа)</span>
                <span>{cartTotal.toLocaleString()}₮</span>
              </div>
              {hasCargoItems && (
                <>
                  {cargoFee > 0 && (
                    <div className="flex justify-between text-orange-700">
                      <span>Ачааны төлбөр</span>
                      <span>{cargoFee.toLocaleString()}₮</span>
                    </div>
                  )}
                  {cart.some(item => item.product.type === 'preorder' && item.product.weightKg && item.product.hideCargoFee) && (
                    <div className="flex justify-between text-orange-700">
                      <span>Ачааны төлбөр *</span>
                      <span className="text-sm">Ачаа ирэхэд төлнө</span>
                    </div>
                  )}
                </>
              )}
              <div className="border-t pt-3 flex justify-between text-lg font-bold text-gray-900">
                <span>Нийт дүн</span>
                <span>{total.toLocaleString()}₮</span>
              </div>
            </div>

            <button
              onClick={() => navigate('/checkout')}
              className="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors mb-3"
            >
              Худалдаж авах
            </button>

            <button
              onClick={() => navigate('/')}
              className="w-full bg-gray-100 text-gray-700 py-3 rounded-lg font-semibold hover:bg-gray-200 transition-colors"
            >
              Үргэлжлүүлэх
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};