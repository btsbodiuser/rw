import React, { useEffect, useState, useCallback } from 'react';
import { Outlet, Link, useLocation } from 'react-router';
import { Header } from './Header';
import { PWAInstallPrompt } from './PWAInstallPrompt';
import { AppProvider } from '../context/AppContext';
import { Toaster } from 'sonner';
import { fetchSettings } from '../services/api';
import { useApi } from '../hooks/useApi';
import { ArrowUp } from 'lucide-react';

function ScrollToTop() {
  const { pathname } = useLocation();
  useEffect(() => {
    window.scrollTo(0, 0);
  }, [pathname]);
  return null;
}

function GoToTopButton() {
  const [visible, setVisible] = useState(false);

  const handleScroll = useCallback(() => {
    setVisible(window.scrollY > 300);
  }, []);

  useEffect(() => {
    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, [handleScroll]);

  if (!visible) return null;

  return (
    <button
      onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
      className="fixed bottom-6 right-6 z-50 bg-blue-600 hover:bg-blue-700 text-white p-3 rounded-full shadow-lg transition-all duration-300"
      aria-label="Дээш"
    >
      <ArrowUp className="w-5 h-5" />
    </button>
  );
}

function hexToHSL(hex: string): [number, number, number] {
  const r = parseInt(hex.slice(1, 3), 16) / 255;
  const g = parseInt(hex.slice(3, 5), 16) / 255;
  const b = parseInt(hex.slice(5, 7), 16) / 255;
  const max = Math.max(r, g, b), min = Math.min(r, g, b);
  let h = 0, s = 0;
  const l = (max + min) / 2;
  if (max !== min) {
    const d = max - min;
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    if (max === r) h = ((g - b) / d + (g < b ? 6 : 0)) / 6;
    else if (max === g) h = ((b - r) / d + 2) / 6;
    else h = ((r - g) / d + 4) / 6;
  }
  return [Math.round(h * 360), Math.round(s * 100), Math.round(l * 100)];
}

function hslToHex(h: number, s: number, l: number): string {
  s /= 100; l /= 100;
  const a = s * Math.min(l, 1 - l);
  const f = (n: number) => {
    const k = (n + h / 30) % 12;
    const color = l - a * Math.max(Math.min(k - 3, 9 - k, 1), -1);
    return Math.round(255 * Math.max(0, Math.min(1, color))).toString(16).padStart(2, '0');
  };
  return `#${f(0)}${f(8)}${f(4)}`;
}

function applyPrimaryColor(hex: string) {
  const [h, s] = hexToHSL(hex);
  const shades: Record<string, number> = {
    '50': 97, '100': 93, '200': 86, '300': 76, '400': 64,
    '500': 53, '600': 46, '700': 40, '800': 34, '900': 28, '950': 20,
  };
  const root = document.documentElement;
  for (const [shade, lightness] of Object.entries(shades)) {
    const sat = shade === '50' || shade === '100' ? Math.min(s + 10, 100) : s;
    root.style.setProperty(`--brand-${shade}`, hslToHex(h, sat, lightness));
  }
}

export const RootLayout = () => {
  const { data: settingsData } = useApi(() => fetchSettings(), []);

  const s = (key: string, fallback: string) => String(settingsData?.[key] ?? fallback);

  // Dynamic favicon + title + primary color
  useEffect(() => {
    if (!settingsData) return;
    const favicon = String(settingsData['site_favicon'] ?? '');
    if (favicon) {
      let link = document.querySelector("link[rel~='icon']") as HTMLLinkElement | null;
      if (!link) {
        link = document.createElement('link');
        link.rel = 'icon';
        document.head.appendChild(link);
      }
      link.href = `${import.meta.env.BASE_URL}backend/uploads/media/${favicon}`;
    }
    // Dynamic title
    const siteName = String(settingsData['site_name'] ?? '');
    if (siteName) {
      document.title = siteName;
    }
    // Dynamic primary color
    const hex = String(settingsData['site_primary_color'] ?? '');
    if (hex && /^#[0-9a-fA-F]{6}$/.test(hex)) {
      applyPrimaryColor(hex);
    }
  }, [settingsData]);

  return (
    <AppProvider>
      <div className="min-h-screen bg-gray-50 flex flex-col">
        <ScrollToTop />
        <Toaster position="top-right" richColors closeButton />
        <Header />
        <PWAInstallPrompt siteNameMn={s('site_name_mn', s('site_name', 'Runners World'))} siteLogo={settingsData?.['site_favicon'] ? `${import.meta.env.BASE_URL}backend/uploads/media/${settingsData['site_favicon']}` : undefined} />
        <main className="flex-1">
          <Outlet />
        </main>
        <footer className="bg-gray-900 text-white py-12">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
              <div>
                <h3 className="font-bold text-xl mb-4">{s('site_name', 'Runners World')}</h3>
                <p className="text-gray-400 text-sm">
                  {s('site_slogan', 'Дэлгүүр')} — {s('hero_subtitle', 'Танай гэрт хүргэнэ')}
                </p>
                <p className="text-gray-400 text-sm">{s('address', '')}</p>
                <Link to="/order-tracking" className="inline-block mt-3 text-sm bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-full transition-colors">
                  Track Order
                </Link>
              </div>
              <div>
                <h4 className="font-semibold mb-4">Үйлчилгээ</h4>
                <ul className="space-y-2 text-sm text-gray-400">
                  <li>
                    <Link to="/contact" className="hover:text-white transition-colors">
                      Холбоо барих
                    </Link>
                  </li>
                  <li>
                    <Link to="/faq" className="hover:text-white transition-colors">
                      Түгээмэл асуулт
                    </Link>
                  </li>
                </ul>
              </div>
              <div>
                <h4 className="font-semibold mb-4">Холбоо барих</h4>
                <ul className="space-y-2 text-sm text-gray-400">
                  <li>И-мэйл: {s('email', 'info@runnersworld.mn')}</li>
                  <li>
                    Утас: <a href={`tel:${s('phone', '7711-1234').replace(/[\s\-+]/g, '')}`} className="hover:text-white transition-colors">{s('phone', '7711-1234')}</a>
                  </li>
                  <li>Мя-Ба: 10:00 - 19:00</li>
                  <li>Бя: 11:00 - 19:00</li>
                </ul>
              </div>
            </div>
            <div className="mt-8 pt-8 border-t border-gray-800 text-center text-sm text-gray-400">
              © {new Date().getFullYear()} {s('site_name', 'Runners World')} {s('site_slogan', 'Дэлгүүр')}. Бүх эрх хуулиар хамгаалагдсан.
            </div>
          </div>
        </footer>
        <GoToTopButton />
      </div>
    </AppProvider>
  );
};