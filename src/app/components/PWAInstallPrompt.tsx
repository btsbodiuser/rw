import React, { useState, useEffect, useCallback } from 'react';

interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

const DISMISS_KEY = 'pwa_install_dismissed';
const DISMISS_DURATION = 3 * 24 * 60 * 60 * 1000; // 3 days

interface PWAInstallPromptProps {
  siteNameMn?: string;
  siteLogo?: string;
}

export const PWAInstallPrompt: React.FC<PWAInstallPromptProps> = ({ siteNameMn = 'Guzeelzgene', siteLogo }) => {
  const [deferredPrompt, setDeferredPrompt] = useState<BeforeInstallPromptEvent | null>(null);
  const [showBanner, setShowBanner] = useState(false);
  const [isIOS, setIsIOS] = useState(false);
  const [showIOSGuide, setShowIOSGuide] = useState(false);

  useEffect(() => {
    // Check if already installed
    if (window.matchMedia('(display-mode: standalone)').matches) return;
    if ((navigator as any).standalone) return;

    // Check if user dismissed recently
    const dismissed = localStorage.getItem(DISMISS_KEY);
    if (dismissed && Date.now() - parseInt(dismissed) < DISMISS_DURATION) return;

    // Detect iOS/iPadOS (iPadOS 13+ reports 'Macintosh' user-agent)
    const isIOSDevice =
      (/iPad|iPhone|iPod/.test(navigator.userAgent) && !(window as any).MSStream) ||
      (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    // On iOS, only Safari supports "Add to Home Screen" — Chrome/Firefox use a
    // different share sheet that doesn't include that option.
    const isInSafari = isIOSDevice && /^((?!CriOS|FxiOS|OPiOS|mercury).)*Safari/.test(navigator.userAgent);

    setIsIOS(isIOSDevice);

    if (isIOSDevice) {
      // Only show the guide if they're in Safari; other browsers can't install
      if (!isInSafari) return;
      // Show after 5 seconds on iOS
      const timer = setTimeout(() => setShowBanner(true), 5000);
      return () => clearTimeout(timer);
    }

    // Android/Desktop — listen for beforeinstallprompt
    const handler = (e: Event) => {
      e.preventDefault();
      setDeferredPrompt(e as BeforeInstallPromptEvent);
      // Show after 3 seconds
      setTimeout(() => setShowBanner(true), 3000);
    };

    window.addEventListener('beforeinstallprompt', handler);
    return () => window.removeEventListener('beforeinstallprompt', handler);
  }, []);

  const handleInstall = useCallback(async () => {
    if (isIOS) {
      setShowIOSGuide(true);
      return;
    }

    if (!deferredPrompt) return;

    await deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;

    if (outcome === 'accepted') {
      setShowBanner(false);
    }
    setDeferredPrompt(null);
  }, [deferredPrompt, isIOS]);

  const handleDismiss = useCallback(() => {
    setShowBanner(false);
    setShowIOSGuide(false);
    localStorage.setItem(DISMISS_KEY, Date.now().toString());
  }, []);

  if (!showBanner) return null;

  // iOS Safari guide modal
  if (showIOSGuide) {
    return (
      <div className="fixed inset-0 z-[9999] flex items-end justify-center bg-black/50 animate-in fade-in"
           onClick={handleDismiss}>
        <div className="bg-white rounded-t-2xl w-full max-w-md p-6 pb-8 animate-in slide-in-from-bottom"
             onClick={(e) => e.stopPropagation()}>
          <div className="flex justify-between items-center mb-4">
            <h3 className="text-lg font-bold text-gray-900">Апп суулгах заавар</h3>
            <button onClick={handleDismiss} className="text-gray-400 hover:text-gray-600 p-1">
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div className="space-y-4">
            <div className="flex items-start gap-3">
              <span className="flex-shrink-0 w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-bold text-sm">1</span>
              <p className="text-sm text-gray-700 pt-1">
                Доод хэсгийн <span className="inline-flex items-center"><svg className="w-5 h-5 text-blue-500 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg></span> товчийг дарна
              </p>
            </div>
            <div className="flex items-start gap-3">
              <span className="flex-shrink-0 w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-bold text-sm">2</span>
              <p className="text-sm text-gray-700 pt-1">"Нүүр дэлгэц дээр нэмэх" <span className="text-xs text-gray-500">(Add to Home Screen)</span> сонгоно</p>
            </div>
            <div className="flex items-start gap-3">
              <span className="flex-shrink-0 w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-bold text-sm">3</span>
              <p className="text-sm text-gray-700 pt-1">"Нэмэх" дарж суулгана</p>
            </div>
          </div>
        </div>
      </div>
    );
  }

  // Bottom banner
  return (
    <div className="fixed bottom-0 left-0 right-0 z-[9998] p-3 sm:p-4 animate-in slide-in-from-bottom duration-500">
      <div className="max-w-md mx-auto bg-white rounded-2xl shadow-2xl border border-gray-100 p-4 flex items-center gap-3">
        <div className="flex-shrink-0 w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center overflow-hidden">
          {siteLogo ? (
            <img src={siteLogo} alt={siteNameMn} className="w-full h-full object-cover" />
          ) : (
            <span className="text-white font-bold text-xl">{siteNameMn.charAt(0)}</span>
          )}
        </div>
        <div className="flex-1 min-w-0">
          <p className="text-sm font-semibold text-gray-900">{siteNameMn} апп суулгах</p>
          <p className="text-xs text-gray-500 mt-0.5">Илүү хурдан, илүү хялбар</p>
        </div>
        <div className="flex items-center gap-2 flex-shrink-0">
          <button
            onClick={handleDismiss}
            className="text-xs text-gray-400 hover:text-gray-600 px-2 py-1"
          >
            Дараа
          </button>
          <button
            onClick={handleInstall}
            className="px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 active:scale-95 transition-all"
          >
            Суулгах
          </button>
        </div>
      </div>
    </div>
  );
};
