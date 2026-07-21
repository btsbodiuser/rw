import React from 'react';
import { Truck, Clock, MapPin, DollarSign, Package } from 'lucide-react';
import { useApi } from '../hooks/useApi';
import { fetchSettings } from '../services/api';

export const DeliveryPage = () => {
  const { data: settings } = useApi(() => fetchSettings(), []);
  const deliveryFeeEnabled = settings?.delivery_fee_enabled === undefined ? true : !!settings.delivery_fee_enabled;
  const deliveryFee = typeof settings?.delivery_fee === 'number' ? settings.delivery_fee : (Number(settings?.delivery_fee) || 0);
  const freeThreshold = typeof settings?.free_delivery_threshold === 'number' ? settings.free_delivery_threshold : (Number(settings?.free_delivery_threshold) || 0);

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <h1 className="text-3xl font-bold mb-8">Хүргэлтийн мэдээлэл</h1>
      
      <div className="bg-blue-50 p-6 rounded-lg mb-8">
        <div className="flex items-start gap-4">
          <MapPin className="w-6 h-6 text-blue-600 mt-1" />
          <div>
            <h3 className="font-semibold text-lg mb-2">Хүргэлтийн хүрээ</h3>
            <p className="text-gray-700">
              Бид зөвхөн <strong>Улаанбаатар хотод</strong> хүргэлт үйлчилгээ үзүүлж байна.
              Бүх дүүрэг болон хороонд хүргэлт хийх боломжтой.
            </p>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-12">
        <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
          <div className="flex items-start gap-4">
            <div className="p-3 bg-green-50 rounded-lg">
              <Package className="w-6 h-6 text-green-600" />
            </div>
            <div>
              <h3 className="font-semibold mb-2">Бэлэн бараа</h3>
              <p className="text-gray-600 text-sm mb-2">Агуулахад байгаа бараанууд</p>
              <div className="flex items-center gap-2 text-sm">
                <Clock className="w-4 h-4 text-gray-500" />
                <span className="text-gray-700">1-2 өдөр</span>
              </div>
            </div>
          </div>
        </div>

        <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
          <div className="flex items-start gap-4">
            <div className="p-3 bg-orange-50 rounded-lg">
              <Truck className="w-6 h-6 text-orange-600" />
            </div>
            <div>
              <h3 className="font-semibold mb-2">Урьдчилсан захиалга</h3>
              <p className="text-gray-600 text-sm mb-2">Солонгосоос захиалах бараа</p>
              <div className="flex items-center gap-2 text-sm">
                <Clock className="w-4 h-4 text-gray-500" />
                <span className="text-gray-700">7-21 өдөр</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="space-y-8">
        <section>
          <h2 className="text-2xl font-bold mb-4">Хүргэлтийн төлбөр</h2>
          <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            {!deliveryFeeEnabled ? (
              <p className="text-gray-700">Хүргэлтийн төлбөр захиалга хүргэгдэх үед бодогдоно.</p>
            ) : (
              <div className="space-y-4">
                <div className="flex justify-between items-center">
                  <span className="text-gray-700">Хүргэлтийн төлбөр</span>
                  <span className="font-semibold">{deliveryFee.toLocaleString()}₮</span>
                </div>
                {freeThreshold > 0 && (
                  <div className="flex justify-between items-center pt-4 border-t border-gray-200 text-sm">
                    <span className="text-gray-600">{freeThreshold.toLocaleString()}₮-аас дээш захиалга</span>
                    <span className="font-medium text-green-600">Үнэгүй</span>
                  </div>
                )}
              </div>
            )}
          </div>
        </section>

        <section>
          <h2 className="text-2xl font-bold mb-4">Хүргэлтийн процесс</h2>
          <div className="space-y-4">
            <div className="flex gap-4">
              <div className="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-semibold">
                1
              </div>
              <div>
                <h3 className="font-semibold mb-1">Захиалга баталгаажуулах</h3>
                <p className="text-gray-600 text-sm">
                  Таны захиалгын төлбөрийг хүлээн авсны дараа манай ажилтан хүргэлтийг баталгаажуулна.
                </p>
              </div>
            </div>

            <div className="flex gap-4">
              <div className="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-semibold">
                2
              </div>
              <div>
                <h3 className="font-semibold mb-1">Бэлтгэх</h3>
                <p className="text-gray-600 text-sm">
                  Бараагаа бэлтгэж, сайтар савлаж хүргэлтэнд бэлэн болгоно.
                </p>
              </div>
            </div>

            <div className="flex gap-4">
              <div className="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-semibold">
                3
              </div>
              <div>
                <h3 className="font-semibold mb-1">Хүргэх</h3>
                <p className="text-gray-600 text-sm">
                  Таны өгсөн хаягаар барааг хүргэнэ. Хүргэлтийн өмнө утсаар мэдэгдэнэ.
                </p>
              </div>
            </div>
          </div>
        </section>

        <section>
          <h2 className="text-2xl font-bold mb-4">Анхаарах зүйлс</h2>
          <div className="bg-yellow-50 p-6 rounded-lg border border-yellow-200">
            <ul className="space-y-2 text-gray-700">
              <li className="flex gap-2">
                <span>•</span>
                <span>Хүргэлтийн цагийг урьдчилан тохирсон байх шаардлагатай</span>
              </li>
              <li className="flex gap-2">
                <span>•</span>
                <span>Хаягаа үнэн зөв, дэлгэрэнгүй оруулна уу</span>
              </li>
              <li className="flex gap-2">
                <span>•</span>
                <span>Бараагаа хүлээн авахдаа заавал шалгана уу</span>
              </li>
            </ul>
          </div>
        </section>
      </div>
    </div>
  );
};