import React, { useState, useEffect, useRef, useCallback } from 'react';
import { useApp } from '../context/AppContext';
import { useNavigate } from 'react-router';
import { User, MapPin, LogOut, Plus, Trash2, Edit2, Package, Clock, CheckCircle, Truck, XCircle, Search, Home, CreditCard, Banknote, ShoppingBag, Loader2, QrCode, X } from 'lucide-react';

const API_BASE = `${import.meta.env.BASE_URL}backend/api`;
import { toast } from 'sonner';
import { fetchDistricts, fetchAddresses, createAddress, deleteAddress as apiDeleteAddress, ApiAddress, fetchCustomerOrders, ApiOrder, fixImageUrl, updateOrderFulfillment, authSendOtp, authSendEmailOtp, updatePhone, updateEmail, fetchSettings } from '../services/api';
import { useApi } from '../hooks/useApi';
import { District } from '../types';

export const ProfilePage = () => {
  const { user, authUser, logout, authToken, updateUserProfile, updateUserPhone, updateUserEmail } = useApp();
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState<'profile' | 'addresses' | 'orders'>('orders');
  const [isEditingProfile, setIsEditingProfile] = useState(false);
  const [profileForm, setProfileForm] = useState({ name: '' });
  const [showAddAddress, setShowAddAddress] = useState(false);
  const [selectedDistrict, setSelectedDistrict] = useState('');
  const [addresses, setAddresses] = useState<ApiAddress[]>([]);
  const [addressLoading, setAddressLoading] = useState(false);
  const [orders, setOrders] = useState<ApiOrder[]>([]);
  const [ordersLoading, setOrdersLoading] = useState(false);
  const [changingFulfillment, setChangingFulfillment] = useState<string | null>(null); // order_number
  const [fulfillmentForm, setFulfillmentForm] = useState({ district_id: '', khoroo_id: '', address: '', detail_address: '' });

  // Phone edit
  const [phoneEditStep, setPhoneEditStep] = useState<'idle' | 'input' | 'otp'>('idle');
  const [newPhone, setNewPhone] = useState('');
  const [phoneOtp, setPhoneOtp] = useState(['', '', '', '']);
  const [phoneEditLoading, setPhoneEditLoading] = useState(false);
  const phoneOtpRefs = useRef<(HTMLInputElement | null)[]>([]);

  // Email edit
  const [emailEditStep, setEmailEditStep] = useState<'idle' | 'input' | 'otp'>('idle');
  const [newEmail, setNewEmail] = useState('');
  const [emailOtp, setEmailOtp] = useState(['', '', '', '']);
  const [emailEditLoading, setEmailEditLoading] = useState(false);
  const emailOtpRefs = useRef<(HTMLInputElement | null)[]>([]);
  const [fulfillmentSubmitting, setFulfillmentSubmitting] = useState(false);
  const [newAddress, setNewAddress] = useState({
    district_id: '',
    khoroo_id: '',
    address: '',
    detail_address: '',
  });

  const { data: apiSettings } = useApi(() => fetchSettings(), []);
  const deliveryFeeEnabled = apiSettings?.delivery_fee_enabled === undefined ? true : !!apiSettings.delivery_fee_enabled;
  const qpayEnabled     = !!apiSettings?.payment_qpay_enabled;
  const bonumEnabled    = !!apiSettings?.payment_bonum_enabled;
  const storepayEnabled = !!apiSettings?.payment_storepay_enabled;

  // Cargo payment modal
  type CargoInvoice = { cargo_payment_id: number; invoice_id: string; qr_image?: string; urls?: { name: string; description: string; logo: string; link: string }[]; follow_up_link?: string };
  type PayMethod = 'qpay' | 'bonum' | 'storepay';
  const [cargoPayOrder, setCargoPayOrder]   = useState<import('../services/api').ApiOrder | null>(null);
  const [payMethod, setPayMethod]           = useState<PayMethod | null>(null);
  const [mobileInput, setMobileInput]       = useState('');
  const [payLoading, setPayLoading]         = useState(false);
  const [payError, setPayError]             = useState('');
  const [cargoInvoice, setCargoInvoice]     = useState<CargoInvoice | null>(null);
  const [cargoPaid, setCargoPaid]           = useState(false);
  const cargoPollRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const stopCargoPoll = useCallback(() => {
    if (cargoPollRef.current) { clearInterval(cargoPollRef.current); cargoPollRef.current = null; }
  }, []);
  useEffect(() => () => stopCargoPoll(), [stopCargoPoll]);

  const checkCargoPayment = useCallback(async (cpId: number) => {
    if (!authToken) return;
    try {
      const res = await fetch(`${API_BASE}/cargo-payment.php?action=check`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${authToken}` },
        body: JSON.stringify({ cargo_payment_id: cpId }),
      });
      const data = await res.json();
      if (data.paid) {
        stopCargoPoll();
        setCargoPaid(true);
        // Refresh orders list
        if (authToken) fetchCustomerOrders(authToken).then(data => setOrders(data.filter(o => o.status !== 'cancelled'))).catch(() => {});
      }
    } catch {}
  }, [authToken, stopCargoPoll]);

  const startCargoPoll = useCallback((cpId: number) => {
    stopCargoPoll();
    cargoPollRef.current = setInterval(() => checkCargoPayment(cpId), 3000);
  }, [checkCargoPayment, stopCargoPoll]);

  const handleCreateCargoInvoice = async () => {
    if (!authToken || !cargoPayOrder || !payMethod) return;
    if (payMethod === 'storepay' && !/^\d{8}$/.test(mobileInput)) { setPayError('8 оронтой утасны дугаар оруулна уу'); return; }
    setPayLoading(true); setPayError('');
    try {
      const res = await fetch(`${API_BASE}/cargo-payment.php?action=create`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${authToken}` },
        body: JSON.stringify({ order_number: cargoPayOrder.order_number, payment_method: payMethod, mobile_number: mobileInput }),
      });
      const data = await res.json();
      if (!res.ok || data.error) { setPayError(data.error || 'Алдаа гарлаа'); return; }
      setCargoInvoice(data);
      startCargoPoll(data.cargo_payment_id);
    } catch (e: unknown) {
      setPayError('Серверийн алдаа: ' + (e instanceof Error ? e.message : String(e)));
    } finally { setPayLoading(false); }
  };

  const closeCargoModal = () => {
    stopCargoPoll();
    setCargoPayOrder(null); setPayMethod(null); setCargoInvoice(null);
    setCargoPaid(false); setPayError(''); setMobileInput('');
  };

  const { data: apiDistricts } = useApi(() => fetchDistricts(), []);
  const districts: District[] = (apiDistricts || []).map(d => ({
    id: String(d.id),
    name: d.name_mn || d.name,
    khoroos: d.khoroos.map(k => ({ id: k.id, number: k.number, label: k.name || `${k.number}-р хороо` })),
  }));

  // Load addresses from API
  useEffect(() => {
    if (authToken && activeTab === 'addresses') {
      setAddressLoading(true);
      fetchAddresses(authToken)
        .then(setAddresses)
        .catch((err) => { toast.error(err.message || 'Хаяг уншихад алдаа гарлаа'); })
        .finally(() => setAddressLoading(false));
    }
  }, [authToken, activeTab]);

  // Load orders from API
  useEffect(() => {
    if (authToken && activeTab === 'orders') {
      setOrdersLoading(true);
      fetchCustomerOrders(authToken)
        .then(data => setOrders(data.filter(o => o.status !== 'cancelled')))
        .catch((err) => { toast.error(err.message || 'Захиалга уншихад алдаа гарлаа'); })
        .finally(() => setOrdersLoading(false));
    }
  }, [authToken, activeTab]);

  const handleProfileEdit = () => {
    if (!user) return;
    setProfileForm({ name: user.name });
    setIsEditingProfile(true);
  };

  const handleProfileSave = () => {
    updateUserProfile(profileForm.name);
    setIsEditingProfile(false);
    toast.success('Мэдээлэл шинэчлэгдлээ!');
  };

  const handleAddAddress = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!authToken) return;
    try {
      const created = await createAddress(authToken, {
        district_id: Number(newAddress.district_id),
        khoroo_id: Number(newAddress.khoroo_id),
        address: newAddress.address,
        detail_address: newAddress.detail_address || undefined,
      });
      setAddresses(prev => [created, ...prev]);
      setNewAddress({ district_id: '', khoroo_id: '', address: '', detail_address: '' });
      setSelectedDistrict('');
      setShowAddAddress(false);
      toast.success('Хаяг нэмэгдлээ!');
    } catch (err: any) {
      toast.error(err.message || 'Хаяг нэмэхэд алдаа гарлаа');
    }
  };

  const handleDeleteAddress = async (id: number) => {
    if (!authToken) return;
    try {
      await apiDeleteAddress(authToken, id);
      setAddresses(prev => prev.filter(a => a.id !== id));
      toast.success('Хаяг устгагдлаа');
    } catch (err: any) {
      toast.error(err.message || 'Хаяг устгахад алдаа гарлаа');
    }
  };

  const handleSendPhoneOtp = async () => {
    const clean = newPhone.replace(/\D/g, '');
    if (clean.length !== 8) { toast.error('8 оронтой утасны дугаар оруулна уу'); return; }
    setPhoneEditLoading(true);
    try {
      await authSendOtp(clean);
      setPhoneOtp(['', '', '', '']);
      setPhoneEditStep('otp');
      toast.success('Баталгаажуулах код илгээлээ');
      setTimeout(() => phoneOtpRefs.current[0]?.focus(), 100);
    } catch (err: any) {
      toast.error(err.message || 'Алдаа гарлаа');
    } finally {
      setPhoneEditLoading(false);
    }
  };

  const handleUpdatePhone = async (otpCode = '') => {
    if (!authToken) return;
    setPhoneEditLoading(true);
    try {
      const res = await updatePhone(authToken, newPhone.replace(/\D/g, ''), otpCode);
      updateUserPhone(res.phone);
      setPhoneEditStep('idle');
      setNewPhone('');
      setPhoneOtp(['', '', '', '']);
      toast.success('Утасны дугаар шинэчлэгдлээ');
    } catch (err: any) {
      toast.error(err.message || 'Алдаа гарлаа');
    } finally {
      setPhoneEditLoading(false);
    }
  };

  const handleSendEmailOtp = async () => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(newEmail)) { toast.error('Зөв имэйл хаяг оруулна уу'); return; }
    setEmailEditLoading(true);
    try {
      await authSendEmailOtp(newEmail, 'update');
      setEmailOtp(['', '', '', '']);
      setEmailEditStep('otp');
      toast.success('Баталгаажуулах код имэйлээр илгээлээ');
      setTimeout(() => emailOtpRefs.current[0]?.focus(), 100);
    } catch (err: any) {
      toast.error(err.message || 'Алдаа гарлаа');
    } finally {
      setEmailEditLoading(false);
    }
  };

  const handleUpdateEmail = async () => {
    const code = emailOtp.join('');
    if (code.length !== 4) { toast.error('4 оронтой код оруулна уу'); return; }
    if (!authToken) return;
    setEmailEditLoading(true);
    try {
      const res = await updateEmail(authToken, newEmail, code);
      updateUserEmail(res.email);
      setEmailEditStep('idle');
      setNewEmail('');
      setEmailOtp(['', '', '', '']);
      toast.success('Имэйл хаяг шинэчлэгдлээ');
    } catch (err: any) {
      toast.error(err.message || 'Алдаа гарлаа');
    } finally {
      setEmailEditLoading(false);
    }
  };

  const handleOtpBoxChange = (
    index: number, value: string,
    state: string[], setState: (v: string[]) => void,
    refs: React.MutableRefObject<(HTMLInputElement | null)[]>
  ) => {
    if (!/^\d*$/.test(value)) return;
    const next = [...state];
    next[index] = value.slice(-1);
    setState(next);
    if (value && index < 3) refs.current[index + 1]?.focus();
  };

  const handleOtpBoxKeyDown = (
    index: number, e: React.KeyboardEvent,
    state: string[],
    refs: React.MutableRefObject<(HTMLInputElement | null)[]>
  ) => {
    if (e.key === 'Backspace' && !state[index] && index > 0) refs.current[index - 1]?.focus();
  };

  const khoroos = districts.find(d => d.id === selectedDistrict)?.khoroos || [];
  const fulfillmentKhoroos = districts.find(d => d.id === fulfillmentForm.district_id)?.khoroos || [];

  const handleFulfillmentChange = async (orderNumber: string) => {
    if (!authToken || fulfillmentSubmitting) return;
    if (!fulfillmentForm.district_id || !fulfillmentForm.khoroo_id || !fulfillmentForm.address.trim()) {
      toast.error('Дүүрэг, хороо, хаяг оруулна уу');
      return;
    }
    setFulfillmentSubmitting(true);
    try {
      await updateOrderFulfillment(authToken, orderNumber, {
        fulfillment: 'delivery',
        district_id: Number(fulfillmentForm.district_id),
        khoroo_id: Number(fulfillmentForm.khoroo_id),
        address: fulfillmentForm.address.trim(),
        detail_address: fulfillmentForm.detail_address.trim(),
      });
      // Refetch orders to get updated address/district data
      fetchCustomerOrders(authToken).then(data => setOrders(data.filter(o => o.status !== 'cancelled'))).catch(() => {});
      setChangingFulfillment(null);
      setFulfillmentForm({ district_id: '', khoroo_id: '', address: '', detail_address: '' });
      toast.success('Хүргэлтээр солигдлоо!');
    } catch (err: any) {
      toast.error(err.message || 'Алдаа гарлаа');
    } finally {
      setFulfillmentSubmitting(false);
    }
  };

  const openFulfillmentForm = (orderNumber: string) => {
    // Pre-fill from saved addresses if available
    setChangingFulfillment(orderNumber);
    setFulfillmentForm({ district_id: '', khoroo_id: '', address: '', detail_address: '' });
    // Load addresses if not loaded
    if (authToken && addresses.length === 0) {
      fetchAddresses(authToken).then(setAddresses).catch(() => {});
    }
  };

  const getOrderStatusInfo = (status: string) => {
    const statusMap: Record<string, { text: string; color: string; bg: string; icon: typeof Clock }> = {
      pending: { text: 'Хүлээгдэж байна', color: 'text-yellow-600', bg: 'bg-yellow-50', icon: Clock },
      confirmed: { text: 'Баталгаажсан', color: 'text-blue-600', bg: 'bg-blue-50', icon: CheckCircle },
      shipping: { text: 'Хүргэлтэнд гарсан', color: 'text-purple-600', bg: 'bg-purple-50', icon: Truck },
      delivering: { text: 'Хүргэлтэнд гарсан', color: 'text-purple-600', bg: 'bg-purple-50', icon: Truck },
      partially_delivered: { text: 'Хэсэгчлэн хүргэсэн', color: 'text-amber-600', bg: 'bg-amber-50', icon: Truck },
      cargo_shipping: { text: 'Ачаа Солонгосоос явсан', color: 'text-orange-600', bg: 'bg-orange-50', icon: Truck },
      cargo_arrived: { text: 'Ачаа ирсэн', color: 'text-teal-600', bg: 'bg-teal-50', icon: CheckCircle },
      ready_pickup: { text: 'Авахад бэлэн', color: 'text-indigo-600', bg: 'bg-indigo-50', icon: CheckCircle },
      delivered: { text: 'Хүргэгдсэн', color: 'text-green-600', bg: 'bg-green-50', icon: CheckCircle },
      picked_up: { text: 'Авсан', color: 'text-green-600', bg: 'bg-green-50', icon: CheckCircle },
      completed: { text: 'Дууссан', color: 'text-green-600', bg: 'bg-green-50', icon: CheckCircle },
      cancelled: { text: 'Цуцлагдсан', color: 'text-red-600', bg: 'bg-red-50', icon: XCircle },
    };
    return statusMap[status] || { text: status, color: 'text-gray-600', bg: 'bg-gray-50', icon: Clock };
  };

  return (
    <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div className="flex items-center justify-between mb-8">
        <h1 className="text-3xl font-bold text-gray-900">Миний хэсэг</h1>
        <button
          onClick={logout}
          className="flex items-center gap-2 text-red-600 hover:text-red-700 font-medium"
        >
          <LogOut className="w-5 h-5" />
          Гарах
        </button>
      </div>

      {/* Tabs */}
      <div className="border-b mb-6">
        <div className="flex gap-6">
          <button
            onClick={() => setActiveTab('orders')}
            className={`pb-3 px-2 font-medium transition-colors border-b-2 ${
              activeTab === 'orders'
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-gray-600 hover:text-gray-900'
            }`}
          >
            Миний захиалга
            {orders.length > 0 && (
              <span className="ml-2 bg-blue-100 text-blue-600 text-xs font-bold px-2 py-0.5 rounded-full">
                {orders.length}
              </span>
            )}
          </button>
          <button
            onClick={() => setActiveTab('profile')}
            className={`pb-3 px-2 font-medium transition-colors border-b-2 ${
              activeTab === 'profile'
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-gray-600 hover:text-gray-900'
            }`}
          >
            Хувийн мэдээлэл
          </button>
          <button
            onClick={() => setActiveTab('addresses')}
            className={`pb-3 px-2 font-medium transition-colors border-b-2 ${
              activeTab === 'addresses'
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-gray-600 hover:text-gray-900'
            }`}
          >
            Хүргэлтийн хаяг
          </button>
        </div>
      </div>

      {/* Profile Tab */}
      {activeTab === 'profile' && (
        <>
        <div className="bg-white rounded-lg shadow-sm p-6">
          <div className="flex items-center gap-2 mb-6">
            <User className="w-5 h-5 text-blue-600" />
            <h2 className="text-xl font-bold text-gray-900">Хувийн мэдээлэл</h2>
          </div>

          <div className="space-y-1">
            {/* Name row */}
            {isEditingProfile ? (
              <div className="py-2 space-y-3">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Нэр</label>
                  <input
                    type="text"
                    value={profileForm.name}
                    onChange={(e) => setProfileForm({ ...profileForm, name: e.target.value })}
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>
                <div className="flex gap-3">
                  <button onClick={() => setIsEditingProfile(false)} className="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg font-medium hover:bg-gray-300 transition-colors">Болих</button>
                  <button onClick={handleProfileSave} className="flex-1 bg-blue-600 text-white py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors">Хадгалах</button>
                </div>
              </div>
            ) : (
              <div className="flex items-center justify-between py-2 border-b border-gray-100">
                <span className="text-gray-600 text-sm w-20">Нэр</span>
                <span className="font-medium text-gray-900 flex-1 text-right mr-3">{user?.name || '—'}</span>
                <button onClick={handleProfileEdit} className="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center gap-1">
                  <Edit2 className="w-3.5 h-3.5" /> Засах
                </button>
              </div>
            )}

            {/* Phone row */}
            {phoneEditStep === 'idle' && (
              <div className="flex items-center justify-between py-2 border-b border-gray-100">
                <span className="text-gray-600 text-sm w-20">Утас</span>
                <span className="font-medium text-gray-900 flex-1 text-right mr-3">{user?.phone || '—'}</span>
                <button onClick={() => { setPhoneEditStep('input'); setNewPhone(''); }} className="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center gap-1">
                  <Edit2 className="w-3.5 h-3.5" /> Засах
                </button>
              </div>
            )}
            {phoneEditStep === 'input' && (
              <div className="py-3 border-b border-gray-100 space-y-3">
                <label className="block text-sm font-medium text-gray-700">Шинэ утасны дугаар</label>
                <input
                  type="tel" value={newPhone} onChange={(e) => setNewPhone(e.target.value)}
                  placeholder="99112233" maxLength={8} autoFocus
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-center tracking-widest"
                />
                <div className="flex gap-3">
                  <button onClick={() => setPhoneEditStep('idle')} className="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg font-medium hover:bg-gray-300 transition-colors text-sm">Болих</button>
                  {authUser?.email ? (
                    // Email-registered: save directly, no OTP needed
                    <button onClick={() => handleUpdatePhone()} disabled={phoneEditLoading || newPhone.replace(/\D/g, '').length !== 8} className="flex-1 bg-blue-600 text-white py-2 rounded-lg font-medium hover:bg-blue-700 disabled:opacity-50 transition-colors text-sm flex items-center justify-center gap-2">
                      {phoneEditLoading ? <Loader2 className="w-4 h-4 animate-spin" /> : 'Хадгалах'}
                    </button>
                  ) : (
                    // Phone-registered: must verify new number with OTP
                    <button onClick={handleSendPhoneOtp} disabled={phoneEditLoading || newPhone.replace(/\D/g, '').length !== 8} className="flex-1 bg-blue-600 text-white py-2 rounded-lg font-medium hover:bg-blue-700 disabled:opacity-50 transition-colors text-sm flex items-center justify-center gap-2">
                      {phoneEditLoading ? <Loader2 className="w-4 h-4 animate-spin" /> : 'Код илгээх'}
                    </button>
                  )}
                </div>
              </div>
            )}
            {phoneEditStep === 'otp' && (
              <div className="py-3 border-b border-gray-100 space-y-3">
                <p className="text-sm text-gray-600">{newPhone.replace(/\D/g, '')} дугаарт илгээсэн 4 оронтой код</p>
                <div className="flex justify-center gap-3">
                  {phoneOtp.map((digit, i) => (
                    <input key={i} ref={(el) => { phoneOtpRefs.current[i] = el; }}
                      type="text" inputMode="numeric" value={digit} maxLength={1}
                      onChange={(e) => handleOtpBoxChange(i, e.target.value, phoneOtp, setPhoneOtp, phoneOtpRefs)}
                      onKeyDown={(e) => handleOtpBoxKeyDown(i, e, phoneOtp, phoneOtpRefs)}
                      className="w-12 h-12 text-center text-xl font-bold border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none"
                    />
                  ))}
                </div>
                <div className="flex gap-3">
                  <button onClick={() => setPhoneEditStep('idle')} className="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg font-medium hover:bg-gray-300 transition-colors text-sm">Болих</button>
                  <button onClick={() => handleUpdatePhone(phoneOtp.join(''))} disabled={phoneEditLoading || phoneOtp.join('').length !== 4} className="flex-1 bg-blue-600 text-white py-2 rounded-lg font-medium hover:bg-blue-700 disabled:opacity-50 transition-colors text-sm flex items-center justify-center gap-2">
                    {phoneEditLoading ? <Loader2 className="w-4 h-4 animate-spin" /> : 'Хадгалах'}
                  </button>
                </div>
              </div>
            )}

            {/* Email row */}
            {emailEditStep === 'idle' && (
              <div className="flex items-center justify-between py-2">
                <span className="text-gray-600 text-sm w-20">Имэйл</span>
                <span className="font-medium text-gray-900 flex-1 text-right mr-3 text-sm">{authUser?.email || '—'}</span>
                <button onClick={() => { setEmailEditStep('input'); setNewEmail(authUser?.email || ''); }} className="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center gap-1">
                  <Edit2 className="w-3.5 h-3.5" /> {authUser?.email ? 'Засах' : 'Нэмэх'}
                </button>
              </div>
            )}
            {emailEditStep === 'input' && (
              <div className="py-3 space-y-3">
                <label className="block text-sm font-medium text-gray-700">{authUser?.email ? 'Шинэ имэйл хаяг' : 'Имэйл хаяг нэмэх'}</label>
                <input
                  type="email" value={newEmail} onChange={(e) => setNewEmail(e.target.value)}
                  placeholder="example@email.com" autoFocus
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                <div className="flex gap-3">
                  <button onClick={() => setEmailEditStep('idle')} className="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg font-medium hover:bg-gray-300 transition-colors text-sm">Болих</button>
                  <button onClick={handleSendEmailOtp} disabled={emailEditLoading || !newEmail.trim()} className="flex-1 bg-blue-600 text-white py-2 rounded-lg font-medium hover:bg-blue-700 disabled:opacity-50 transition-colors text-sm flex items-center justify-center gap-2">
                    {emailEditLoading ? <Loader2 className="w-4 h-4 animate-spin" /> : 'Код илгээх'}
                  </button>
                </div>
              </div>
            )}
            {emailEditStep === 'otp' && (
              <div className="py-3 space-y-3">
                <p className="text-sm text-gray-600">{newEmail} хаягт илгээсэн 4 оронтой код</p>
                <div className="flex justify-center gap-3">
                  {emailOtp.map((digit, i) => (
                    <input key={i} ref={(el) => { emailOtpRefs.current[i] = el; }}
                      type="text" inputMode="numeric" value={digit} maxLength={1}
                      onChange={(e) => handleOtpBoxChange(i, e.target.value, emailOtp, setEmailOtp, emailOtpRefs)}
                      onKeyDown={(e) => handleOtpBoxKeyDown(i, e, emailOtp, emailOtpRefs)}
                      className="w-12 h-12 text-center text-xl font-bold border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none"
                    />
                  ))}
                </div>
                <div className="flex gap-3">
                  <button onClick={() => setEmailEditStep('idle')} className="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg font-medium hover:bg-gray-300 transition-colors text-sm">Болих</button>
                  <button onClick={handleUpdateEmail} disabled={emailEditLoading || emailOtp.join('').length !== 4} className="flex-1 bg-blue-600 text-white py-2 rounded-lg font-medium hover:bg-blue-700 disabled:opacity-50 transition-colors text-sm flex items-center justify-center gap-2">
                    {emailEditLoading ? <Loader2 className="w-4 h-4 animate-spin" /> : 'Хадгалах'}
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Social Accounts */}
        <div className="bg-white rounded-lg shadow-sm p-6 mt-4">
          <div className="flex items-center gap-2 mb-4">
            <svg className="w-5 h-5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" /><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" /></svg>
            <h2 className="text-xl font-bold text-gray-900">Холбогдсон бүртгэлүүд</h2>
          </div>
          <div className="space-y-3">
            <div className="flex items-center justify-between py-3 px-4 bg-gray-50 rounded-lg">
              <div className="flex items-center gap-3">
                <svg className="w-5 h-5" viewBox="0 0 24 24">
                  <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/>
                  <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                  <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                  <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                </svg>
                <span className="font-medium text-gray-900">Google</span>
              </div>
              {authUser?.google_connected ? (
                <span className="inline-flex items-center gap-1 px-3 py-1 bg-green-100 text-green-700 text-sm font-medium rounded-full">
                  <CheckCircle className="w-3.5 h-3.5" />
                  Холбогдсон
                </span>
              ) : (
                <span className="inline-flex items-center px-3 py-1 bg-gray-200 text-gray-500 text-sm font-medium rounded-full">
                  Холбогдоогүй
                </span>
              )}
            </div>
          </div>
        </div>
        </>
      )}

      {/* Addresses Tab */}
      {activeTab === 'addresses' && (
        <div className="bg-white rounded-lg shadow-sm p-6">
          <div className="flex items-center justify-between mb-6">
            <div className="flex items-center gap-2">
              <MapPin className="w-5 h-5 text-blue-600" />
              <h2 className="text-xl font-bold text-gray-900">Хүргэлтийн хаягууд</h2>
            </div>
            <button
              onClick={() => setShowAddAddress(!showAddAddress)}
              className="flex items-center gap-2 text-blue-600 hover:text-blue-700 font-medium"
            >
              <Plus className="w-5 h-5" />
              Хаяг нэмэх
            </button>
          </div>

          {showAddAddress && (
            <form onSubmit={handleAddAddress} className="mb-6 p-4 bg-gray-50 rounded-lg space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Дүүрэг *</label>
                <select
                  required
                  value={selectedDistrict}
                  onChange={(e) => {
                    setSelectedDistrict(e.target.value);
                    setNewAddress({ ...newAddress, district_id: e.target.value, khoroo_id: '' });
                  }}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">Дүүрэг сонгох</option>
                  {districts.map((district) => (
                    <option key={district.id} value={district.id}>
                      {district.name}
                    </option>
                  ))}
                </select>
              </div>

              {selectedDistrict && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Хороо *</label>
                  <select
                    required
                    value={newAddress.khoroo_id}
                    onChange={(e) => setNewAddress({ ...newAddress, khoroo_id: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="">Хороо сонгох</option>
                    {khoroos.map((khoroo) => (
                      <option key={khoroo.id} value={String(khoroo.id)}>
                        {khoroo.label}
                      </option>
                    ))}
                  </select>
                </div>
              )}

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Хаяг *</label>
                <input
                  type="text"
                  required
                  value={newAddress.address}
                  onChange={(e) => setNewAddress({ ...newAddress, address: e.target.value })}
                  placeholder="Гудамж, байр, тоот"
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Нэмэлт хаяг</label>
                <input
                  type="text"
                  value={newAddress.detail_address}
                  onChange={(e) => setNewAddress({ ...newAddress, detail_address: e.target.value })}
                  placeholder="Орц, давхар, тоот"
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div className="flex gap-3">
                <button
                  type="button"
                  onClick={() => {
                    setShowAddAddress(false);
                    setSelectedDistrict('');
                  }}
                  className="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg font-medium hover:bg-gray-300 transition-colors"
                >
                  Болих
                </button>
                <button
                  type="submit"
                  className="flex-1 bg-blue-600 text-white py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors"
                >
                  Хадгалах
                </button>
              </div>
            </form>
          )}

          {addressLoading ? (
            <p className="text-gray-500 text-center py-8">Уншиж байна...</p>
          ) : addresses.length === 0 ? (
            <p className="text-gray-500 text-center py-8">Хадгалсан хаяг байхгүй байна</p>
          ) : (
            <div className="space-y-3">
              {addresses.map((addr) => (
                <div key={addr.id} className="border border-gray-200 rounded-lg p-4">
                  <div className="flex justify-between items-start">
                    <div>
                      <div className="flex items-center gap-2 mb-1">
                        <Home className="w-4 h-4 text-gray-500" />
                        <p className="font-medium text-gray-900">
                          {addr.district_name} {addr.khoroo_name || (addr.khoroo_number ? `${addr.khoroo_number}-р хороо` : '')}
                        </p>
                        {addr.is_default === 1 && (
                          <span className="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Үндсэн</span>
                        )}
                      </div>
                      <p className="text-sm text-gray-600">
                        {addr.address}
                        {addr.detail_address && `, ${addr.detail_address}`}
                      </p>
                    </div>
                    <button
                      onClick={() => handleDeleteAddress(addr.id)}
                      className="text-red-500 hover:text-red-600"
                    >
                      <Trash2 className="w-5 h-5" />
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Orders Tab */}
      {activeTab === 'orders' && (
        <div className="space-y-4">
          {ordersLoading ? (
            <p className="text-gray-500 text-center py-8">Уншиж байна...</p>
          ) : orders.length === 0 ? (
            <div className="bg-white rounded-lg shadow-sm p-12 text-center">
              <Package className="w-16 h-16 text-gray-300 mx-auto mb-4" />
              <p className="text-gray-500 text-lg">Захиалга байхгүй байна</p>
            </div>
          ) : (
            orders.map((order) => {
              const statusInfo = getOrderStatusInfo(order.status);
              const StatusIcon = statusInfo.icon;
              
              return (
                <div key={order.id} className="bg-white rounded-lg shadow-sm p-6">
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between mb-4 gap-3">
                    <div>
                      <h3 className="text-lg font-bold text-gray-900">#{order.order_number}</h3>
                      <p className="text-sm text-gray-500">
                        {new Date(order.created_at).toLocaleDateString('mn-MN', {
                          year: 'numeric',
                          month: 'long',
                          day: 'numeric',
                          hour: '2-digit',
                          minute: '2-digit'
                        })}
                      </p>
                      <div className="flex flex-wrap gap-2 mt-2">
                        <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium ${
                          order.fulfillment === 'pickup' ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700'
                        }`}>
                          {order.fulfillment === 'pickup' ? <><ShoppingBag className="w-3 h-3" /> Очиж авах</> : <><Truck className="w-3 h-3" /> Хүргэлт</>}
                        </span>
                        <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium ${
                          order.payment_method === 'transfer' ? 'bg-purple-50 text-purple-700'
                          : order.payment_method === 'bonum' ? 'bg-indigo-50 text-indigo-700'
                          : 'bg-cyan-50 text-cyan-700'
                        }`}>
                          {order.payment_method === 'transfer' ? <><Banknote className="w-3 h-3" /> Шилжүүлэг</>
                          : order.payment_method === 'bonum' ? <><CreditCard className="w-3 h-3" /> Bonum</>
                          : <><CreditCard className="w-3 h-3" /> QPay</>}
                        </span>
                        {order.payment_status === 'paid' && (
                          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700">
                            <CheckCircle className="w-3 h-3" /> Төлсөн
                          </span>
                        )}
                      </div>
                    </div>
                    <div className={`${statusInfo.bg} ${statusInfo.color} px-4 py-2 rounded-lg font-medium text-sm flex items-center gap-2`}>
                      <StatusIcon className="w-4 h-4" />
                      {statusInfo.text}
                    </div>
                  </div>

                  <div className="border-t border-b py-4 mb-4">
                    <div className="space-y-3">
                      {order.items.map((item, idx) => (
                        <div key={idx} className="flex gap-4">
                          <img
                            src={fixImageUrl(item.image) || 'https://placehold.co/64x64?text=No+Image'}
                            alt={item.product_name}
                            className="w-16 h-16 object-cover rounded"
                          />
                          <div className="flex-1">
                            <p className="font-medium text-gray-900 text-sm">{item.product_name_mn}</p>
                            {item.variant_label && (
                              <p className="text-xs text-blue-600 font-medium">{item.variant_label}</p>
                            )}
                            <p className="text-xs text-gray-500">{item.quantity} ширхэг × {item.product_price.toLocaleString()}₮</p>
                            {item.cargo_fee > 0 && (
                              <p className="flex items-center gap-1 mt-0.5">
                                {item.cargo_fee_paid ? (
                                  <><span className="text-xs text-gray-500">Ачаа: {item.cargo_fee.toLocaleString()}₮</span>
                                  <span className="px-1 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Төлсөн</span></>
                                ) : (
                                  <span className="px-1 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">Ачаа төлөөгүй</span>
                                )}
                              </p>
                            )}
                          </div>
                          <div className="text-right">
                            <p className="font-medium text-gray-900">
                              {(item.product_price * item.quantity).toLocaleString()}₮
                            </p>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>

                  <div className="flex justify-between items-center">
                    <div className="text-sm text-gray-600">
                      {order.fulfillment === 'pickup' ? (
                        <>
                          <p className="font-medium flex items-center gap-1"><ShoppingBag className="w-4 h-4" /> Очиж авах</p>
                          <p className="text-gray-500">Захиалга бэлэн болмогц мэдэгдэнэ</p>
                        </>
                      ) : (
                        <>
                          <p className="font-medium">Хүргэх хаяг:</p>
                          <p>{order.district_name}{order.khoroo_name ? `, ${order.khoroo_name}` : (order.khoroo_number ? `, ${order.khoroo_number}-р хороо` : '')}</p>
                          <p>{order.address}</p>
                        </>
                      )}
                    </div>
                    <div className="text-right">
                      {order.delivery_fee > 0 ? (
                        <p className="text-xs text-gray-600 mb-1">
                          Хүргэлт: <span className="font-medium text-gray-900">{order.delivery_fee.toLocaleString()}₮</span>
                        </p>
                      ) : (order.fulfillment === 'delivery' && !deliveryFeeEnabled) && (
                        <p className="text-xs text-gray-600 mb-1">
                          Хүргэлт: <span className="font-medium text-gray-900">Хүргэлтийн үед бодогдоно</span>
                        </p>
                      )}
                      <p className="text-sm text-gray-600">Нийт дүн</p>
                      <p className="text-2xl font-bold text-gray-900">{order.total.toLocaleString()}₮</p>
                      {order.cargo_fee > 0 && (
                        <p className="mt-1">
                          {order.cargo_fee_paid ? (
                            <><span className="text-xs text-gray-500">Ачаа: {order.cargo_fee.toLocaleString()}₮ </span>
                            <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Төлсөн</span></>
                          ) : ['cargo_arrived','ready_pickup','delivering','partially_delivered'].includes(order.status) ? (
                            <button
                              onClick={() => { setCargoPayOrder(order); setPayMethod(null); setCargoInvoice(null); setCargoPaid(false); setPayError(''); }}
                              className="mt-1 px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold rounded-lg transition-colors"
                            >
                              Ачаа ({order.cargo_fee.toLocaleString()}₮) — Одоо төлөх
                            </button>
                          ) : (
                            <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Ачаа төлөөгүй</span>
                          )}
                        </p>
                      )}
                    </div>
                  </div>

                  {/* Track Order Button + Fulfillment Change */}
                  <div className="mt-4 pt-4 border-t space-y-3">
                    {/* Pickup → Delivery change button */}
                    {order.fulfillment === 'pickup' && ['pending', 'confirmed'].includes(order.status) && changingFulfillment !== order.order_number && (
                      <button
                        onClick={() => openFulfillmentForm(order.order_number)}
                        className="w-full sm:w-auto px-6 py-2 bg-orange-500 text-white font-medium rounded-lg hover:bg-orange-600 transition-colors flex items-center justify-center gap-2"
                      >
                        <Truck className="w-4 h-4" />
                        Хүргэлтээр авах
                      </button>
                    )}

                    {/* Inline address form */}
                    {changingFulfillment === order.order_number && (
                      <div className="bg-orange-50 border border-orange-200 rounded-lg p-4 space-y-3">
                        <p className="text-sm font-medium text-orange-800">Хүргэлтийн хаяг оруулна уу</p>
                        
                        {/* Quick select from saved addresses */}
                        {addresses.length > 0 && (
                          <div>
                            <p className="text-xs text-gray-500 mb-1">Хадгалсан хаягаас сонгох:</p>
                            <div className="space-y-1">
                              {addresses.map(addr => (
                                <button
                                  key={addr.id}
                                  type="button"
                                  onClick={() => setFulfillmentForm({
                                    district_id: String(addr.district_id),
                                    khoroo_id: String(addr.khoroo_id),
                                    address: addr.address,
                                    detail_address: addr.detail_address || '',
                                  })}
                                  className="w-full text-left px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm hover:border-orange-400 transition-colors"
                                >
                                  <span className="font-medium">{addr.district_name}</span>
                                  {addr.khoroo_name && <span>, {addr.khoroo_name}</span>}
                                  <span className="text-gray-500"> — {addr.address}</span>
                                </button>
                              ))}
                            </div>
                            <div className="border-t border-orange-200 my-2"></div>
                          </div>
                        )}

                        <select
                          value={fulfillmentForm.district_id}
                          onChange={(e) => setFulfillmentForm({ ...fulfillmentForm, district_id: e.target.value, khoroo_id: '' })}
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 outline-none"
                        >
                          <option value="">Дүүрэг сонгох</option>
                          {districts.map(d => (
                            <option key={d.id} value={d.id}>{d.name}</option>
                          ))}
                        </select>

                        {fulfillmentForm.district_id && (
                          <select
                            value={fulfillmentForm.khoroo_id}
                            onChange={(e) => setFulfillmentForm({ ...fulfillmentForm, khoroo_id: e.target.value })}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 outline-none"
                          >
                            <option value="">Хороо сонгох</option>
                            {fulfillmentKhoroos.map(k => (
                              <option key={k.id} value={String(k.id)}>{k.label}</option>
                            ))}
                          </select>
                        )}

                        <input
                          type="text"
                          value={fulfillmentForm.address}
                          onChange={(e) => setFulfillmentForm({ ...fulfillmentForm, address: e.target.value })}
                          placeholder="Гудамж, байр, тоот"
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 outline-none"
                        />
                        <input
                          type="text"
                          value={fulfillmentForm.detail_address}
                          onChange={(e) => setFulfillmentForm({ ...fulfillmentForm, detail_address: e.target.value })}
                          placeholder="Орц, давхар, тоот (заавал биш)"
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 outline-none"
                        />

                        <div className="flex gap-2">
                          <button
                            onClick={() => { setChangingFulfillment(null); setFulfillmentForm({ district_id: '', khoroo_id: '', address: '', detail_address: '' }); }}
                            className="flex-1 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300 transition-colors"
                          >
                            Болих
                          </button>
                          <button
                            onClick={() => handleFulfillmentChange(order.order_number)}
                            disabled={fulfillmentSubmitting}
                            className="flex-1 py-2 bg-orange-600 text-white rounded-lg text-sm font-medium hover:bg-orange-700 transition-colors disabled:opacity-50"
                          >
                            {fulfillmentSubmitting ? 'Илгээж байна...' : 'Хүргэлтээр солих'}
                          </button>
                        </div>
                      </div>
                    )}

                    <button
                      onClick={() => navigate(`/order-tracking/${order.order_number}`)}
                      className="w-full sm:w-auto px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center gap-2"
                    >
                      <Search className="w-4 h-4" />
                      Захиалга мөшгих
                    </button>
                  </div>
                </div>
              );
            })
          )}
        </div>
      )}

      {/* Cargo Payment Modal */}
      {cargoPayOrder && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60">
          <div className="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
            <div className="flex items-center justify-between px-6 py-4 border-b">
              <h3 className="text-lg font-bold text-gray-900">Ачааны төлбөр төлөх</h3>
              <button onClick={closeCargoModal} className="p-2 hover:bg-gray-100 rounded-full transition-colors">
                <X className="w-5 h-5 text-gray-500" />
              </button>
            </div>

            <div className="p-6 space-y-5">
              {cargoPaid ? (
                <div className="text-center py-6 space-y-3">
                  <CheckCircle className="w-14 h-14 text-green-500 mx-auto" />
                  <p className="text-xl font-bold text-green-700">Төлбөр амжилттай!</p>
                  <p className="text-gray-500 text-sm">Ачааны төлбөрийг амжилттай төллөө.</p>
                  <button onClick={closeCargoModal} className="mt-4 px-6 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">Хаах</button>
                </div>
              ) : !payMethod ? (
                <>
                  <div className="bg-amber-50 border border-amber-200 rounded-xl p-4">
                    <p className="text-sm text-amber-800 font-medium">Захиалга #{cargoPayOrder.order_number}</p>
                    <p className="text-2xl font-bold text-amber-900 mt-1">{cargoPayOrder.cargo_fee.toLocaleString()}₮</p>
                    <p className="text-xs text-amber-700 mt-0.5">Ачааны хураамж</p>
                  </div>
                  <p className="text-sm font-medium text-gray-700">Төлбөрийн хэрэгсэл сонгоно уу:</p>
                  <div className="space-y-2">
                    {qpayEnabled && (
                      <button onClick={() => setPayMethod('qpay')} className="w-full flex items-center gap-3 px-4 py-3 border-2 border-gray-200 hover:border-blue-500 rounded-xl transition-colors">
                        <QrCode className="w-5 h-5 text-blue-600" />
                        <span className="font-medium">QPay</span>
                      </button>
                    )}
                    {bonumEnabled && (
                      <button onClick={() => setPayMethod('bonum')} className="w-full flex items-center gap-3 px-4 py-3 border-2 border-gray-200 hover:border-purple-500 rounded-xl transition-colors">
                        <CreditCard className="w-5 h-5 text-purple-600" />
                        <span className="font-medium">Bonum</span>
                      </button>
                    )}
                    {storepayEnabled && (
                      <button onClick={() => setPayMethod('storepay')} className="w-full flex items-center gap-3 px-4 py-3 border-2 border-gray-200 hover:border-green-500 rounded-xl transition-colors">
                        <Banknote className="w-5 h-5 text-green-600" />
                        <span className="font-medium">StorePay</span>
                      </button>
                    )}
                  </div>
                </>
              ) : cargoInvoice ? (
                <div className="space-y-4">
                  {payMethod === 'qpay' && cargoInvoice.qr_image && (
                    <>
                      <p className="text-sm text-center text-gray-600">QPay апп-аар дараах QR кодыг уншуулна уу</p>
                      <img src={`data:image/png;base64,${cargoInvoice.qr_image}`} alt="QPay QR" className="mx-auto w-52 h-52" />
                      {cargoInvoice.urls && cargoInvoice.urls.length > 0 && (
                        <div className="grid grid-cols-3 gap-2">
                          {cargoInvoice.urls.slice(0, 6).map((u, i) => (
                            <a key={i} href={u.link} className="flex flex-col items-center gap-1 p-2 border rounded-lg hover:bg-gray-50 text-xs text-center text-gray-700">
                              {u.logo && <img src={u.logo} alt={u.name} className="w-7 h-7 object-contain" />}
                              {u.name}
                            </a>
                          ))}
                        </div>
                      )}
                    </>
                  )}
                  {payMethod === 'bonum' && cargoInvoice.follow_up_link && (
                    <>
                      <p className="text-sm text-center text-gray-600">Bonum апп-д шилжихийн тулд товшино уу</p>
                      <a href={cargoInvoice.follow_up_link} className="block w-full text-center py-3 bg-purple-600 text-white font-semibold rounded-xl hover:bg-purple-700">
                        Bonum-ээр төлөх
                      </a>
                    </>
                  )}
                  <div className="flex items-center justify-center gap-2 text-sm text-gray-500">
                    <Loader2 className="w-4 h-4 animate-spin" />
                    Төлбөр хүлээж байна...
                  </div>
                </div>
              ) : (
                <div className="space-y-4">
                  <button onClick={() => { setPayMethod(null); setPayError(''); }} className="text-sm text-blue-600 hover:underline flex items-center gap-1">
                    ← Буцах
                  </button>
                  <div className="bg-amber-50 border border-amber-200 rounded-xl p-4">
                    <p className="text-sm text-amber-800 font-medium">{payMethod === 'qpay' ? 'QPay' : payMethod === 'bonum' ? 'Bonum' : 'StorePay'} — {cargoPayOrder.cargo_fee.toLocaleString()}₮</p>
                  </div>
                  {payMethod === 'storepay' && (
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Утасны дугаар</label>
                      <input
                        type="tel"
                        value={mobileInput}
                        onChange={e => setMobileInput(e.target.value)}
                        placeholder="8 оронтой дугаар"
                        maxLength={8}
                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                      />
                    </div>
                  )}
                  {payError && <p className="text-sm text-red-600 bg-red-50 rounded-lg px-3 py-2">{payError}</p>}
                  <button
                    onClick={handleCreateCargoInvoice}
                    disabled={payLoading}
                    className="w-full py-3 bg-blue-600 text-white font-semibold rounded-xl hover:bg-blue-700 disabled:opacity-50 flex items-center justify-center gap-2 transition-colors"
                  >
                    {payLoading ? <><Loader2 className="w-4 h-4 animate-spin" /> Үүсгэж байна...</> : 'Нэхэмжлэл үүсгэх'}
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};