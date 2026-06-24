import React, { useMemo, useState } from 'react';
import { ChevronDown, ChevronUp, Loader2 } from 'lucide-react';
import { fetchSettings, fetchFaqs, ApiFaq } from '../services/api';
import { useApi } from '../hooks/useApi';

const ALL = 'Бүгд';

export const FAQPage = () => {
  const [openId, setOpenId] = useState<number | null>(null);
  const [selectedCategory, setSelectedCategory] = useState<string>(ALL);

  const { data: settings } = useApi(() => fetchSettings(), []);
  const { data: faqsData, loading, error } = useApi<ApiFaq[]>(() => fetchFaqs(), []);

  const s = (key: string, fallback = '') => String(settings?.[key] ?? fallback);
  const phone = s('phone', '77117711');
  const email = s('email', 'rw@gmail.com');

  const faqs = faqsData ?? [];
  const categories = useMemo(
    () => Array.from(new Set(faqs.map((f) => f.category))),
    [faqs]
  );
  const filteredFAQs = selectedCategory === ALL
    ? faqs
    : faqs.filter((f) => f.category === selectedCategory);

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <h1 className="text-3xl font-bold mb-4">Түгээмэл асуулт хариулт</h1>
      <p className="text-gray-600 mb-8">
        Танд хэрэгтэй хариултыг эндээс олж болно. Хэрэв танд өөр асуулт байвал бидэнтэй холбогдоно уу.
      </p>

      {categories.length > 0 && (
        <div className="mb-8">
          <div className="flex flex-wrap gap-2">
            <button
              onClick={() => setSelectedCategory(ALL)}
              className={`px-4 py-2 rounded-full text-sm font-medium transition-colors ${
                selectedCategory === ALL
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
              }`}
            >
              {ALL}
            </button>
            {categories.map((category) => (
              <button
                key={category}
                onClick={() => setSelectedCategory(category)}
                className={`px-4 py-2 rounded-full text-sm font-medium transition-colors ${
                  selectedCategory === category
                    ? 'bg-blue-600 text-white'
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                }`}
              >
                {category}
              </button>
            ))}
          </div>
        </div>
      )}

      {loading && (
        <div className="py-16 flex items-center justify-center text-gray-400">
          <Loader2 className="w-6 h-6 animate-spin mr-2" />
          <span className="text-sm">Ачаалж байна...</span>
        </div>
      )}

      {!loading && error && (
        <div className="py-12 text-center text-red-500 text-sm">
          Асуултуудыг ачаалж чадсангүй: {error}
        </div>
      )}

      {!loading && !error && filteredFAQs.length === 0 && (
        <div className="py-16 text-center text-gray-400 text-sm">
          Одоогоор асуулт хариулт нэмэгдээгүй байна.
        </div>
      )}

      <div className="space-y-3">
        {filteredFAQs.map((faq) => (
          <div
            key={faq.id}
            className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden"
          >
            <button
              onClick={() => setOpenId(openId === faq.id ? null : faq.id)}
              className="w-full px-6 py-4 flex items-center justify-between text-left hover:bg-gray-50 transition-colors"
            >
              <div className="flex-1">
                <span className="text-xs font-medium text-blue-600 mb-1 block">
                  {faq.category}
                </span>
                <span className="font-semibold text-gray-900">{faq.question}</span>
              </div>
              {openId === faq.id ? (
                <ChevronUp className="w-5 h-5 text-gray-500 flex-shrink-0 ml-4" />
              ) : (
                <ChevronDown className="w-5 h-5 text-gray-500 flex-shrink-0 ml-4" />
              )}
            </button>
            {openId === faq.id && (
              <div className="px-6 pb-4 text-gray-600 whitespace-pre-line">
                {faq.answer}
              </div>
            )}
          </div>
        ))}
      </div>

      <div className="mt-12 bg-blue-50 p-6 rounded-lg">
        <h2 className="font-bold text-lg mb-2">Хариулт олдсонгүй юу?</h2>
        <p className="text-gray-700 mb-4">
          Бидэнтэй холбогдоод асуултаа асуугаарай. Бид таньд хамгийн түргэн хугацаанд хариулах болно.
        </p>
        <div className="flex flex-wrap gap-4">
          <a
            href={`tel:${phone.replace(/\s/g, '')}`}
            className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            Утас: {phone}
          </a>
          <a
            href={`mailto:${email}`}
            className="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
          >
            И-мэйл: {email}
          </a>
        </div>
      </div>
    </div>
  );
};
