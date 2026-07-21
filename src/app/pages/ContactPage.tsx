import React from 'react';
import { Mail, Phone, MapPin } from 'lucide-react';
import { fetchSettings } from '../services/api';
import { useApi } from '../hooks/useApi';

export const ContactPage = () => {
  const { data: settings } = useApi(() => fetchSettings(), []);
  const s = (key: string, fallback = '') => String(settings?.[key] ?? fallback);

  const phone = s('phone');
  const email = s('email');
  const address = s('address');
  const siteName = s('site_name_mn') || s('site_name');

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <h1 className="text-3xl font-bold mb-8">Холбоо барих</h1>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
        {phone && (
          <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <div className="flex items-start gap-4">
              <div className="p-3 bg-blue-50 rounded-lg">
                <Phone className="w-6 h-6 text-blue-600" />
              </div>
              <div>
                <h3 className="font-semibold mb-2">Утас</h3>
                <a href={`tel:${phone.replace(/[^+\d]/g, '')}`} className="text-blue-600 hover:text-blue-700 font-medium text-lg">
                  {phone}
                </a>
              </div>
            </div>
          </div>
        )}

        {email && (
          <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <div className="flex items-start gap-4">
              <div className="p-3 bg-blue-50 rounded-lg">
                <Mail className="w-6 h-6 text-blue-600" />
              </div>
              <div>
                <h3 className="font-semibold mb-2">И-мэйл</h3>
                <a href={`mailto:${email}`} className="text-blue-600 hover:text-blue-700 font-medium">
                  {email}
                </a>
              </div>
            </div>
          </div>
        )}

        {address && (
          <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <div className="flex items-start gap-4">
              <div className="p-3 bg-blue-50 rounded-lg">
                <MapPin className="w-6 h-6 text-blue-600" />
              </div>
              <div>
                <h3 className="font-semibold mb-2">Хаяг</h3>
                <p className="text-gray-600">{address}</p>
              </div>
            </div>
          </div>
        )}

        {siteName && (
          <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <div className="flex items-start gap-4">
              <div className="p-3 bg-blue-50 rounded-lg">
                <MapPin className="w-6 h-6 text-blue-600" />
              </div>
              <div>
                <h3 className="font-semibold mb-2">Дэлгүүр</h3>
                <p className="text-gray-600">{siteName}</p>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};
