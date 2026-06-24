import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router';
import { useApp } from '../context/AppContext';
import { MapPin, CheckCircle, Home, Truck, Store, CreditCard, Building2, Copy } from 'lucide-react';
import { toast } from 'sonner';
import { fetchDistricts, createOrder, cancelOrder, updateOrderPaymentMethod, fetchAddresses, fetchSettings, ApiAddress, createQPayInvoice, checkQPayPayment, QPayInvoiceResponse, createBonumInvoice, checkBonumPayment, createStorepayInvoice, checkStorepayPayment } from '../services/api';
import { useApi } from '../hooks/useApi';
import { District } from '../types';

export const CheckoutPage = () => {
  const navigate = useNavigate();
  const { cart, cartTotal, user, authToken, clearCart, isAuthenticated } = useApp();
  const [step, setStep] = useState<'shipping' | 'payment' | 'complete'>('shipping');
  const [orderNumber, setOrderNumber] = useState('');
  const [shippingInfo, setShippingInfo] = useState({
    name: user?.name || '',
    phone: user?.phone || '',
    district: '',
    khoroo: '',
    address: '',
    detailAddress: '',
  });

  const [selectedDistrict, setSelectedDistrict] = useState('');
  const [qrCodeUrl, setQrCodeUrl] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [qpayInvoiceId, setQpayInvoiceId] = useState('');
  const [qpayUrls, setQpayUrls] = useState<QPayInvoiceResponse['urls']>([]);
  const [paymentChecking, setPaymentChecking] = useState(false);
  const [qpayError, setQpayError] = useState('');
  const [bonumInvoiceId, setBonumInvoiceId] = useState('');
  const [bonumFollowUpLink, setBonumFollowUpLink] = useState('');
  const [storepayInvoiceId, setStorepayInvoiceId] = useState('');
  const [storepayMobile, setStorepayMobile] = useState('');
  const [storepayError, setStorepayError] = useState('');
  const [saveAddress, setSaveAddress] = useState(false);
  const [savedAddresses, setSavedAddresses] = useState<ApiAddress[]>([]);
  const [selectedAddressId, setSelectedAddressId] = useState<number | null>(null);
  const [useNewAddress, setUseNewAddress] = useState(false);
  const [fulfillment, setFulfillment] = useState<'delivery' | 'pickup'>('pickup');
  const [paymentMethod, setPaymentMethod] = useState<'qpay' | 'transfer' | 'bonum' | 'storepay'>('transfer');
  const [qpaySecondsLeft, setQpaySecondsLeft] = useState<number | null>(null);
  const [switchingMethod, setSwitchingMethod] = useState(false);

  const { data: apiDistricts } = useApi(() => fetchDistricts(), []);
  const districts: District[] = (apiDistricts || []).map(d => ({
    id: String(d.id),
    name: d.name_mn || d.name,
    khoroos: d.khoroos.map(k => ({ id: k.id, number: k.number, label: k.name || `${k.number}-р хороо` })),
  }));

  // Load saved addresses
  useEffect(() => {
    if (authToken) {
      fetchAddresses(authToken)
        .then(addrs => {
          setSavedAddresses(addrs);
          if (addrs.length > 0) {
            const defaultAddr = addrs.find(a => a.is_default) || addrs[0];
            setSelectedAddressId(defaultAddr.id);
          } else {
            setUseNewAddress(true);
          }
        })
        .catch(() => setUseNewAddress(true));
    } else {
      setUseNewAddress(true);
    }
  }, [authToken]);

  // Shipping fee and threshold from settings API
  const [settings, setSettings] = useState<{ delivery_fee_enabled: boolean; delivery_fee: number; free_delivery_threshold: number; cargo_rate_per_kg: number; bank_name: string; bank_account_number: string; bank_account_name: string; payment_qpay_enabled: boolean; payment_transfer_enabled: boolean; payment_bonum_enabled: boolean; payment_storepay_enabled: boolean }>({ delivery_fee_enabled: true, delivery_fee: 5000, free_delivery_threshold: 50000, cargo_rate_per_kg: 0, bank_name: '', bank_account_number: '', bank_account_name: '', payment_qpay_enabled: true, payment_transfer_enabled: true, payment_bonum_enabled: false, payment_storepay_enabled: false });
  useEffect(() => {
    fetchSettings().then(s => {
      const toBool = (v: unknown) => v === true || v === '1' || v === 1;
      const qpayEnabled    = toBool(s.payment_qpay_enabled);
      const transferEnabled = toBool(s.payment_transfer_enabled);
      const bonumEnabled   = toBool(s.payment_bonum_enabled);
      const storepayEnabled = toBool(s.payment_storepay_enabled);
      setSettings({
        delivery_fee_enabled: s.delivery_fee_enabled === undefined ? true : toBool(s.delivery_fee_enabled),
        delivery_fee: typeof s.delivery_fee === 'number' ? s.delivery_fee : 5000,
        free_delivery_threshold: typeof s.free_delivery_threshold === 'number' ? s.free_delivery_threshold : 50000,
        cargo_rate_per_kg: typeof s.cargo_rate_per_kg === 'number' ? s.cargo_rate_per_kg : 0,
        bank_name: typeof s.bank_name === 'string' ? s.bank_name : '',
        bank_account_number: typeof s.bank_account_number === 'string' ? s.bank_account_number : '',
        bank_account_name: typeof s.bank_account_name === 'string' ? s.bank_account_name : '',
        payment_qpay_enabled: qpayEnabled,
        payment_transfer_enabled: transferEnabled,
        payment_bonum_enabled: bonumEnabled,
        payment_storepay_enabled: storepayEnabled,
      });
      // Auto-select single enabled method
      const enabledMethods = [
        qpayEnabled    ? 'qpay'     : null,
        transferEnabled ? 'transfer' : null,
        bonumEnabled   ? 'bonum'    : null,
        storepayEnabled ? 'storepay' : null,
      ].filter(Boolean) as ('qpay' | 'transfer' | 'bonum' | 'storepay')[];
      if (enabledMethods.length === 1) setPaymentMethod(enabledMethods[0]);
      else if (!transferEnabled && enabledMethods.length > 0) setPaymentMethod(enabledMethods[0]);
    }).catch(() => {
      toast.error('Тохиргоо уншихад алдаа гарлаа');
    });
  }, []);
  const shippingFee = fulfillment === 'pickup' || !settings.delivery_fee_enabled ? 0 : (cartTotal >= settings.free_delivery_threshold ? 0 : settings.delivery_fee);

  const hasCargoItems = cart.some(item => item.product.type === 'preorder' && item.product.weightKg && settings.cargo_rate_per_kg > 0);

  const cargoFee = cart.reduce((sum, item) => {
    if (item.product.type === 'preorder' && item.product.weightKg && !item.product.hideCargoFee) {
      return sum + item.product.weightKg * settings.cargo_rate_per_kg * item.quantity;
    }
    return sum;
  }, 0);

  const total = cartTotal + cargoFee + shippingFee;
  const [confirmedTotal, setConfirmedTotal] = useState<number | null>(null);
  const displayTotal = confirmedTotal ?? total;

  // Cart fingerprint: detect if user changed cart since the pending order was created
  const cartFingerprint = cart.map(i => `${i.product.id}:${i.quantity}:${i.variantId || ''}`).sort().join('|');

  // Restore pending payment on mount (if user returns after navigating away or bank app redirect)
  const [restored, setRestored] = useState(false);
  useEffect(() => {
    const saved = sessionStorage.getItem('rw-pending-payment');
    if (saved && step === 'shipping' && !qpayInvoiceId) {
      try {
        const data = JSON.parse(saved);
        // If cart changed since the order was created, discard the old pending payment
        if (data.cartFingerprint && data.cartFingerprint !== cartFingerprint) {
          sessionStorage.removeItem('rw-pending-payment');
          return;
        }
        if (data.qpayInvoiceId) {
          setQpayInvoiceId(data.qpayInvoiceId);
          setOrderNumber(data.orderNumber || '');
          if (data.qrCodeUrl) setQrCodeUrl(data.qrCodeUrl);
          if (data.qpayUrls) setQpayUrls(data.qpayUrls);
          if (data.paymentMethod) setPaymentMethod(data.paymentMethod);
          if (data.confirmedTotal) setConfirmedTotal(data.confirmedTotal);
          setStep('payment');
          setRestored(true);
        } else if (data.bonumInvoiceId && data.paymentMethod === 'bonum') {
          setBonumInvoiceId(data.bonumInvoiceId);
          setBonumFollowUpLink(data.bonumFollowUpLink || '');
          setOrderNumber(data.orderNumber || '');
          setPaymentMethod('bonum');
          if (data.confirmedTotal) setConfirmedTotal(data.confirmedTotal);
          setStep('payment');
          setRestored(true);
        } else if (data.storepayInvoiceId && data.paymentMethod === 'storepay') {
          setStorepayInvoiceId(data.storepayInvoiceId);
          setStorepayMobile(data.storepayMobile || '');
          setOrderNumber(data.orderNumber || '');
          setPaymentMethod('storepay');
          if (data.confirmedTotal) setConfirmedTotal(data.confirmedTotal);
          setStep('payment');
          setRestored(true);
        } else if (data.orderNumber && data.paymentMethod === 'transfer') {
          setOrderNumber(data.orderNumber);
          setPaymentMethod('transfer');
          if (data.confirmedTotal) setConfirmedTotal(data.confirmedTotal);
          setStep('payment');
          setRestored(true);
        }
      } catch { /* ignore */ }
    }
  }, []);

  const hasPendingPayment = restored || !!sessionStorage.getItem('rw-pending-payment');

  useEffect(() => {
    if (cart.length === 0 && step !== 'complete' && step !== 'payment' && !hasPendingPayment) {
      navigate('/cart');
    }
  }, [cart.length, step, navigate, hasPendingPayment]);

  if (cart.length === 0 && step !== 'complete' && step !== 'payment' && !hasPendingPayment) {
    return null;
  }

  // Save pending payment to sessionStorage so it survives navigation or bank app redirect
  useEffect(() => {
    if (step === 'payment' && orderNumber) {
      sessionStorage.setItem('rw-pending-payment', JSON.stringify({ qpayInvoiceId, bonumInvoiceId, bonumFollowUpLink, storepayInvoiceId, storepayMobile, orderNumber, total, confirmedTotal: confirmedTotal ?? total, qrCodeUrl, qpayUrls, paymentMethod, cartFingerprint }));
    }
    if (step === 'complete') {
      sessionStorage.removeItem('rw-pending-payment');
    }
  }, [step, qpayInvoiceId, orderNumber, total, qrCodeUrl, qpayUrls, paymentMethod]);

  // Poll Bonum payment status when on payment step
  useEffect(() => {
    if (step !== 'payment' || paymentMethod !== 'bonum' || !bonumInvoiceId) return;

    const checkAndComplete = async () => {
      try {
        const result = await checkBonumPayment(bonumInvoiceId);
        if (result.paid) {
          clearCart();
          setStep('complete');
          sessionStorage.removeItem('rw-pending-payment');
          toast.success('Bonum төлбөр амжилттай төлөгдлөө!');
          return true;
        }
      } catch { /* silent retry */ }
      return false;
    };

    const interval = setInterval(checkAndComplete, 3000);

    const handleVisibility = () => {
      if (document.visibilityState === 'visible') checkAndComplete();
    };
    document.addEventListener('visibilitychange', handleVisibility);

    return () => {
      clearInterval(interval);
      document.removeEventListener('visibilitychange', handleVisibility);
    };
  }, [step, bonumInvoiceId, paymentMethod]);

  // Poll StorePay payment status when on payment step
  useEffect(() => {
    if (step !== 'payment' || paymentMethod !== 'storepay' || !storepayInvoiceId) return;

    const checkAndComplete = async () => {
      try {
        const result = await checkStorepayPayment(storepayInvoiceId);
        if (result.paid) {
          clearCart();
          setStep('complete');
          sessionStorage.removeItem('rw-pending-payment');
          toast.success('StorePay төлбөр амжилттай төлөгдлөө!');
          return true;
        }
      } catch { /* silent retry */ }
      return false;
    };

    const interval = setInterval(checkAndComplete, 4000);

    const handleVisibility = () => {
      if (document.visibilityState === 'visible') checkAndComplete();
    };
    document.addEventListener('visibilitychange', handleVisibility);

    return () => {
      clearInterval(interval);
      document.removeEventListener('visibilitychange', handleVisibility);
    };
  }, [step, storepayInvoiceId, paymentMethod]);

  // QPay QR expiry countdown (30 min)
  useEffect(() => {
    if (step !== 'payment' || !qpayInvoiceId) return;
    setQpaySecondsLeft(30 * 60);
    const tick = setInterval(() => {
      setQpaySecondsLeft(prev => (prev !== null && prev > 0 ? prev - 1 : 0));
    }, 1000);
    return () => clearInterval(tick);
  }, [step, qpayInvoiceId]);

  // Poll QPay payment status when on payment step
  useEffect(() => {
    if (step !== 'payment' || !qpayInvoiceId || paymentChecking) return;
    
    const interval = setInterval(async () => {
      try {
        const result = await checkQPayPayment(qpayInvoiceId);
        if (result.paid) {
          clearInterval(interval);
          clearCart();
          setStep('complete');
          sessionStorage.removeItem('rw-pending-payment');
          toast.success('Төлбөр амжилттай төлөгдлөө!');
        }
      } catch {
        // Silent retry
      }
    }, 3000);

    // Immediately check when user returns from bank app (tab becomes visible again)
    const handleVisibility = async () => {
      if (document.visibilityState === 'visible' && qpayInvoiceId) {
        try {
          const result = await checkQPayPayment(qpayInvoiceId);
          if (result.paid) {
            clearInterval(interval);
            clearCart();
            setStep('complete');
            sessionStorage.removeItem('rw-pending-payment');
            toast.success('Төлбөр амжилттай төлөгдлөө!');
          }
        } catch { /* ignore */ }
      }
    };
    document.addEventListener('visibilitychange', handleVisibility);

    return () => {
      clearInterval(interval);
      document.removeEventListener('visibilitychange', handleVisibility);
    };
  }, [step, qpayInvoiceId, paymentChecking]);

  const handleSwitchPaymentMethod = async (newMethod: 'qpay' | 'transfer' | 'bonum' | 'storepay') => {
    if (newMethod === paymentMethod || switchingMethod || !orderNumber) return;
    setSwitchingMethod(true);
    try {
      await updateOrderPaymentMethod(orderNumber, newMethod);
      setPaymentMethod(newMethod);
      setQpayInvoiceId('');
      setQrCodeUrl('');
      setQpayUrls([]);
      setQpayError('');
      setBonumInvoiceId('');
      setBonumFollowUpLink('');
      setStorepayInvoiceId('');
      setStorepayError('');
      setQpaySecondsLeft(null);

      if (newMethod === 'qpay') {
        try {
          const invoice = await createQPayInvoice(orderNumber, confirmedTotal ?? total, `Захиалга #${orderNumber}`);
          setQpayInvoiceId(invoice.invoice_id);
          setQrCodeUrl(invoice.qr_image ? `data:image/png;base64,${invoice.qr_image}` : '');
          setQpayUrls(invoice.urls || []);
        } catch {
          toast.error('QPay нэхэмжлэх үүсгэхэд алдаа гарлаа');
        }
      } else if (newMethod === 'bonum') {
        try {
          const invoice = await createBonumInvoice(orderNumber, confirmedTotal ?? total, `Runners World захиалга #${orderNumber}`);
          setBonumInvoiceId(invoice.invoice_id);
          setBonumFollowUpLink(invoice.follow_up_link);
        } catch {
          toast.error('Bonum нэхэмжлэх үүсгэхэд алдаа гарлаа');
        }
      }
    } catch {
      toast.error('Төлбөрийн хэлбэр солиход алдаа гарлаа');
    } finally {
      setSwitchingMethod(false);
    }
  };

  const handleShippingSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (submitting) return; // prevent double-click

    // StorePay requires a valid 8-digit Mongolian mobile number
    if (paymentMethod === 'storepay') {
      if (!/^\d{8}$/.test(storepayMobile.trim())) {
        toast.error('StorePay-д бүртгэлтэй 8 оронтой утасны дугаараа оруулна уу');
        return;
      }
    }

    setSubmitting(true);
    setQpayError('');
    setStorepayError('');

    try {
      let currentOrderNumber = orderNumber;
      let currentTotal = total;

      // Only create order if we don't already have one
      if (!currentOrderNumber) {
        // Step 1: Create the order
        let districtId: number | undefined;
        let khorooId: number | undefined;
        let address: string | undefined;
        let detailAddress: string | undefined;

        if (fulfillment === 'delivery') {
          if (!useNewAddress && selectedAddressId) {
            const saved = savedAddresses.find(a => a.id === selectedAddressId);
            if (saved) {
              districtId = saved.district_id;
              khorooId = saved.khoroo_id;
              address = saved.address;
              detailAddress = saved.detail_address || undefined;
            }
          } else {
            const district = districts.find(d => d.id === selectedDistrict);
            const selectedKhoroo = district?.khoroos.find(k => String(k.id) === shippingInfo.khoroo);
            districtId = selectedDistrict ? Number(selectedDistrict) : undefined;
            khorooId = selectedKhoroo?.id;
            address = shippingInfo.address;
            detailAddress = shippingInfo.detailAddress || undefined;
          }

          if (!districtId || !khorooId || !address) {
            toast.error('Хүргэлтийн хаяг бүрэн бөглөнө үү');
            setSubmitting(false);
            return;
          }
        }

        const result = await createOrder({
          customer_name: shippingInfo.name,
          customer_phone: shippingInfo.phone,
          fulfillment,
          district_id: districtId,
          khoroo_id: khorooId,
          address,
          detail_address: detailAddress,
          payment_method: paymentMethod,
          save_address: fulfillment === 'delivery' && useNewAddress && saveAddress ? true : undefined,
          items: cart.map(item => ({ product_id: Number(item.product.id), quantity: item.quantity, ...(item.variantId ? { variant_id: item.variantId } : {}) })),
        }, authToken || undefined);

        currentOrderNumber = result.order_number;
        currentTotal = result.total;
        setOrderNumber(currentOrderNumber);
        setConfirmedTotal(currentTotal);
      }

      // Step 2: Create payment invoice if needed
      if (paymentMethod === 'qpay') {
        try {
          const invoice = await createQPayInvoice(
            currentOrderNumber,
            currentTotal,
            `Захиалга #${currentOrderNumber}`
          );
          setQpayInvoiceId(invoice.invoice_id);
          setQrCodeUrl(invoice.qr_image ? `data:image/png;base64,${invoice.qr_image}` : '');
          setQpayUrls(invoice.urls || []);
          setStep('payment');
        } catch (qErr: any) {
          // Order created but QPay failed — show fallback
          setQpayError(qErr.message || 'QPay нэхэмжлэх үүсгэхэд алдаа гарлаа');
          setQrCodeUrl(`https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=rw_${currentOrderNumber}_${currentTotal}`);
          setStep('payment');
        }
      } else if (paymentMethod === 'bonum') {
        try {
          const invoice = await createBonumInvoice(
            currentOrderNumber,
            currentTotal,
            `Runners World захиалга #${currentOrderNumber}`
          );
          setBonumInvoiceId(invoice.invoice_id);
          setBonumFollowUpLink(invoice.follow_up_link);
          setStep('payment');
        } catch (bErr: any) {
          toast.error(bErr.message || 'Bonum нэхэмжлэх үүсгэхэд алдаа гарлаа');
        }
      } else if (paymentMethod === 'storepay') {
        try {
          const invoice = await createStorepayInvoice(
            currentOrderNumber,
            currentTotal,
            storepayMobile.trim(),
            `Runners World захиалга #${currentOrderNumber}`
          );
          setStorepayInvoiceId(invoice.invoice_id);
          setStep('payment');
          toast.success('StorePay-ээс таны утсанд нэхэмжлэх илгээгдлээ');
        } catch (sErr: any) {
          setStorepayError(sErr.message || 'StorePay нэхэмжлэх үүсгэхэд алдаа гарлаа');
          toast.error(sErr.message || 'StorePay нэхэмжлэх үүсгэхэд алдаа гарлаа');
        }
      } else {
        // Bank transfer — go straight to payment step
        setStep('payment');
      }
    } catch (err: any) {
      toast.error(err.message || 'Захиалга үүсгэхэд алдаа гарлаа');
    } finally {
      setSubmitting(false);
    }
  };

  const handlePaymentComplete = async () => {
    setSubmitting(true);
    try {
      if (qpayInvoiceId) {
        const result = await checkQPayPayment(qpayInvoiceId);
        if (result.paid) {
          clearCart();
          setStep('complete');
          sessionStorage.removeItem('rw-pending-payment');
          toast.success('Төлбөр амжилттай баталгаажлаа!');
          return;
        }
        toast.info('Төлбөр хараахан төлөгдөөгүй байна. QR кодыг уншуулна уу.');
      } else {
        // Fallback: QPay wasn't available, just complete
        clearCart();
        setStep('complete');
        toast.success('Захиалга амжилттай баталгаажлаа!');
      }
    } catch (err: any) {
      toast.error(err.message || 'Төлбөр шалгахад алдаа гарлаа');
    } finally {
      setSubmitting(false);
    }
  };

  const khoroos = districts.find(d => d.id === selectedDistrict)?.khoroos || [];

  if (step === 'complete') {
    return (
      <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div className="text-center">
          <CheckCircle className="w-20 h-20 text-green-500 mx-auto mb-6" />
          <h1 className="text-3xl font-bold text-gray-900 mb-4">Захиалга баталгаажлаа!</h1>
          <p className="text-lg text-gray-600 mb-8">
            Таны захиалга амжилттай хүлээн авлаа. Баярлалаа.
          </p>
          <div className="bg-gray-50 rounded-lg p-6 mb-8">
            <p className="text-sm text-gray-600 mb-2">Захиалгын дугаар</p>
            <p className="text-2xl font-bold text-gray-900">
              #{orderNumber || Date.now().toString().slice(-8)}
            </p>
          </div>
          <div className="space-y-3">
            <button
              onClick={() => navigate('/')}
              className="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors"
            >
              Дэлгүүр үзэх
            </button>
            <button
              onClick={() => navigate('/profile')}
              className="w-full bg-gray-100 text-gray-700 py-3 rounded-lg font-semibold hover:bg-gray-200 transition-colors"
            >
              Захиалга харах
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <h1 className="text-3xl font-bold text-gray-900 mb-8">Төлбөр тооцоо</h1>

      {/* Progress Steps */}
      <div className="mb-8">
        <div className="flex items-center justify-center gap-4">
          <div className={`flex items-center gap-2 ${step === 'shipping' ? 'text-blue-600' : 'text-gray-400'}`}>
            <div className={`w-8 h-8 rounded-full flex items-center justify-center ${step === 'shipping' ? 'bg-blue-600 text-white' : 'bg-gray-200'}`}>
              1
            </div>
            <span className="hidden sm:inline font-medium">Хүргэлт</span>
          </div>
          <div className="w-16 h-1 bg-gray-200"></div>
          <div className={`flex items-center gap-2 ${step === 'payment' ? 'text-blue-600' : 'text-gray-400'}`}>
            <div className={`w-8 h-8 rounded-full flex items-center justify-center ${step === 'payment' ? 'bg-blue-600 text-white' : 'bg-gray-200'}`}>
              2
            </div>
            <span className="hidden sm:inline font-medium">Төлбөр</span>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Form */}
        <div className="lg:col-span-2">
          {step === 'shipping' && (
            <form onSubmit={handleShippingSubmit} className="bg-white rounded-lg shadow-sm p-6">
              <h2 className="text-xl font-bold text-gray-900 mb-6">Захиалгын мэдээлэл</h2>

              {/* Fulfillment Toggle */}
              <div className="grid grid-cols-2 gap-3 mb-6">
                <button
                  type="button"
                  onClick={() => setFulfillment('pickup')}
                  className={`flex flex-col items-center gap-2 p-4 rounded-lg border-2 transition-colors ${
                    fulfillment === 'pickup'
                      ? 'border-blue-600 bg-blue-50 text-blue-700'
                      : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'
                  }`}
                >
                  <Store className="w-6 h-6" />
                  <span className="font-semibold text-sm">Очиж авах</span>
                </button>
                <button
                  type="button"
                  onClick={() => setFulfillment('delivery')}
                  className={`flex flex-col items-center gap-2 p-4 rounded-lg border-2 transition-colors ${
                    fulfillment === 'delivery'
                      ? 'border-blue-600 bg-blue-50 text-blue-700'
                      : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'
                  }`}
                >
                  <Truck className="w-6 h-6" />
                  <span className="font-semibold text-sm">Хүргэлт</span>
                </button>
              </div>

              {fulfillment === 'delivery' && (
                <div className="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6">
                  <ul className="text-xs text-amber-700 space-y-1 list-disc list-inside">
                    <li>Та захиалсан бараагаа хүргэлтээр авах бол тус сонголтыг хийнэ үү.</li>
                  </ul>
                </div>
              )}

              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Нэр *
                  </label>
                  <input
                    type="text"
                    required
                    value={shippingInfo.name}
                    onChange={(e) => setShippingInfo({ ...shippingInfo, name: e.target.value })}
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Утасны дугаар *
                  </label>
                  <input
                    type="tel"
                    required
                    value={shippingInfo.phone}
                    onChange={(e) => setShippingInfo({ ...shippingInfo, phone: e.target.value })}
                    placeholder="99991234"
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>

                {/* Address section - only for delivery */}
                {fulfillment === 'delivery' && savedAddresses.length > 0 && (
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Хүргэлтийн хаяг
                    </label>
                    <div className="space-y-2">
                      {savedAddresses.map((addr) => (
                        <label
                          key={addr.id}
                          className={`flex items-start gap-3 p-3 border rounded-lg cursor-pointer transition-colors ${
                            !useNewAddress && selectedAddressId === addr.id
                              ? 'border-blue-500 bg-blue-50'
                              : 'border-gray-200 hover:border-gray-300'
                          }`}
                        >
                          <input
                            type="radio"
                            name="address_choice"
                            checked={!useNewAddress && selectedAddressId === addr.id}
                            onChange={() => {
                              setUseNewAddress(false);
                              setSelectedAddressId(addr.id);
                            }}
                            className="mt-1"
                          />
                          <div className="flex-1">
                            <div className="flex items-center gap-2">
                              <Home className="w-4 h-4 text-gray-500" />
                              <span className="text-sm font-medium text-gray-900">
                                {addr.district_name} {addr.khoroo_name || (addr.khoroo_number ? `${addr.khoroo_number}-р хороо` : '')}
                              </span>
                              {addr.is_default === 1 && (
                                <span className="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Үндсэн</span>
                              )}
                            </div>
                            <p className="text-sm text-gray-600 mt-1">
                              {addr.address}{addr.detail_address ? `, ${addr.detail_address}` : ''}
                            </p>
                          </div>
                        </label>
                      ))}
                      <label
                        className={`flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition-colors ${
                          useNewAddress
                            ? 'border-blue-500 bg-blue-50'
                            : 'border-gray-200 hover:border-gray-300'
                        }`}
                      >
                        <input
                          type="radio"
                          name="address_choice"
                          checked={useNewAddress}
                          onChange={() => setUseNewAddress(true)}
                        />
                        <span className="text-sm font-medium text-gray-700">+ Шинэ хаяг оруулах</span>
                      </label>
                    </div>
                  </div>
                )}

                {/* New Address Form */}
                {fulfillment === 'delivery' && useNewAddress && (
                  <>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Дүүрэг *
                      </label>
                      <select
                        required={useNewAddress}
                        value={selectedDistrict}
                        onChange={(e) => {
                          setSelectedDistrict(e.target.value);
                          setShippingInfo({ ...shippingInfo, district: e.target.value, khoroo: '' });
                        }}
                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
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
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Хороо *
                        </label>
                        <select
                          required={useNewAddress}
                          value={shippingInfo.khoroo}
                          onChange={(e) => setShippingInfo({ ...shippingInfo, khoroo: e.target.value })}
                          className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
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
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Хаяг *
                      </label>
                      <input
                        type="text"
                        required={useNewAddress}
                        value={shippingInfo.address}
                        onChange={(e) => setShippingInfo({ ...shippingInfo, address: e.target.value })}
                        placeholder="Гудамж, байр, тоот"
                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                      />
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Нэмэлт хаяг
                      </label>
                      <input
                        type="text"
                        value={shippingInfo.detailAddress}
                        onChange={(e) => setShippingInfo({ ...shippingInfo, detailAddress: e.target.value })}
                        placeholder="Орц, давхар, тоот"
                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                      />
                    </div>

                    {isAuthenticated && (
                      <label className="flex items-center gap-2 cursor-pointer">
                        <input
                          type="checkbox"
                          checked={saveAddress}
                          onChange={(e) => setSaveAddress(e.target.checked)}
                          className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        />
                        <span className="text-sm text-gray-700">Энэ хаягийг хадгалах</span>
                      </label>
                    )}
                  </>
                )}
              </div>

              {/* Payment Method Toggle — show when more than one method is enabled */}
              {([settings.payment_qpay_enabled, settings.payment_transfer_enabled, settings.payment_bonum_enabled, settings.payment_storepay_enabled].filter(Boolean).length > 1) && (
                <div className="mt-6">
                  <label className="block text-sm font-medium text-gray-700 mb-2">Төлбөрийн хэлбэр</label>
                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    {settings.payment_transfer_enabled && (
                      <button
                        type="button"
                        onClick={() => setPaymentMethod('transfer')}
                        className={`flex flex-col items-center gap-2 p-4 rounded-lg border-2 transition-colors ${
                          paymentMethod === 'transfer'
                            ? 'border-blue-600 bg-blue-50 text-blue-700'
                            : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'
                        }`}
                      >
                        <Building2 className="w-6 h-6" />
                        <span className="font-semibold text-sm">Шилжүүлэг</span>
                      </button>
                    )}
                    {settings.payment_qpay_enabled && (
                      <button
                        type="button"
                        onClick={() => setPaymentMethod('qpay')}
                        className={`flex flex-col items-center gap-2 p-4 rounded-lg border-2 transition-colors ${
                          paymentMethod === 'qpay'
                            ? 'border-blue-600 bg-blue-50 text-blue-700'
                            : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'
                        }`}
                      >
                        <CreditCard className="w-6 h-6" />
                        <span className="font-semibold text-sm">QPay</span>
                      </button>
                    )}
                    {settings.payment_bonum_enabled && (
                      <button
                        type="button"
                        onClick={() => setPaymentMethod('bonum')}
                        className={`flex flex-col items-center gap-2 p-4 rounded-lg border-2 transition-colors ${
                          paymentMethod === 'bonum'
                            ? 'border-indigo-600 bg-indigo-50 text-indigo-700'
                            : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'
                        }`}
                      >
                        <CreditCard className="w-6 h-6" />
                        <span className="font-semibold text-sm">Bonum</span>
                      </button>
                    )}
                    {settings.payment_storepay_enabled && (
                      <button
                        type="button"
                        onClick={() => setPaymentMethod('storepay')}
                        className={`flex flex-col items-center gap-2 p-4 rounded-lg border-2 transition-colors ${
                          paymentMethod === 'storepay'
                            ? 'border-violet-600 bg-violet-50 text-violet-700'
                            : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'
                        }`}
                      >
                        <CreditCard className="w-6 h-6" />
                        <span className="font-semibold text-sm">StorePay</span>
                      </button>
                    )}
                  </div>
                </div>
              )}

              {paymentMethod === 'storepay' && (
                <div className="mt-4 bg-violet-50 border border-violet-200 rounded-lg p-4 space-y-3">
                  <div>
                    <label className="block text-sm font-medium text-violet-900 mb-1">
                      StorePay-д бүртгэлтэй утасны дугаар *
                    </label>
                    <input
                      type="tel"
                      inputMode="numeric"
                      required={paymentMethod === 'storepay'}
                      value={storepayMobile}
                      onChange={(e) => setStorepayMobile(e.target.value.replace(/\D/g, '').slice(0, 8))}
                      placeholder="99999999"
                      maxLength={8}
                      className="w-full px-4 py-2 border border-violet-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 bg-white"
                    />
                  </div>
                  <p className="text-xs text-violet-700">
                    Энэ дугаар нь StorePay-д бүртгэлтэй байх ёстой. Нэхэмжлэх илгээсний дараа StorePay апп-аасаа баталгаажуулна уу.
                  </p>
                </div>
              )}

              <button
                type="submit"
                disabled={submitting}
                className="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors mt-6 disabled:opacity-50"
              >
                {submitting ? 'Боловсруулж байна...' : 'Төлбөр төлөх'}
              </button>
            </form>
          )}

          {step === 'payment' && (
            <div className="bg-white rounded-lg shadow-sm p-6">
              <button
                type="button"
                onClick={async () => {
                  if (orderNumber) {
                    try { await cancelOrder(orderNumber); } catch { /* best-effort */ }
                  }
                  setStep('shipping');
                  setOrderNumber('');
                  setQpayInvoiceId('');
                  setQrCodeUrl('');
                  setQpayUrls([]);
                  setQpayError('');
                  setBonumInvoiceId('');
                  setBonumFollowUpLink('');
                  setStorepayInvoiceId('');
                  setStorepayError('');
                  setConfirmedTotal(null);
                  setQpaySecondsLeft(null);
                  sessionStorage.removeItem('rw-pending-payment');
                }}
                className="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-4"
              >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>
                Буцах
              </button>
              {/* Payment method switcher — shown when multiple methods are enabled */}
              {([settings.payment_qpay_enabled, settings.payment_transfer_enabled, settings.payment_bonum_enabled, settings.payment_storepay_enabled].filter(Boolean).length > 1) && (
                <div className="flex gap-2 mb-6 flex-wrap">
                  {settings.payment_transfer_enabled && (
                    <button type="button" disabled={switchingMethod} onClick={() => handleSwitchPaymentMethod('transfer')}
                      className={`px-3 py-1.5 rounded-full text-sm font-medium border transition-colors ${paymentMethod === 'transfer' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400'}`}>
                      Шилжүүлэг
                    </button>
                  )}
                  {settings.payment_qpay_enabled && (
                    <button type="button" disabled={switchingMethod} onClick={() => handleSwitchPaymentMethod('qpay')}
                      className={`px-3 py-1.5 rounded-full text-sm font-medium border transition-colors ${paymentMethod === 'qpay' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400'}`}>
                      {switchingMethod && paymentMethod !== 'qpay' ? '...' : 'QPay'}
                    </button>
                  )}
                  {settings.payment_bonum_enabled && (
                    <button type="button" disabled={switchingMethod} onClick={() => handleSwitchPaymentMethod('bonum')}
                      className={`px-3 py-1.5 rounded-full text-sm font-medium border transition-colors ${paymentMethod === 'bonum' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:border-indigo-400'}`}>
                      {switchingMethod && paymentMethod !== 'bonum' ? '...' : 'Bonum'}
                    </button>
                  )}
                  {settings.payment_storepay_enabled && (
                    <button type="button" disabled={switchingMethod} onClick={() => handleSwitchPaymentMethod('storepay')}
                      className={`px-3 py-1.5 rounded-full text-sm font-medium border transition-colors ${paymentMethod === 'storepay' ? 'bg-violet-600 text-white border-violet-600' : 'bg-white text-gray-600 border-gray-300 hover:border-violet-400'}`}>
                      {switchingMethod && paymentMethod !== 'storepay' ? '...' : 'StorePay'}
                    </button>
                  )}
                </div>
              )}

              {paymentMethod === 'storepay' ? (
                <>
                  <h2 className="text-xl font-bold text-gray-900 mb-6 text-center">StorePay-ээр төлөх</h2>

                  <div className="max-w-sm mx-auto">
                    <div className="text-center mb-6">
                      <p className="text-gray-700 mb-1">Төлөх дүн</p>
                      <p className="text-3xl font-bold text-gray-900">{displayTotal.toLocaleString()}₮</p>
                      {orderNumber && <p className="text-sm text-gray-500 mt-1">Захиалга #{orderNumber}</p>}
                      {storepayMobile && (
                        <p className="text-sm text-gray-500 mt-1">Утас: {storepayMobile}</p>
                      )}
                    </div>

                    {storepayError && (
                      <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                        <p className="text-sm text-red-800">{storepayError}</p>
                      </div>
                    )}

                    <div className="bg-violet-50 border border-violet-200 rounded-lg p-4 mb-4">
                      <div className="flex items-center gap-2 mb-2">
                        <div className="w-2 h-2 bg-violet-500 rounded-full animate-pulse"></div>
                        <p className="text-sm font-medium text-violet-800">StorePay апп-аас баталгаажуулна уу</p>
                      </div>
                      <ul className="text-xs text-violet-700 space-y-1 list-disc list-inside">
                        <li>StorePay апп руу нэхэмжлэх илгээгдсэн</li>
                        <li>Апп-аасаа орж нэхэмжлэхийг батална</li>
                        <li>Баталсны дараа автоматаар шалгагдана</li>
                      </ul>
                    </div>

                    <button
                      onClick={async () => {
                        if (!storepayInvoiceId) return;
                        setSubmitting(true);
                        try {
                          const result = await checkStorepayPayment(storepayInvoiceId);
                          if (result.paid) {
                            clearCart();
                            setStep('complete');
                            sessionStorage.removeItem('rw-pending-payment');
                            toast.success('StorePay төлбөр амжилттай баталгаажлаа!');
                          } else {
                            toast.info('Нэхэмжлэх хараахан баталгаажаагүй байна. StorePay апп-аасаа баталгаажуулна уу.');
                          }
                        } catch (err: any) {
                          toast.error(err.message || 'Төлбөр шалгахад алдаа гарлаа');
                        } finally {
                          setSubmitting(false);
                        }
                      }}
                      disabled={submitting || !storepayInvoiceId}
                      className="w-full bg-green-600 text-white py-3 rounded-lg font-semibold hover:bg-green-700 transition-colors disabled:opacity-50 mb-2"
                    >
                      {submitting ? 'Шалгаж байна...' : 'Баталгаажуулсан'}
                    </button>
                    <p className="text-xs text-gray-400 text-center">Автоматаар шалгагдана. Хэрэв шалгагдахгүй бол дарна уу.</p>
                  </div>
                </>
              ) : paymentMethod === 'bonum' ? (
                <>
                  <h2 className="text-xl font-bold text-gray-900 mb-6 text-center">Bonum-аар төлөх</h2>

                  <div className="max-w-sm mx-auto">
                    <div className="text-center mb-6">
                      <p className="text-gray-700 mb-1">Төлөх дүн</p>
                      <p className="text-3xl font-bold text-gray-900">{displayTotal.toLocaleString()}₮</p>
                      {orderNumber && <p className="text-sm text-gray-500 mt-1">Захиалга #{orderNumber}</p>}
                    </div>

                    {bonumFollowUpLink && (
                      <a
                        href={bonumFollowUpLink}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex items-center justify-center gap-2 w-full bg-indigo-600 text-white py-3 rounded-lg font-semibold hover:bg-indigo-700 transition-colors mb-4"
                      >
                        <CreditCard className="w-5 h-5" />
                        Bonum-аар төлөх
                      </a>
                    )}

                    <p className="text-xs text-orange-600 text-center mb-4">
                      ⚠️ Дээрх товчийг дараад төлбөрөө хийгээрэй. Төлсний дараа энэ хуудас руу буцаж ирнэ үү.
                    </p>

                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                      <div className="flex items-center gap-2 mb-1">
                        <div className="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></div>
                        <p className="text-sm font-medium text-blue-800">Төлбөр хүлээж байна...</p>
                      </div>
                      <p className="text-xs text-blue-700">
                        Bonum төлбөр хийгдсэний дараа автоматаар баталгаажна.
                      </p>
                    </div>

                    <button
                      onClick={async () => {
                        setSubmitting(true);
                        try {
                          const result = await checkBonumPayment(bonumInvoiceId);
                          if (result.paid) {
                            clearCart();
                            setStep('complete');
                            sessionStorage.removeItem('rw-pending-payment');
                            toast.success('Bonum төлбөр амжилттай баталгаажлаа!');
                          } else {
                            toast.info('Төлбөр хийгдээгүй байна. Bonum хуудсыг нээгээд төлнө үү.');
                          }
                        } catch (err: any) {
                          toast.error(err.message || 'Төлбөр шалгахад алдаа гарлаа');
                        } finally {
                          setSubmitting(false);
                        }
                      }}
                      disabled={submitting}
                      className="w-full bg-green-600 text-white py-3 rounded-lg font-semibold hover:bg-green-700 transition-colors disabled:opacity-50 mb-2"
                    >
                      {submitting ? 'Шалгаж байна...' : 'Төлбөр төлсөн'}
                    </button>
                    <p className="text-xs text-gray-400 text-center">Автоматаар шалгагдана. Хэрэв шалгагдахгүй бол дарна уу.</p>
                  </div>
                </>
              ) : paymentMethod === 'qpay' ? (
                <>
                  <h2 className="text-xl font-bold text-gray-900 mb-6 text-center">QPay-ээр төлөх</h2>

                  {qpayError && (
                    <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                      <p className="text-sm text-yellow-800">{qpayError}</p>
                      <p className="text-xs text-yellow-600 mt-1">Захиалга үүссэн. Админтай холбогдоно уу.</p>
                    </div>
                  )}

                  <div className="max-w-sm mx-auto">
                    {qrCodeUrl && (
                      <div className="bg-gray-50 rounded-lg p-6 mb-4 relative">
                        {qpaySecondsLeft !== null && qpaySecondsLeft <= 0 ? (
                          <div className="absolute inset-0 bg-white/90 rounded-lg flex flex-col items-center justify-center gap-3 p-4">
                            <p className="text-sm font-medium text-red-600 text-center">QR кодны хугацаа дууслаа</p>
                            <button
                              onClick={async () => {
                                try {
                                  const invoice = await createQPayInvoice(orderNumber, confirmedTotal ?? total, `Захиалга #${orderNumber}`);
                                  setQpayInvoiceId(invoice.invoice_id);
                                  setQrCodeUrl(invoice.qr_image ? `data:image/png;base64,${invoice.qr_image}` : '');
                                  setQpayUrls(invoice.urls || []);
                                  setQpaySecondsLeft(30 * 60);
                                } catch { toast.error('QPay нэхэмжлэх үүсгэхэд алдаа гарлаа'); }
                              }}
                              className="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700"
                            >
                              Шинэ QR авах
                            </button>
                          </div>
                        ) : (
                          <>
                            <img src={qrCodeUrl} alt="QPay QR Code" className="w-full h-auto rounded-lg" />
                            {qpaySecondsLeft !== null && qpaySecondsLeft <= 300 && (
                              <p className="text-xs text-orange-500 text-center mt-2">
                                QR дуусахад: {Math.floor(qpaySecondsLeft / 60)}:{String(qpaySecondsLeft % 60).padStart(2, '0')}
                              </p>
                            )}
                          </>
                        )}
                      </div>
                    )}

                    <div className="text-center mb-4">
                      <p className="text-gray-700 mb-1">Төлөх дүн</p>
                      <p className="text-3xl font-bold text-gray-900">{displayTotal.toLocaleString()}₮</p>
                      {orderNumber && <p className="text-sm text-gray-500 mt-1">Захиалга #{orderNumber}</p>}
                    </div>

                    {/* Bank App Deeplinks */}
                    {qpayUrls.length > 0 && (
                      <div className="mb-4">
                        <p className="text-sm font-medium text-gray-700 mb-3 text-center">Банкны апп-аар төлөх</p>
                        <div className="grid grid-cols-4 gap-2">
                          {qpayUrls.map((url, i) => (
                            <a
                              key={i}
                              href={url.link}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="flex flex-col items-center gap-1 p-2 rounded-lg border border-gray-200 hover:border-blue-400 hover:bg-blue-50 transition-colors"
                            >
                              <img src={url.logo} alt={url.description} className="w-8 h-8 rounded" />
                              <span className="text-[10px] text-gray-600 text-center leading-tight line-clamp-1">{url.description}</span>
                            </a>
                          ))}
                        </div>
                        <p className="text-xs text-orange-600 text-center mt-2">⚠️ Банкны апп-д төлсний дараа энэ хуудас руу буцаж ирнэ үү</p>
                      </div>
                    )}

                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                      <div className="flex items-center gap-2 mb-1">
                        <div className="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></div>
                        <p className="text-sm font-medium text-blue-800">Төлбөр хүлээж байна...</p>
                      </div>
                      <p className="text-xs text-blue-700">
                        QPay апп-аас QR кодыг уншуулж эсвэл дээрх банкны апп дээр дарж төлбөрөө төлнө үү. Төлбөр төлөгдсөний дараа автоматаар баталгаажна.
                      </p>
                    </div>

                    <button
                      onClick={handlePaymentComplete}
                      disabled={submitting}
                      className="w-full bg-green-600 text-white py-3 rounded-lg font-semibold hover:bg-green-700 transition-colors disabled:opacity-50 mb-2"
                    >
                      {submitting ? 'Шалгаж байна...' : 'Төлбөр төлсөн'}
                    </button>
                    <p className="text-xs text-gray-400 text-center">Автоматаар шалгагдана. Хэрэв шалгагдахгүй бол дарна уу.</p>
                  </div>
                </>
              ) : (
                <>
                  <h2 className="text-xl font-bold text-gray-900 mb-6 text-center">Банкны шилжүүлгээр төлөх</h2>

                  <div className="max-w-sm mx-auto">
                    <div className="text-center mb-6">
                      <p className="text-gray-700 mb-1">Төлөх дүн</p>
                      <p className="text-3xl font-bold text-gray-900">{displayTotal.toLocaleString()}₮</p>
                      {orderNumber && <p className="text-sm text-gray-500 mt-1">Захиалга #{orderNumber}</p>}
                    </div>

                    {/* Bank Account Details */}
                    <div className="bg-gray-50 rounded-lg p-5 mb-6 space-y-4">
                      {settings.bank_name && (
                        <div className="flex justify-between items-center">
                          <span className="text-sm text-gray-600">Банк</span>
                          <span className="text-sm font-semibold text-gray-900">{String(settings.bank_name)}</span>
                        </div>
                      )}
                      {settings.bank_account_number && (
                        <div className="flex justify-between items-center">
                          <span className="text-sm text-gray-600">Дансны дугаар</span>
                          <div className="flex items-center gap-2">
                            <span className="text-sm font-semibold text-gray-900 font-mono">{String(settings.bank_account_number)}</span>
                            <button
                              type="button"
                              onClick={() => {
                                navigator.clipboard.writeText(String(settings.bank_account_number));
                                toast.success('Дансны дугаар хуулагдлаа');
                              }}
                              className="p-1 text-gray-400 hover:text-blue-600 transition-colors"
                            >
                              <Copy className="w-4 h-4" />
                            </button>
                          </div>
                        </div>
                      )}
                      {settings.bank_account_name && (
                        <div className="flex justify-between items-center">
                          <span className="text-sm text-gray-600">Дансны нэр</span>
                          <span className="text-sm font-semibold text-gray-900">{String(settings.bank_account_name)}</span>
                        </div>
                      )}
                      <div className="flex justify-between items-center border-t pt-3">
                        <span className="text-sm text-gray-600">Гүйлгээний утга</span>
                        <div className="flex items-center gap-2">
                          <span className="text-sm font-semibold text-blue-700 font-mono">{orderNumber}</span>
                          <button
                            type="button"
                            onClick={() => {
                              navigator.clipboard.writeText(orderNumber);
                              toast.success('Гүйлгээний утга хуулагдлаа');
                            }}
                            className="p-1 text-gray-400 hover:text-blue-600 transition-colors"
                          >
                            <Copy className="w-4 h-4" />
                          </button>
                        </div>
                      </div>
                    </div>

                    <div className="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6">
                      <p className="text-sm font-medium text-amber-800 mb-1">Анхааруулга</p>
                      <ul className="text-xs text-amber-700 space-y-1 list-disc list-inside">
                        <li>Гүйлгээний утга дээр захиалгын дугаараа <strong>заавал</strong> бичнэ үү</li>
                        <li>Шилжүүлсний дараа 1 цагийн дотор баталгаажна</li>
                        <li>Асуудал гарвал админтай холбогдоно уу</li>
                      </ul>
                    </div>

                    <button
                      onClick={() => {
                        clearCart();
                        setStep('complete');
                        sessionStorage.removeItem('rw-pending-payment');
                      }}
                      className="w-full bg-green-600 text-white py-3 rounded-lg font-semibold hover:bg-green-700 transition-colors mb-2"
                    >
                      Шилжүүлэг хийсэн
                    </button>
                    <p className="text-xs text-gray-400 text-center">Шилжүүлэг хийсний дараа дарна уу</p>
                  </div>
                </>
              )}
            </div>
          )}
        </div>

        {/* Order Summary */}
        <div className="lg:col-span-1">
          <div className="bg-white rounded-lg shadow-sm p-6 sticky top-24">
            <h2 className="text-xl font-bold text-gray-900 mb-4">Захиалгын дүн</h2>

            <div className="space-y-3 mb-4 max-h-60 overflow-y-auto">
              {cart.map((item) => (
                <div key={item.product.id} className="flex gap-3">
                  <img
                    src={item.product.image}
                    alt={item.product.name}
                    className="w-16 h-16 object-cover rounded"
                  />
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-gray-900 line-clamp-1">
                      {item.product.nameMn}
                    </p>
                    <p className="text-sm text-gray-600">Ширхэг: {item.quantity}</p>
                    <p className="text-sm font-medium text-gray-900">
                      {(item.product.price * item.quantity).toLocaleString()}₮
                    </p>
                    {item.product.type === 'preorder' && item.product.weightKg && settings.cargo_rate_per_kg > 0 && (
                      <p className="text-xs text-orange-600">
                        {item.product.hideCargoFee
                          ? 'Ачаа ирэхэд төлнө'
                          : `+ ачааны ${(item.product.weightKg * settings.cargo_rate_per_kg * item.quantity).toLocaleString()}₮`}
                      </p>
                    )}
                  </div>
                </div>
              ))}
            </div>

            <div className="border-t pt-4 space-y-2">
              <div className="flex justify-between text-gray-700">
                <span>Нийт</span>
                <span>{cartTotal.toLocaleString()}₮</span>
              </div>
              <div className="flex justify-between text-gray-700">
                <span>{fulfillment === 'pickup' ? 'Очиж авах' : 'Хүргэлт'}</span>
                <span>
                  {fulfillment === 'pickup' ? (
                    'Үнэгүй'
                  ) : !settings.delivery_fee_enabled ? (
                    <span className="text-sm text-gray-600">Хүргэлтийн үед бодогдоно</span>
                  ) : shippingFee === 0 ? (
                    <span className="text-green-600">Үнэгүй</span>
                  ) : (
                    <span className="font-medium">{shippingFee.toLocaleString()}₮</span>
                  )}
                </span>
              </div>
              {fulfillment === 'delivery' && settings.delivery_fee_enabled && shippingFee === 0 && settings.free_delivery_threshold > 0 && (
                <p className="text-xs text-green-600">
                  {settings.free_delivery_threshold.toLocaleString()}₮-аас дээш захиалга үнэгүй хүргэлттэй
                </p>
              )}
              {fulfillment === 'delivery' && settings.delivery_fee_enabled && shippingFee > 0 && settings.free_delivery_threshold > 0 && cartTotal < settings.free_delivery_threshold && (
                <p className="text-xs text-gray-500">
                  {(settings.free_delivery_threshold - cartTotal).toLocaleString()}₮ нэмж авбал үнэгүй хүргэлттэй
                </p>
              )}
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
              <div className="border-t pt-2 flex justify-between text-lg font-bold text-gray-900">
                <span>Нийт дүн</span>
                <span>{displayTotal.toLocaleString()}₮</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};