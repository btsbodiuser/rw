import React, { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router';
import { Package, Truck, CheckCircle, XCircle, Clock, MapPin, Phone, User } from 'lucide-react';
import { fetchOrderStatus, fetchSettings, OrderStatusResponse, fixImageUrl } from '../services/api';
import { useApi } from '../hooks/useApi';

export const OrderTrackingPage = () => {
  const { orderNumber } = useParams<{ orderNumber: string }>();
  const navigate = useNavigate();
  const [searchOrderNumber, setSearchOrderNumber] = useState(orderNumber || '');
  const [order, setOrder] = useState<OrderStatusResponse | null>(null);
  const [loading, setLoading] = useState(false);
  const [searched, setSearched] = useState(false);
  const { data: settings } = useApi(() => fetchSettings(), []);
  const phone = String(settings?.['phone'] ?? '');
  const deliveryFeeEnabled = settings?.delivery_fee_enabled === undefined ? true : !!settings.delivery_fee_enabled;

  // Load order when URL param changes
  useEffect(() => {
    if (orderNumber) {
      setSearchOrderNumber(orderNumber);
      setLoading(true);
      setSearched(true);
      fetchOrderStatus(orderNumber)
        .then(setOrder)
        .catch(() => setOrder(null))
        .finally(() => setLoading(false));
    }
  }, [orderNumber]);


  const getStatusInfo = (status: string) => {
    const statusMap: Record<string, { icon: typeof Clock; color: string; bgColor: string; borderColor: string; label: string }> = {
      pending:            { icon: Clock,        color: 'text-yellow-600', bgColor: 'bg-yellow-50', borderColor: 'border-yellow-200', label: 'Хүлээгдэж байна' },
      confirmed:          { icon: CheckCircle,  color: 'text-blue-600',   bgColor: 'bg-blue-50',   borderColor: 'border-blue-200',   label: 'Баталгаажсан' },
      shipping:           { icon: Truck,        color: 'text-purple-600', bgColor: 'bg-purple-50', borderColor: 'border-purple-200', label: 'Хүргэлтэнд' },
      delivering:         { icon: Truck,        color: 'text-purple-600', bgColor: 'bg-purple-50', borderColor: 'border-purple-200', label: 'Хүргэлтэнд' },
      cargo_shipping:     { icon: Truck,        color: 'text-orange-600', bgColor: 'bg-orange-50', borderColor: 'border-orange-200', label: 'Солонгосоос ирж байна' },
      cargo_arrived:      { icon: CheckCircle,  color: 'text-teal-600',   bgColor: 'bg-teal-50',   borderColor: 'border-teal-200',   label: 'Монголд ирсэн' },
      ready_pickup:       { icon: CheckCircle,  color: 'text-indigo-600', bgColor: 'bg-indigo-50', borderColor: 'border-indigo-200', label: 'Авахад бэлэн' },
      delivered:          { icon: CheckCircle,  color: 'text-green-600',  bgColor: 'bg-green-50',  borderColor: 'border-green-200',  label: 'Хүргэгдсэн' },
      partially_delivered:{ icon: Truck,        color: 'text-amber-600',  bgColor: 'bg-amber-50',  borderColor: 'border-amber-200',  label: 'Хэсэгчлэн хүргэсэн' },
      picked_up:          { icon: CheckCircle,  color: 'text-green-600',  bgColor: 'bg-green-50',  borderColor: 'border-green-200',  label: 'Авсан' },
      completed:          { icon: CheckCircle,  color: 'text-green-600',  bgColor: 'bg-green-50',  borderColor: 'border-green-200',  label: 'Дууссан' },
      cancelled:          { icon: XCircle,      color: 'text-red-600',    bgColor: 'bg-red-50',    borderColor: 'border-red-200',    label: 'Цуцлагдсан' },
    };
    return statusMap[status] || { icon: Clock, color: 'text-gray-600', bgColor: 'bg-gray-50', borderColor: 'border-gray-200', label: status };
  };

  const getTrackingSteps = (status: string, statusFlow: string[]) => {
    const iconMap: Record<string, typeof Clock> = {
      pending: Clock, confirmed: CheckCircle, cargo_shipping: Truck, cargo_arrived: CheckCircle,
      delivering: Truck, partially_delivered: Truck, shipping: Truck, delivered: CheckCircle,
      ready_pickup: CheckCircle, picked_up: CheckCircle, completed: CheckCircle,
    };
    const labelMap: Record<string, string> = {
      pending: 'Захиалга хүлээн авсан', confirmed: 'Баталгаажуулсан', cargo_shipping: 'Солонгосоос ирж байна',
      cargo_arrived: 'Монголд ирсэн', delivering: 'Хүргэлтэнд явж байна', partially_delivered: 'Хэсэгчлэн хүргэсэн',
      shipping: 'Хүргэлтэнд явж байна', delivered: 'Хүргэгдсэн', ready_pickup: 'Авахад бэлэн',
      picked_up: 'Авсан', completed: 'Дууссан',
    };
    const steps = statusFlow.length > 0 ? statusFlow : ['pending', 'confirmed', 'delivering', 'delivered'];
    const currentIndex = steps.indexOf(status);
    const effectiveIndex = currentIndex !== -1 ? currentIndex
      : (status === 'completed' || status === 'delivered' || status === 'picked_up') ? steps.length - 1
      : status === 'partially_delivered' ? Math.max(steps.indexOf('delivering'), 0)
      : -1;
    return steps.map((key, index) => ({
      key, label: labelMap[key] || key, icon: iconMap[key] || Clock,
      completed: effectiveIndex >= 0 && index <= effectiveIndex,
      active: index === effectiveIndex,
    }));
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchOrderNumber.trim()) navigate(`/order-tracking/${searchOrderNumber.trim()}`);
  };


  return (
    <div className="min-h-screen bg-gray-50 py-12">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* Header */}
        <div className="text-center mb-12">
          <h1 className="text-3xl sm:text-4xl font-bold text-gray-900 mb-4">Захиалга хайх</h1>
          <p className="text-gray-600">Захиалгын дугаараар илгээлтийн мэдээлэл шалгах</p>
        </div>

        {/* Search Form */}
        <div className="bg-white rounded-xl shadow-sm p-6 mb-8">
          <form onSubmit={handleSearch} className="flex gap-4">
            <input
              type="text"
              value={searchOrderNumber}
              onChange={(e) => setSearchOrderNumber(e.target.value)}
              placeholder="Захиалгын дугаар (жнь: NO12345678)"
              className="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            <button type="submit" className="px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors">
              Хайх
            </button>
          </form>
        </div>

        {/* Order Details */}
        {loading ? (
          <div className="bg-white rounded-xl shadow-sm p-12 text-center">
            <p className="text-gray-500 text-lg">Уншиж байна...</p>
          </div>
        ) : order ? (
          <div className="space-y-6">
            {/* Status Card */}
            <div className={`bg-white rounded-xl shadow-sm p-6 border-l-4 ${getStatusInfo(order.status).borderColor}`}>
              <div className="flex items-center gap-4 mb-4">
                {(() => {
                  const StatusIcon = getStatusInfo(order.status).icon;
                  return <StatusIcon className={`w-12 h-12 ${getStatusInfo(order.status).color}`} />;
                })()}
                <div className="flex-1">
                  <h2 className="text-2xl font-bold text-gray-900">{order.status_label || getStatusInfo(order.status).label}</h2>
                  <p className="text-gray-600">Захиалга: {order.order_number}</p>
                </div>
                <div className="text-right">
                  <p className="text-sm text-gray-500">Огноо</p>
                  <p className="font-semibold">
                    {new Date(order.created_at).toLocaleDateString('mn-MN', { year: 'numeric', month: 'long', day: 'numeric' })}
                  </p>
                </div>
              </div>

              {/* Tracking Progress */}
              {(() => {
                const steps = getTrackingSteps(order.status, order.status_flow);
                return (
                  <div className="relative mt-8">
                    <div className="absolute top-5 left-0 right-0 h-1 bg-gray-200">
                      <div className="h-full bg-blue-600 transition-all duration-500"
                        style={{ width: `${(steps.filter(s => s.completed).length / steps.length) * 100}%` }} />
                    </div>
                    <div className="relative flex justify-between">
                      {steps.map((step) => {
                        const StepIcon = step.icon;
                        return (
                          <div key={step.key} className="flex flex-col items-center" style={{ width: `${100 / steps.length}%` }}>
                            <div className={`w-10 h-10 rounded-full flex items-center justify-center mb-2 ${step.completed ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-400'}`}>
                              <StepIcon className="w-5 h-5" />
                            </div>
                            <p className={`text-xs text-center ${step.completed ? 'text-gray-900 font-medium' : 'text-gray-500'}`}>{step.label}</p>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                );
              })()}
            </div>

            {/* Shipping Address */}
            <div className="bg-white rounded-xl shadow-sm p-6">
              <h3 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                <MapPin className="w-5 h-5 text-blue-600" />
                Хүргэх хаяг
              </h3>
              <div className="space-y-2 text-gray-700">
                <div className="flex items-center gap-2"><User className="w-4 h-4 text-gray-400" /><span>{order.customer_name}</span></div>
                <div className="flex items-center gap-2"><Phone className="w-4 h-4 text-gray-400" /><span>{order.customer_phone}</span></div>
                <div className="flex items-start gap-2">
                  <MapPin className="w-4 h-4 text-gray-400 mt-1" />
                  <div><p>{order.district_mn || order.district}</p></div>
                </div>
              </div>
            </div>

            {/* Order Items */}
            <div className="bg-white rounded-xl shadow-sm p-6">
              <h3 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                <Package className="w-5 h-5 text-blue-600" />
                Захиалсан бараа ({order.items.length})
              </h3>
              <div className="space-y-4">
                {order.items.map((item, index) => (
                  <div key={index} className="flex gap-4 pb-4 border-b last:border-0">
                    <img
                      src={fixImageUrl(item.image) || 'https://placehold.co/80x80?text=No+Image'}
                      alt={item.product_name}
                      className="w-20 h-20 object-cover rounded-lg"
                    />
                    <div className="flex-1">
                      <h4 className="font-semibold text-gray-900">{item.product_name}</h4>
                      {item.variant_label && <p className="text-sm text-gray-500">{item.variant_label}</p>}
                      <div className="flex justify-between items-center mt-2">
                        <span className="text-sm text-gray-500">Тоо: {item.quantity}</span>
                        <span className="font-semibold text-gray-900">{(item.product_price * item.quantity).toLocaleString()}₮</span>
                      </div>
                      {item.cargo_fee > 0 && (
                        <div className="flex items-center gap-2 mt-1">
                          {item.cargo_fee_paid ? (
                            <><span className="text-xs text-gray-500">Ачаа: {item.cargo_fee.toLocaleString()}₮</span>
                            <span className="px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Төлсөн</span></>
                          ) : (
                            <span className="px-1.5 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">Ачаа: {item.cargo_fee.toLocaleString()}₮ (төлөөгүй)</span>
                          )}
                        </div>
                      )}
                    </div>
                  </div>
                ))}
              </div>

              {/* Order Summary */}
              <div className="mt-6 pt-6 border-t space-y-2">
                <div className="flex justify-between text-gray-700">
                  <span>Барааны үнэ</span>
                  <span>{order.subtotal.toLocaleString()}₮</span>
                </div>
                {order.delivery_fee > 0 ? (
                  <div className="flex justify-between text-gray-700">
                    <span>Хүргэлт</span>
                    <span className="font-medium">{order.delivery_fee.toLocaleString()}₮</span>
                  </div>
                ) : (order.fulfillment === 'delivery' && !deliveryFeeEnabled) && (
                  <div className="flex justify-between text-gray-700">
                    <span>Хүргэлт</span>
                    <span className="text-sm text-gray-600">Хүргэлтийн үед бодогдоно</span>
                  </div>
                )}
                {order.cargo_fee > 0 && (
                  <div className="flex justify-between text-gray-700">
                    <span>Ачааны хураамж</span>
                    <span className="flex items-center gap-2">
                      {order.cargo_fee_paid ? (
                        <><span>{order.cargo_fee.toLocaleString()}₮</span>
                        <span className="px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Төлсөн</span></>
                      ) : (
                        <span className="px-1.5 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">Ачаа ирэхэд төлнө</span>
                      )}
                    </span>
                  </div>
                )}
                <div className="flex justify-between text-lg font-bold text-gray-900 pt-2 border-t">
                  <span>Нийт дүн</span>
                  <span>{order.total.toLocaleString()}₮</span>
                </div>
                <div className="flex justify-between items-center pt-3 border-t mt-2">
                  <span className="text-sm text-gray-600">Төлбөрийн арга</span>
                  <span className="text-sm font-medium text-gray-900">
                    {order.payment_method === 'transfer' ? 'Шилжүүлэг'
                      : order.payment_method === 'bonum' ? 'Bonum'
                      : order.payment_method === 'qpay' ? 'QPay'
                      : order.payment_method === 'cash' ? 'Бэлэн'
                      : order.payment_method === 'card' ? 'Карт'
                      : order.payment_method}
                  </span>
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-sm text-gray-600">Төлбөрийн статус</span>
                  {order.payment_status === 'paid'
                    ? <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Төлсөн</span>
                    : order.payment_status === 'refunded'
                    ? <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Буцаагдсан</span>
                    : <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Хүлээгдэж байна</span>
                  }
                </div>
              </div>

            </div>

            {/* Help Section */}
            <div className="bg-blue-50 rounded-xl p-6 text-center">
              <h3 className="font-bold text-gray-900 mb-2">Асуулт байна уу?</h3>
              <p className="text-gray-600 mb-4">Манай тусламжийн төв танд туслахад бэлэн байна</p>
              <div className="flex flex-wrap gap-4 justify-center">
                {phone && (
                  <a href={`tel:${phone.replace(/[^+\d]/g, '')}`}
                    className="inline-flex items-center gap-2 px-6 py-2 bg-white text-blue-600 font-semibold rounded-lg hover:bg-blue-100 transition-colors">
                    <Phone className="w-4 h-4" />{phone}
                  </a>
                )}
                <button onClick={() => navigate('/contact')} className="px-6 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors">
                  Холбоо барих
                </button>
              </div>
            </div>
          </div>
        ) : searched ? (
          <div className="bg-white rounded-xl shadow-sm p-12 text-center">
            <XCircle className="w-16 h-16 text-red-500 mx-auto mb-4" />
            <h2 className="text-2xl font-bold text-gray-900 mb-2">Захиалга олдсонгүй</h2>
            <p className="text-gray-600 mb-6">Захиалгын дугаар: <span className="font-semibold">{searchOrderNumber}</span></p>
            <button onClick={() => { setSearchOrderNumber(''); setSearched(false); navigate('/order-tracking'); }}
              className="text-blue-600 hover:text-blue-700 font-medium">
              Дахин хайх
            </button>
          </div>
        ) : (
          <div className="bg-white rounded-xl shadow-sm p-12 text-center">
            <Package className="w-16 h-16 text-gray-300 mx-auto mb-4" />
            <h2 className="text-xl font-bold text-gray-900 mb-2">Захиалгын дугаар оруулна уу</h2>
            <p className="text-gray-600">Та дээрх хайлтын хэсэгт захиалгын дугаараа оруулна уу</p>
          </div>
        )}

        {/* Back Button */}
        <div className="mt-8 text-center">
          <button onClick={() => navigate('/profile')} className="text-blue-600 hover:text-blue-700 font-medium">
            ← Профайл руу буцах
          </button>
        </div>
      </div>

    </div>
  );
};
