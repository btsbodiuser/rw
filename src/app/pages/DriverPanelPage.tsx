import React, { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router';
import { Truck, Phone, MapPin, Package, CheckCircle, XCircle, ChevronRight, RefreshCw, AlertCircle, ShoppingBag } from 'lucide-react';

const API_BASE = `${import.meta.env.BASE_URL}backend/api`;

interface Delivery {
  id: number;
  batch_id: number | null;
  status: 'assigned' | 'picked_up' | 'delivered' | 'failed';
  assigned_at: string;
  picked_up_at: string | null;
  delivered_at: string | null;
  notes: string | null;
  order_number: string;
  customer_name: string;
  customer_phone: string;
  total: number;
  payment_status: string;
  delivery_fee: number;
  district_name: string | null;
  khoroo_number: number | null;
  khoroo_name: string;
  address: string | null;
  detail_address: string | null;
  item_count: number;
  products: { product_name: string; quantity: number; product_price: number }[];
}

interface Batch {
  id: number;
  status: 'assigned' | 'in_progress';
  notes: string | null;
  created_at: string;
  total_orders: number;
  delivered_count: number;
  failed_count: number;
}

interface CompletedDelivery {
  id: number;
  status: 'delivered' | 'failed';
  delivered_at: string;
  order_number: string;
  customer_name: string;
  total: number;
  payment_status: string;
  district_name: string | null;
}

interface DriverData {
  driver: { id: number; name: string; phone: string };
  batches: Batch[];
  deliveries: Delivery[];
  completed_today: CompletedDelivery[];
}

const statusLabels: Record<string, string> = {
  assigned: 'Хуваарилсан',
  picked_up: 'Авсан',
  delivered: 'Хүргэсэн',
  failed: 'Амжилтгүй',
};

const statusColors: Record<string, string> = {
  assigned: 'bg-yellow-100 text-yellow-700',
  picked_up: 'bg-blue-100 text-blue-700',
  delivered: 'bg-green-100 text-green-700',
  failed: 'bg-red-100 text-red-700',
};

function formatPrice(amount: number): string {
  return new Intl.NumberFormat('mn-MN').format(amount) + '₮';
}

export const DriverPanelPage: React.FC = () => {
  const { token } = useParams<{ token: string }>();
  const [data, setData] = useState<DriverData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [updating, setUpdating] = useState<number | null>(null);
  const [tab, setTab] = useState<'active' | 'completed'>('active');
  const [deliveryFeeEnabled, setDeliveryFeeEnabled] = useState(true);

  useEffect(() => {
    fetch(`${API_BASE}/settings.php`)
      .then(r => r.json())
      .then(j => {
        const v = j?.settings?.delivery_fee_enabled;
        setDeliveryFeeEnabled(v === undefined ? true : !!v);
      })
      .catch(() => { /* keep default */ });
  }, []);

  const fetchData = useCallback(async () => {
    try {
      const res = await fetch(`${API_BASE}/driver-deliveries.php?token=${token}`);
      if (!res.ok) {
        if (res.status === 401) throw new Error('Хандах эрхгүй. Линк буруу байна.');
        throw new Error('Серверийн алдаа');
      }
      const json = await res.json();
      setData(json);
      setError(null);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, [token]);

  useEffect(() => {
    fetchData();
    // Auto-refresh every 30 seconds
    const interval = setInterval(fetchData, 30000);
    return () => clearInterval(interval);
  }, [fetchData]);

  const updateStatus = async (deliveryId: number, newStatus: string) => {
    setUpdating(deliveryId);
    try {
      const res = await fetch(`${API_BASE}/driver-deliveries.php?token=${token}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_status', delivery_id: deliveryId, new_status: newStatus }),
      });
      if (!res.ok) throw new Error('Update failed');
      await fetchData();
    } catch {
      alert('Алдаа гарлаа. Дахин оролдоно уу.');
    } finally {
      setUpdating(null);
    }
  };

  const startBatch = async (batchId: number) => {
    try {
      await fetch(`${API_BASE}/driver-deliveries.php?token=${token}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_batch_status', batch_id: batchId, new_status: 'in_progress' }),
      });
      await fetchData();
    } catch {
      alert('Алдаа гарлаа.');
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center max-w-sm">
          <AlertCircle className="w-12 h-12 text-red-400 mx-auto mb-4" />
          <p className="text-gray-600">{error || 'Мэдээлэл ачаалахад алдаа гарлаа'}</p>
          <button onClick={() => { setLoading(true); fetchData(); }} className="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">
            Дахин оролдох
          </button>
        </div>
      </div>
    );
  }

  const { driver, batches, deliveries, completed_today } = data;
  const assignedCount = deliveries.filter(d => d.status === 'assigned').length;
  const pickedUpCount = deliveries.filter(d => d.status === 'picked_up').length;

  // Group deliveries by batch
  const batchedDeliveries: Record<number, Delivery[]> = {};
  const unbatched: Delivery[] = [];
  deliveries.forEach(d => {
    if (d.batch_id) {
      if (!batchedDeliveries[d.batch_id]) batchedDeliveries[d.batch_id] = [];
      batchedDeliveries[d.batch_id].push(d);
    } else {
      unbatched.push(d);
    }
  });

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-blue-600 text-white px-4 py-4 sticky top-0 z-20">
        <div className="max-w-lg mx-auto flex items-center justify-between">
          <div className="flex items-center gap-3">
            <Truck className="w-6 h-6" />
            <div>
              <h1 className="font-bold text-lg leading-tight">{driver.name}</h1>
              <p className="text-blue-200 text-xs">{driver.phone}</p>
            </div>
          </div>
          <button onClick={() => { setLoading(true); fetchData(); }} className="p-2 rounded-lg bg-blue-700 hover:bg-blue-800 active:bg-blue-900">
            <RefreshCw className="w-5 h-5" />
          </button>
        </div>
      </div>

      <div className="max-w-lg mx-auto px-4 py-4">
        {/* Stats */}
        <div className="grid grid-cols-3 gap-3 mb-4">
          <div className="bg-white rounded-xl p-3 shadow-sm border border-gray-100 text-center">
            <p className="text-2xl font-bold text-yellow-600">{assignedCount}</p>
            <p className="text-xs text-gray-500">Хүлээж буй</p>
          </div>
          <div className="bg-white rounded-xl p-3 shadow-sm border border-gray-100 text-center">
            <p className="text-2xl font-bold text-blue-600">{pickedUpCount}</p>
            <p className="text-xs text-gray-500">Авч явж буй</p>
          </div>
          <div className="bg-white rounded-xl p-3 shadow-sm border border-gray-100 text-center">
            <p className="text-2xl font-bold text-green-600">{completed_today.length}</p>
            <p className="text-xs text-gray-500">Өнөөдөр хүргэсэн</p>
          </div>
        </div>

        {/* Tabs */}
        <div className="flex gap-1 bg-gray-100 rounded-xl p-1 mb-4">
          <button
            onClick={() => setTab('active')}
            className={`flex-1 py-2.5 rounded-lg text-sm font-medium transition ${tab === 'active' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500'}`}
          >
            Хүргэлт {deliveries.length > 0 && <span className="ml-1 px-1.5 py-0.5 bg-blue-500 text-white rounded-full text-xs">{deliveries.length}</span>}
          </button>
          <button
            onClick={() => setTab('completed')}
            className={`flex-1 py-2.5 rounded-lg text-sm font-medium transition ${tab === 'completed' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500'}`}
          >
            Дууссан {completed_today.length > 0 && <span className="ml-1 px-1.5 py-0.5 bg-green-500 text-white rounded-full text-xs">{completed_today.length}</span>}
          </button>
        </div>

        {/* Active Tab */}
        {tab === 'active' && (
          <>
            {deliveries.length === 0 ? (
              <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                <Package className="w-12 h-12 text-gray-300 mx-auto mb-3" />
                <p className="text-gray-400">Идэвхтэй хүргэлт байхгүй</p>
              </div>
            ) : (
              <>
                {/* Batched deliveries */}
                {batches.map(batch => (
                  <div key={batch.id} className="mb-4">
                    <div className="bg-white rounded-t-2xl border border-gray-100 px-4 py-3 flex items-center justify-between">
                      <div>
                        <span className="font-semibold text-gray-900 text-sm">Багц #{batch.id}</span>
                        <span className={`ml-2 px-2 py-0.5 rounded-full text-xs font-medium ${batch.status === 'assigned' ? 'bg-yellow-100 text-yellow-700' : 'bg-blue-100 text-blue-700'}`}>
                          {batch.status === 'assigned' ? 'Хуваарилсан' : 'Хүргэж байна'}
                        </span>
                        <p className="text-xs text-gray-400 mt-0.5">
                          {batch.delivered_count}/{batch.total_orders} хүргэсэн
                          {batch.failed_count > 0 && <span className="text-red-500"> · {batch.failed_count} амжилтгүй</span>}
                        </p>
                      </div>
                      {batch.status === 'assigned' && (
                        <button
                          onClick={() => startBatch(batch.id)}
                          className="px-3 py-2 bg-blue-600 text-white rounded-xl text-xs font-medium active:bg-blue-800"
                        >
                          Эхлүүлэх
                        </button>
                      )}
                    </div>
                    {/* Progress bar */}
                    <div className="bg-gray-50 border-x border-gray-100 px-4 py-2">
                      <div className="w-full bg-gray-200 rounded-full h-1.5">
                        <div
                          className={`h-1.5 rounded-full transition-all ${(batch.delivered_count + batch.failed_count) >= batch.total_orders ? 'bg-green-500' : 'bg-blue-500'}`}
                          style={{ width: `${batch.total_orders > 0 ? Math.min(((batch.delivered_count + batch.failed_count) / batch.total_orders) * 100, 100) : 0}%` }}
                        />
                      </div>
                    </div>
                    <div className="bg-white rounded-b-2xl border-x border-b border-gray-100 divide-y divide-gray-50">
                      {(batchedDeliveries[batch.id] || []).map(del => (
                        <DeliveryCard key={del.id} delivery={del} updating={updating} onUpdateStatus={updateStatus} deliveryFeeEnabled={deliveryFeeEnabled} />
                      ))}
                    </div>
                  </div>
                ))}

                {/* Unbatched */}
                {unbatched.length > 0 && (
                  <div className="bg-white rounded-2xl shadow-sm border border-gray-100 divide-y divide-gray-50">
                    {unbatched.map(del => (
                      <DeliveryCard key={del.id} delivery={del} updating={updating} onUpdateStatus={updateStatus} />
                    ))}
                  </div>
                )}
              </>
            )}
          </>
        )}

        {/* Completed Tab */}
        {tab === 'completed' && (
          <>
            {completed_today.length === 0 ? (
              <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                <CheckCircle className="w-12 h-12 text-gray-300 mx-auto mb-3" />
                <p className="text-gray-400">Өнөөдөр хүргэлт дуусаагүй</p>
              </div>
            ) : (
              <div className="bg-white rounded-2xl shadow-sm border border-gray-100 divide-y divide-gray-50">
                {completed_today.map(del => (
                  <div key={del.id} className="px-4 py-3 flex items-center justify-between">
                    <div>
                      <div className="flex items-center gap-2">
                        <span className="font-medium text-sm text-gray-900">#{del.order_number}</span>
                        <span className={`px-1.5 py-0.5 rounded-full text-xs font-medium ${statusColors[del.status]}`}>
                          {statusLabels[del.status]}
                        </span>
                      </div>
                      <p className="text-xs text-gray-400 mt-0.5">
                        {del.customer_name} · {del.district_name || ''}
                      </p>
                    </div>
                    <div className="text-right">
                      <p className="font-medium text-sm">{formatPrice(del.total)}</p>
                      <p className={`text-xs ${del.payment_status === 'paid' ? 'text-green-600' : 'text-red-500'}`}>
                        {del.payment_status === 'paid' ? 'Төлсөн' : 'Төлөөгүй'}
                      </p>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
};

// Delivery card sub-component
interface DeliveryCardProps {
  delivery: Delivery;
  updating: number | null;
  onUpdateStatus: (id: number, status: string) => void;
  deliveryFeeEnabled: boolean;
}

const DeliveryCard: React.FC<DeliveryCardProps> = ({ delivery: del, updating, onUpdateStatus, deliveryFeeEnabled }) => {
  const isUpdating = updating === del.id;
  const addressParts = [
    del.district_name,
    del.khoroo_name || (del.khoroo_number ? `${del.khoroo_number}-р хороо` : null),
  ].filter(Boolean).join(', ');

  return (
    <div className="px-4 py-4">
      {/* Order info */}
      <div className="flex items-start justify-between mb-2">
        <div>
          <div className="flex items-center gap-2">
            <span className="font-bold text-base text-gray-900">#{del.order_number}</span>
            <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[del.status]}`}>
              {statusLabels[del.status]}
            </span>
            <span className={`px-1.5 py-0.5 rounded-full text-xs font-medium ${del.payment_status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
              {del.payment_status === 'paid' ? 'Төлсөн' : 'Төлөөгүй'}
            </span>
          </div>
          <p className="text-sm text-gray-700 mt-1 font-medium">{del.customer_name}</p>
        </div>
        <div className="text-right">
          <p className="font-bold text-base">{formatPrice(del.total)}</p>
          {del.delivery_fee > 0 ? (
            <p className="text-xs text-gray-500">үүнээс хүргэлт {formatPrice(del.delivery_fee)}</p>
          ) : !deliveryFeeEnabled && (
            <p className="text-xs text-amber-600">Хүргэлтийн төлбөр: тохиролцоно</p>
          )}
          <p className="text-xs text-gray-400">{del.item_count} бараа</p>
        </div>
      </div>

      {/* Address */}
      <div className="flex items-start gap-2 mb-2 text-sm text-gray-600">
        <MapPin className="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" />
        <div>
          {addressParts && <p>{addressParts}</p>}
          {del.address && <p>{del.address}</p>}
          {del.detail_address && <p className="text-gray-400">{del.detail_address}</p>}
        </div>
      </div>

      {/* Phone - clickable */}
      <a href={`tel:${del.customer_phone}`} className="flex items-center gap-2 text-sm text-blue-600 mb-2">
        <Phone className="w-4 h-4" />
        {del.customer_phone}
      </a>

      {/* Products */}
      {del.products && del.products.length > 0 && (
        <div className="bg-gray-50 rounded-lg p-2 mb-3">
          <div className="flex items-center gap-1.5 mb-1">
            <ShoppingBag className="w-3.5 h-3.5 text-gray-400" />
            <span className="text-xs font-medium text-gray-500">Бараа:</span>
          </div>
          <div className="space-y-0.5">
            {del.products.map((p, i) => (
              <div key={i} className="flex items-center justify-between text-xs">
                <span className="text-gray-700">{p.product_name} <span className="text-gray-400">×{p.quantity}</span></span>
                <span className="text-gray-500">{formatPrice(p.product_price * p.quantity)}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Action buttons */}
      <div className="flex gap-2">
        {del.status === 'assigned' && (
          <button
            onClick={() => onUpdateStatus(del.id, 'picked_up')}
            disabled={isUpdating}
            className="flex-1 py-3 bg-blue-600 text-white rounded-xl text-sm font-medium active:bg-blue-800 disabled:opacity-50 flex items-center justify-center gap-2"
          >
            {isUpdating ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Package className="w-4 h-4" />}
            Авсан
          </button>
        )}
        {del.status === 'picked_up' && (
          <>
            <button
              onClick={() => onUpdateStatus(del.id, 'delivered')}
              disabled={isUpdating}
              className="flex-1 py-3 bg-green-600 text-white rounded-xl text-sm font-medium active:bg-green-800 disabled:opacity-50 flex items-center justify-center gap-2"
            >
              {isUpdating ? <RefreshCw className="w-4 h-4 animate-spin" /> : <CheckCircle className="w-4 h-4" />}
              Хүргэсэн
            </button>
            <button
              onClick={() => { if (confirm('Амжилтгүй гэж тэмдэглэх үү?')) onUpdateStatus(del.id, 'failed'); }}
              disabled={isUpdating}
              className="py-3 px-4 bg-red-50 text-red-600 rounded-xl text-sm font-medium active:bg-red-100 disabled:opacity-50 flex items-center justify-center gap-2"
            >
              <XCircle className="w-4 h-4" />
            </button>
          </>
        )}
      </div>
    </div>
  );
};
