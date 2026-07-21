import React, { useState, useEffect, useRef, useCallback } from 'react';
import { useNavigate, useSearchParams } from 'react-router';
import { Phone, KeyRound, UserPlus, LogIn, ArrowLeft, Loader2, Mail } from 'lucide-react';
import { useApp } from '../context/AppContext';
import { authCheckPhone, authSendOtp, authVerifyOtp, authRegister, authRegisterDirect, authResetPassword, authSocialLogin, fetchSettings, authSendEmailOtp, authVerifyEmailOtp, authRegisterEmail, authResetPasswordEmail } from '../services/api';
import { toast } from 'sonner';

type Step = 'phone' | 'otp' | 'password' | 'set-password' | 'social-phone' | 'social-otp' | 'register-choose' | 'direct-register' | 'email' | 'email-otp' | 'email-password' | 'email-set-password' | 'email-register-choose' | 'email-direct-register';

export const LoginPage = () => {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const redirect = searchParams.get('redirect') || '/';
  const { isAuthenticated, login, loginWithToken } = useApp();

  const [step, setStep] = useState<Step>('phone');
  const [phone, setPhone] = useState('');
  const [email, setEmail] = useState('');
  const [isNewUser, setIsNewUser] = useState(false);
  const [isForgotPassword, setIsForgotPassword] = useState(false);
  const [otp, setOtp] = useState(['', '', '', '']);
  const [otpToken, setOtpToken] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [name, setName] = useState('');
  const [loading, setLoading] = useState(false);
  const [countdown, setCountdown] = useState(0);
  const otpRefs = useRef<(HTMLInputElement | null)[]>([]);

  // Social login state
  const [socialProvider, setSocialProvider] = useState<'google' | 'facebook' | null>(null);
  const [socialToken, setSocialToken] = useState('');
  const [socialProfile, setSocialProfile] = useState<{ name: string; email: string | null; avatar: string | null } | null>(null);

  // Login method settings
  const [loginSettings, setLoginSettings] = useState({
    login_phone_password_enabled: false,
    login_phone_otp_enabled: false,
    login_register_without_otp_enabled: false,
    login_email_enabled: false,
    login_email_password_enabled: false,
    login_email_otp_enabled: false,
    login_email_register_without_otp_enabled: false,
    login_google_enabled: false,
    login_facebook_enabled: false,
    google_client_id: '',
    facebook_app_id: '',
  });
  const [settingsLoaded, setSettingsLoaded] = useState(false);

  // Load login settings
  useEffect(() => {
    fetchSettings().then((s) => {
      const emailEnabled = s.login_email_enabled === true;
      const registerEmailEnabled = s.register_email_enabled === true;
      const newSettings = {
        login_phone_password_enabled: s.login_phone_password_enabled === true,
        login_phone_otp_enabled: s.login_phone_otp_enabled === true,
        login_register_without_otp_enabled: s.login_register_without_otp_enabled === true,
        login_email_enabled: emailEnabled,
        login_email_password_enabled: emailEnabled,
        login_email_otp_enabled: emailEnabled,
        login_email_register_without_otp_enabled: registerEmailEnabled,
        login_google_enabled: s.login_google_enabled === true,
        login_facebook_enabled: s.login_facebook_enabled === true,
        google_client_id: String(s.google_client_id || ''),
        facebook_app_id: String(s.facebook_app_id || ''),
      };
      setLoginSettings(newSettings);

      // Set initial step based on enabled methods
      const hasPhoneLogin = newSettings.login_phone_password_enabled || newSettings.login_phone_otp_enabled || newSettings.login_register_without_otp_enabled;
      const hasEmailLogin = newSettings.login_email_enabled || newSettings.login_email_register_without_otp_enabled;
      if (!hasPhoneLogin && hasEmailLogin) {
        setStep('email');
      } else {
        setStep('phone');
      }

      setSettingsLoaded(true);
    }).catch(() => setSettingsLoaded(true));
  }, []);

  // Load Google Identity Services SDK
  useEffect(() => {
    if (!loginSettings.login_google_enabled || !loginSettings.google_client_id) return;
    if (document.getElementById('google-gsi-script')) return;
    const script = document.createElement('script');
    script.id = 'google-gsi-script';
    script.src = 'https://accounts.google.com/gsi/client';
    script.async = true;
    document.head.appendChild(script);
  }, [loginSettings.login_google_enabled, loginSettings.google_client_id]);

  // Load Facebook SDK
  useEffect(() => {
    if (!loginSettings.login_facebook_enabled || !loginSettings.facebook_app_id) return;
    if (document.getElementById('facebook-jssdk')) return;
    (window as any).fbAsyncInit = function () {
      (window as any).FB.init({ appId: loginSettings.facebook_app_id, cookie: true, xfbml: false, version: 'v19.0' });
    };
    const script = document.createElement('script');
    script.id = 'facebook-jssdk';
    script.src = 'https://connect.facebook.net/en_US/sdk.js';
    script.async = true;
    document.head.appendChild(script);
  }, [loginSettings.login_facebook_enabled, loginSettings.facebook_app_id]);

  // Redirect if already logged in
  useEffect(() => {
    if (isAuthenticated) navigate(redirect, { replace: true });
  }, [isAuthenticated, navigate, redirect]);

  // OTP countdown timer
  useEffect(() => {
    if (countdown <= 0) return;
    const timer = setTimeout(() => setCountdown(countdown - 1), 1000);
    return () => clearTimeout(timer);
  }, [countdown]);

  const hasPhoneLogin =
    loginSettings.login_phone_password_enabled ||
    loginSettings.login_phone_otp_enabled ||
    loginSettings.login_register_without_otp_enabled;

  // ── Social Login Handlers ──
  const handleGoogleLogin = useCallback(() => {
    const google = (window as any).google;
    if (!google?.accounts?.id) {
      toast.error('Google SDK ачааллаагүй байна');
      return;
    }
    google.accounts.id.initialize({
      client_id: loginSettings.google_client_id,
      callback: async (response: { credential: string }) => {
        setLoading(true);
        try {
          const result = await authSocialLogin('google', response.credential);
          if (result.needs_phone && result.social_profile) {
            setSocialProvider('google');
            setSocialToken(response.credential);
            setSocialProfile(result.social_profile);
            setName(result.social_profile.name || '');
            setStep('social-phone');
          } else if (result.token && result.user) {
            loginWithToken(result.token, result.user);
            toast.success('Амжилттай нэвтэрлээ');
            navigate(redirect, { replace: true });
          }
        } catch (err: any) {
          toast.error(err.message || 'Google нэвтрэх алдаа');
        } finally {
          setLoading(false);
        }
      },
    });
    google.accounts.id.prompt((notification: any) => {
      if (notification.isNotDisplayed() || notification.isSkippedMoment()) {
        // Fallback: use button click flow
        google.accounts.id.renderButton(
          document.getElementById('google-signin-btn'),
          { theme: 'outline', size: 'large', width: '100%', text: 'signin_with' }
        );
        (document.getElementById('google-signin-btn')?.querySelector('div[role="button"]') as HTMLElement)?.click();
      }
    });
  }, [loginSettings.google_client_id, loginWithToken, navigate, redirect]);

  const handleFacebookLogin = useCallback(() => {
    const FB = (window as any).FB;
    if (!FB) {
      toast.error('Facebook SDK ачааллаагүй байна');
      return;
    }
    FB.login(async (response: any) => {
      if (response.authResponse) {
        setLoading(true);
        try {
          const result = await authSocialLogin('facebook', response.authResponse.accessToken);
          if (result.needs_phone && result.social_profile) {
            setSocialProvider('facebook');
            setSocialToken(response.authResponse.accessToken);
            setSocialProfile(result.social_profile);
            setName(result.social_profile.name || '');
            setStep('social-phone');
          } else if (result.token && result.user) {
            loginWithToken(result.token, result.user);
            toast.success('Амжилттай нэвтэрлээ');
            navigate(redirect, { replace: true });
          }
        } catch (err: any) {
          toast.error(err.message || 'Facebook нэвтрэх алдаа');
        } finally {
          setLoading(false);
        }
      }
    }, { scope: 'email,public_profile' });
  }, [loginWithToken, navigate, redirect]);

  // Social flow: send OTP for phone verification
  const handleSocialPhoneSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const cleanPhone = phone.replace(/\D/g, '');
    if (cleanPhone.length !== 8) {
      toast.error('8 оронтой утасны дугаар оруулна уу');
      return;
    }
    setLoading(true);
    try {
      await authSendOtp(cleanPhone);
      toast.success('Баталгаажуулах код илгээлээ');
      setStep('social-otp');
      setCountdown(180);
      setTimeout(() => otpRefs.current[0]?.focus(), 100);
    } catch (err: any) {
      toast.error(err.message || 'Алдаа гарлаа');
    } finally {
      setLoading(false);
    }
  };

  // Social flow: verify OTP then complete social login
  const handleSocialOtpSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const code = otp.join('');
    if (code.length !== 4) {
      toast.error('4 оронтой код оруулна уу');
      return;
    }
    setLoading(true);
    try {
      const otpResult = await authVerifyOtp(phone.replace(/\D/g, ''), code);
      // Now complete social login with phone + otp_token
      const result = await authSocialLogin(socialProvider!, socialToken, phone.replace(/\D/g, ''), otpResult.otp_token);
      if (result.token && result.user) {
        loginWithToken(result.token, result.user);
        toast.success('Амжилттай нэвтэрлээ');
        navigate(redirect, { replace: true });
      }
    } catch (err: any) {
      toast.error(err.message || 'Алдаа гарлаа');
    } finally {
      setLoading(false);
    }
  };

  const handleEmailSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      toast.error('Зөв имэйл хаяг оруулна уу');
      return;
    }
    if (
      !loginSettings.login_email_enabled &&
      !loginSettings.login_email_register_without_otp_enabled
    ) {
      toast.error('Имэйлээр нэвтрэх боломжгүй байна. Утас эсвэл нийгмийн сүлжээгээр нэвтэрнэ үү.');
      return;
    }
    const passwordLoginAllowed =
      loginSettings.login_email_password_enabled ||
      loginSettings.login_email_register_without_otp_enabled;

    setLoading(true);
    try {
      // For now, we'll assume email exists if we can send OTP, otherwise it's new user
      // This is a simplified approach - in production you might want a separate check endpoint
      setIsNewUser(false); // We'll determine this during OTP verification

      // Forgot password (existing user) → must use OTP; direct register cannot
      // reset since there's no email-ownership proof.
      if (isForgotPassword) {
        if (!loginSettings.login_email_otp_enabled) {
          toast.error('Нууц үг сэргээх боломжгүй байна. Админтай холбогдоно уу.');
          return;
        }
        await authSendEmailOtp(email, 'reset');
        toast.success('Баталгаажуулах код имэйлээр илгээлээ');
        setStep('email-otp');
        setCountdown(180);
        setTimeout(() => otpRefs.current[0]?.focus(), 100);
        return;
      }

      // For email, we need to try sending OTP first to check if user exists
      if (loginSettings.login_email_otp_enabled) {
        await authSendEmailOtp(email, 'register'); // This will fail if user exists and we're not resetting
        toast.success('Баталгаажуулах код имэйлээр илгээлээ');
        setStep('email-otp');
        setCountdown(180);
        setTimeout(() => otpRefs.current[0]?.focus(), 100);
        return;
      }

      // If OTP is not enabled, go to password login (assuming user exists)
      if (passwordLoginAllowed) {
        toast.info('Нууц үгээр нэвтрэх боломжтой. Нууц үгээ оруулна уу.');
        setStep('email-password');
        return;
      }

      toast.error('Имэйлээр нэвтрэх боломжгүй байна.');
    } catch (err: any) {
      // Only fallback to password login when the email is already registered.
      if (
        passwordLoginAllowed &&
        !isForgotPassword &&
        err.message === 'Email already registered'
      ) {
        toast.info('Имэйл бүртгэгдсэн байна. Нууц үгээ оруулна уу.');
        setStep('email-password');
        return;
      }
      toast.error(err.message || 'Алдаа гарлаа');
    } finally {
      setLoading(false);
    }
  };

  const handlePhoneSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const cleanPhone = phone.replace(/\D/g, '');
    if (cleanPhone.length !== 8) {
      toast.error('8 оронтой утасны дугаар оруулна уу');
      return;
    }
    if (
      !loginSettings.login_phone_password_enabled &&
      !loginSettings.login_phone_otp_enabled &&
      !loginSettings.login_register_without_otp_enabled
    ) {
      toast.error('Утсаар нэвтрэх боломжгүй байна. Нийгмийн сүлжээгээр нэвтэрнэ үү.');
      return;
    }
    setLoading(true);
    try {
      const { exists, has_password } = await authCheckPhone(cleanPhone);
      setIsNewUser(!exists);

      // Password login is allowed if the explicit toggle is on, OR if direct
      // register is on (because direct register creates phone+password accounts
      // and login.php has no per-account flag — turning password login off would
      // strand those accounts with no way to log in).
      const passwordLoginAllowed =
        loginSettings.login_phone_password_enabled ||
        loginSettings.login_register_without_otp_enabled;

      // Forgot password (existing user) → must use OTP; direct register cannot
      // reset since there's no phone-ownership proof.
      if (isForgotPassword) {
        if (!exists) {
          toast.error('Энэ дугаар бүртгэлгүй байна.');
          return;
        }
        if (!loginSettings.login_phone_otp_enabled) {
          toast.error('Нууц үг сэргээх боломжгүй байна. Админтай холбогдоно уу.');
          return;
        }
        await authSendOtp(cleanPhone);
        toast.success('Баталгаажуулах код илгээлээ');
        setStep('otp');
        setCountdown(180);
        setTimeout(() => otpRefs.current[0]?.focus(), 100);
        return;
      }

      // Existing user with password → password login (no OTP needed)
      if (exists && has_password && passwordLoginAllowed) {
        setStep('password');
        return;
      }

      // Existing user without password → must use OTP to set one
      if (exists && !has_password) {
        if (!loginSettings.login_phone_otp_enabled) {
          toast.error('Энэ дугаараар нэвтрэх боломжгүй байна.');
          return;
        }
        await authSendOtp(cleanPhone);
        toast.success('Баталгаажуулах код илгээлээ');
        setStep('otp');
        setCountdown(180);
        setTimeout(() => otpRefs.current[0]?.focus(), 100);
        return;
      }

      // Existing user has password, but no login method is available for them.
      // Never fall through to the "new user" registration path — the phone is
      // already registered and offering registration would be misleading.
      if (exists) {
        toast.error('Энэ дугаараар нэвтрэх боломжгүй байна. Админтай холбогдоно уу.');
        return;
      }

      // New user (registration)
      const otpOn = loginSettings.login_phone_otp_enabled;
      const directOn = loginSettings.login_register_without_otp_enabled;
      if (otpOn && directOn) {
        // Both methods available → let user choose
        setStep('register-choose');
        return;
      }
      if (directOn) {
        setStep('direct-register');
        return;
      }
      if (otpOn) {
        await authSendOtp(cleanPhone);
        toast.success('Баталгаажуулах код илгээлээ');
        setStep('otp');
        setCountdown(180);
        setTimeout(() => otpRefs.current[0]?.focus(), 100);
        return;
      }
      toast.error('Шинэ хэрэглэгч бүртгүүлэх боломжгүй байна.');
    } catch (err: any) {
      toast.error(err.message || 'Алдаа гарлаа');
    } finally {
      setLoading(false);
    }
  };

  // Choose register method: SMS OTP path
  const handleChooseOtp = async () => {
    const cleanPhone = phone.replace(/\D/g, '');
    setLoading(true);
    try {
      await authSendOtp(cleanPhone);
      toast.success('Баталгаажуулах код илгээлээ');
      setStep('otp');
      setCountdown(180);
      setTimeout(() => otpRefs.current[0]?.focus(), 100);
    } catch (err: any) {
      toast.error(err.message || 'Алдаа гарлаа');
    } finally {
      setLoading(false);
    }
  };

  // Direct register submit (no OTP)
  const handleEmailDirectRegister = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) {
      toast.error('Нэрээ оруулна уу');
      return;
    }
    if (!phone.trim()) {
      toast.error('Утасны дугаар оруулна уу');
      return;
    }
    if (password.length < 6) {
      toast.error('Нууц үг хамгийн багадаа 6 тэмдэгт');
      return;
    }
    if (password !== confirmPassword) {
      toast.error('Нууц үг таарахгүй байна');
      return;
    }
    setLoading(true);
    try {
      const result = await authRegisterEmail(email, name.trim(), phone.replace(/\D/g, ''), password);
      loginWithToken(result.token, result.user);
      toast.success('Бүртгэл амжилттай');
      navigate(redirect, { replace: true });
    } catch (err: any) {
      toast.error(err.message || 'Алдаа гарлаа');
    } finally {
      setLoading(false);
    }
  };

  const handleDirectRegister = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) {
      toast.error('Нэрээ оруулна уу');
      return;
    }
    if (password.length < 6) {
      toast.error('Нууц үг хамгийн багадаа 6 тэмдэгт');
      return;
    }
    if (password !== confirmPassword) {
      toast.error('Нууц үг таарахгүй байна');
      return;
    }
    setLoading(true);
    try {
      const cleanPhone = phone.replace(/\D/g, '');
      const result = await authRegisterDirect(cleanPhone, password, name.trim());
      loginWithToken(result.token, result.user);
      toast.success('Бүртгэл амжилттай');
      navigate(redirect, { replace: true });
    } catch (err: any) {
      toast.error(err.message || 'Алдаа гарлаа');
    } finally {
      setLoading(false);
    }
  };

  const handleOtpChange = (index: number, value: string) => {
    if (!/^\d*$/.test(value)) return;
    const newOtp = [...otp];
    newOtp[index] = value.slice(-1);
    setOtp(newOtp);
    if (value && index < 3) {
      otpRefs.current[index + 1]?.focus();
    }
  };

  const handleOtpKeyDown = (index: number, e: React.KeyboardEvent) => {
    if (e.key === 'Backspace' && !otp[index] && index > 0) {
      otpRefs.current[index - 1]?.focus();
    }
  };

  const handleEmailOtpSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const code = otp.join('');
    if (code.length !== 4) {
      toast.error('4 оронтой код оруулна уу');
      return;
    }
    if (isForgotPassword) {
      // OTP stays in DB — reset-password-email.php verifies and consumes it
      setIsNewUser(false);
      setStep('email-set-password');
      toast.success('Код баталгаажлаа');
      return;
    }

    setLoading(true);
    try {
      await authVerifyEmailOtp(email, code);
      setIsNewUser(true);
      setStep('email-set-password');
      toast.success('Код баталгаажлаа');
    } catch (err: any) {
      toast.error(err.message || 'Код буруу эсвэл хугацаа дууссан');
    } finally {
      setLoading(false);
    }
  };

  const handleOtpSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const code = otp.join('');
    if (code.length !== 4) {
      toast.error('4 оронтой код оруулна уу');
      return;
    }
    setLoading(true);
    try {
      const result = await authVerifyOtp(phone.replace(/\D/g, ''), code);
      setOtpToken(result.otp_token);
      setStep('set-password');
      toast.success('Код баталгаажлаа');
    } catch (err: any) {
      toast.error(err.message || 'Код буруу эсвэл хугацаа дууссан');
    } finally {
      setLoading(false);
    }
  };

  const handleEmailPasswordLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!password) return;
    setLoading(true);
    try {
      const success = await login(email, password);
      if (success) {
        toast.success('Амжилттай нэвтэрлээ');
        navigate(redirect, { replace: true });
      } else {
        toast.error('Нууц үг буруу');
      }
    } catch {
      toast.error('Нууц үг буруу');
    } finally {
      setLoading(false);
    }
  };

  const handlePasswordLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!password) return;
    setLoading(true);
    try {
      const success = await login(phone.replace(/\D/g, ''), password);
      if (success) {
        toast.success('Амжилттай нэвтэрлээ');
        navigate(redirect, { replace: true });
      } else {
        toast.error('Нууц үг буруу');
      }
    } catch {
      toast.error('Нууц үг буруу');
    } finally {
      setLoading(false);
    }
  };

  const handleEmailSetPassword = async (e: React.FormEvent) => {
    e.preventDefault();
    if (isNewUser && !name.trim()) {
      toast.error('Нэрээ оруулна уу');
      return;
    }
    if (password.length < 6) {
      toast.error('Нууц үг хамгийн багадаа 6 тэмдэгт');
      return;
    }
    if (password !== confirmPassword) {
      toast.error('Нууц үг таарахгүй байна');
      return;
    }
    setLoading(true);
    try {
      if (isNewUser) {
        // For new user, we need phone as well
        if (!phone.trim()) {
          toast.error('Утасны дугаар оруулна уу');
          return;
        }
        const result = await authRegisterEmail(email, name.trim(), phone.replace(/\D/g, ''), password);
        loginWithToken(result.token, result.user);
        toast.success('Бүртгэл амжилттай');
      } else {
        // Reset password
        const result = await authResetPasswordEmail(email, otp.join(''), password);
        toast.success('Нууц үг шинэчлэгдлээ');
        // After password reset, go back to email login
        setStep('email');
        setPassword('');
        setConfirmPassword('');
        setOtp(['', '', '', '']);
      }
      navigate(redirect, { replace: true });
    } catch (err: any) {
      toast.error(err.message || 'Алдаа гарлаа');
    } finally {
      setLoading(false);
    }
  };

  const handleSetPassword = async (e: React.FormEvent) => {
    e.preventDefault();
    if (isNewUser && !name.trim()) {
      toast.error('Нэрээ оруулна уу');
      return;
    }
    if (password.length < 6) {
      toast.error('Нууц үг хамгийн багадаа 6 тэмдэгт');
      return;
    }
    if (password !== confirmPassword) {
      toast.error('Нууц үг таарахгүй байна');
      return;
    }
    setLoading(true);
    try {
      const cleanPhone = phone.replace(/\D/g, '');
      let result;
      if (isNewUser) {
        result = await authRegister(cleanPhone, otpToken, password, name);
      } else {
        result = await authResetPassword(cleanPhone, otpToken, password);
      }
      loginWithToken(result.token, result.user);
      setIsForgotPassword(false);
      toast.success(isNewUser ? 'Бүртгэл амжилттай' : 'Нууц үг шинэчлэгдлээ');
      navigate(redirect, { replace: true });
    } catch (err: any) {
      toast.error(err.message || 'Алдаа гарлаа');
    } finally {
      setLoading(false);
    }
  };

  const handleResendOtp = async () => {
    if (countdown > 0) return;
    setLoading(true);
    try {
      if (step === 'email-otp') {
        await authSendEmailOtp(email, isForgotPassword ? 'reset' : 'register');
      } else {
        await authSendOtp(phone.replace(/\D/g, ''));
      }
      setCountdown(180);
      setOtp(['', '', '', '']);
      toast.success('Код дахин илгээлээ');
    } catch (err: any) {
      toast.error(err.message || 'Алдаа гарлаа');
    } finally {
      setLoading(false);
    }
  };

  const handleForgotPassword = () => {
    setIsForgotPassword(true);
    setStep('phone');
  };

  const goBack = () => {
    if (step === 'otp') {
      setStep('phone');
      setOtp(['', '', '', '']);
      setCountdown(0);
      setIsForgotPassword(false);
    }
    else if (step === 'password') {
      setStep('phone');
      setPassword('');
      setIsForgotPassword(false);
    }
    else if (step === 'set-password') {
      setStep('otp');
      setPassword('');
      setConfirmPassword('');
      setName('');
    }
    else if (step === 'register-choose') {
      setStep('phone');
    }
    else if (step === 'direct-register') {
      setStep(loginSettings.login_phone_otp_enabled ? 'register-choose' : 'phone');
      setPassword('');
      setConfirmPassword('');
      setName('');
    }
    else if (step === 'social-phone') {
      setStep('phone');
      setSocialProvider(null);
      setSocialToken('');
      setSocialProfile(null);
      setName('');
    }
    else if (step === 'social-otp') {
      setStep('social-phone');
      setOtp(['', '', '', '']);
      setCountdown(0);
    }
    else if (step === 'email') {
      if (hasPhoneLogin) {
        setStep('phone');
      }
      setEmail('');
      setIsForgotPassword(false);
    }
    else if (step === 'email-otp') {
      setStep('email');
      setOtp(['', '', '', '']);
      setCountdown(0);
      setIsForgotPassword(false);
    }
    else if (step === 'email-password') {
      setStep('email');
      setPassword('');
      setIsForgotPassword(false);
    }
    else if (step === 'email-set-password') {
      setStep('email-otp');
      setPassword('');
      setConfirmPassword('');
      setName('');
      setPhone('');
    }
    else if (step === 'email-register-choose') {
      setStep('email');
    }
    else if (step === 'email-direct-register') {
      setStep(loginSettings.login_email_otp_enabled ? 'email-register-choose' : 'email');
      setPassword('');
      setConfirmPassword('');
      setName('');
      setPhone('');
    }
    else navigate(-1);
  };

  const formatCountdown = (s: number) => `${Math.floor(s / 60)}:${(s % 60).toString().padStart(2, '0')}`;

  return (
    <div className="min-h-[80vh] flex items-center justify-center px-4">
      <div className="w-full max-w-md">
        <div className="bg-white rounded-2xl shadow-lg border border-gray-100 p-8">
          {/* Header */}
          <div className="text-center mb-8">
            {socialProfile?.avatar && (step === 'social-phone' || step === 'social-otp') ? (
              <img src={socialProfile.avatar} alt="" className="w-16 h-16 rounded-full mx-auto mb-4 border-2 border-blue-100" />
            ) : (
              <div className="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                {(step === 'phone' || step === 'email') && <Phone className="w-8 h-8 text-blue-600" />}
                {(step === 'otp' || step === 'email-otp') && <KeyRound className="w-8 h-8 text-blue-600" />}
                {step === 'password' && <LogIn className="w-8 h-8 text-blue-600" />}
                {step === 'email-password' && <LogIn className="w-8 h-8 text-blue-600" />}
                {(step === 'set-password' || step === 'email-set-password' || step === 'direct-register' || step === 'email-direct-register' || step === 'register-choose' || step === 'email-register-choose') && <UserPlus className="w-8 h-8 text-blue-600" />}
              </div>
            )}
            <h1 className="text-2xl font-bold text-gray-900">
              {step === 'phone' && (isForgotPassword ? 'Нууц үг сэргээх' : 'Нэвтрэх / Бүртгүүлэх')}
              {step === 'email' && (isForgotPassword ? 'Нууц үг сэргээх' : 'Нэвтрэх / Бүртгүүлэх')}
              {step === 'otp' && 'Баталгаажуулах код'}
              {step === 'email-otp' && 'Баталгаажуулах код'}
              {step === 'password' && 'Нэвтрэх'}
              {step === 'email-password' && 'Нэвтрэх'}
              {step === 'set-password' && (isNewUser ? 'Бүртгүүлэх' : 'Шинэ нууц үг')}
              {step === 'email-set-password' && (isNewUser ? 'Бүртгүүлэх' : 'Шинэ нууц үг')}
              {step === 'register-choose' && 'Бүртгүүлэх арга'}
              {step === 'email-register-choose' && 'Бүртгүүлэх арга'}
              {step === 'direct-register' && 'Бүртгүүлэх'}
              {step === 'email-direct-register' && 'Бүртгүүлэх'}
              {step === 'social-phone' && 'Утасны дугаар баталгаажуулах'}
              {step === 'social-otp' && 'Баталгаажуулах код'}
            </h1>
            <p className="text-gray-500 text-sm mt-2">
              {step === 'phone' && 'Утасны дугаараа оруулна уу'}
              {step === 'email' && 'Имэйл хаягаа оруулна уу'}
              {step === 'otp' && `${phone} дугаарт илгээсэн 4 оронтой код`}
              {step === 'email-otp' && `${email} хаягт илгээсэн 4 оронтой код`}
              {step === 'password' && `${phone} дугаарын нууц үгээ оруулна уу`}
              {step === 'email-password' && `${email} хаягийн нууц үгээ оруулна уу`}
              {step === 'set-password' && (isNewUser ? 'Нэр, нууц үгээ оруулна уу' : 'Шинэ нууц үгээ оруулна уу')}
              {step === 'email-set-password' && (isNewUser ? 'Нэр, утас, нууц үгээ оруулна уу' : 'Шинэ нууц үгээ оруулна уу')}
              {step === 'register-choose' && `${phone} — бүртгүүлэх аргаа сонгоно уу`}
              {step === 'email-register-choose' && `${email} — бүртгүүлэх аргаа сонгоно уу`}
              {step === 'direct-register' && `${phone} — нэр, нууц үгээ оруулна уу`}
              {step === 'email-direct-register' && `${email} — нэр, утас, нууц үгээ оруулна уу`}
              {step === 'social-phone' && `${socialProfile?.name ? socialProfile.name + ', у' : 'У'}тасны дугаараа оруулна уу`}
              {step === 'social-otp' && `${phone} дугаарт илгээсэн 4 оронтой код`}
            </p>
          </div>

          {/* Step: Phone */}
          {step === 'phone' && (
            <form onSubmit={handlePhoneSubmit} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Утасны дугаар</label>
                <input
                  type="tel"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="99112233"
                  maxLength={8}
                  autoFocus
                  className="w-full px-4 py-3 border border-gray-300 rounded-xl text-lg text-center tracking-widest focus:ring-2 focus:ring-blue-500 outline-none"
                />
              </div>
              <button
                type="submit"
                disabled={loading || !settingsLoaded || phone.replace(/\D/g, '').length !== 8}
                className="w-full py-3 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
              >
                {loading ? <Loader2 className="w-5 h-5 animate-spin" /> : 'Үргэлжлүүлэх'}
              </button>

              {/* Toggle to email login */}
              {loginSettings.login_email_enabled || loginSettings.login_email_register_without_otp_enabled ? (
                <div className="text-center">
                  <button
                    type="button"
                    onClick={() => setStep('email')}
                    className="text-sm text-blue-600 hover:underline"
                  >
                    Имэйлээр нэвтрэх
                  </button>
                </div>
              ) : null}

              {/* Social login buttons */}
              {(loginSettings.login_google_enabled || loginSettings.login_facebook_enabled) && (
                <>
                  <div className="relative my-2">
                    <div className="absolute inset-0 flex items-center"><div className="w-full border-t border-gray-200"></div></div>
                    <div className="relative flex justify-center text-sm"><span className="bg-white px-3 text-gray-400">эсвэл</span></div>
                  </div>
                  <div className="space-y-3">
                    {loginSettings.login_google_enabled && (
                      <>
                        <button
                          type="button"
                          onClick={handleGoogleLogin}
                          disabled={loading}
                          className="w-full py-3 border border-gray-300 rounded-xl font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 flex items-center justify-center gap-3"
                        >
                          <svg className="w-5 h-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                          Google-ээр нэвтрэх
                        </button>
                        <div id="google-signin-btn" className="hidden"></div>
                      </>
                    )}
                    {loginSettings.login_facebook_enabled && (
                      <button
                        type="button"
                        onClick={handleFacebookLogin}
                        disabled={loading}
                        className="w-full py-3 bg-[#1877F2] text-white rounded-xl font-medium hover:bg-[#166FE5] disabled:opacity-50 flex items-center justify-center gap-3"
                      >
                        <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        Facebook-ээр нэвтрэх
                      </button>
                    )}
                  </div>
                </>
              )}
            </form>
          )}

          {/* Step: Email */}
          {step === 'email' && (
            <form onSubmit={handleEmailSubmit} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Имэйл хаяг</label>
                <input
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="example@email.com"
                  autoFocus
                  className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none"
                />
              </div>
              <button
                type="submit"
                disabled={loading || !settingsLoaded || !email.trim()}
                className="w-full py-3 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
              >
                {loading ? <Loader2 className="w-5 h-5 animate-spin" /> : 'Үргэлжлүүлэх'}
              </button>

              {/* Toggle to phone login */}
              {loginSettings.login_phone_password_enabled || loginSettings.login_phone_otp_enabled || loginSettings.login_register_without_otp_enabled ? (
                <div className="text-center">
                  <button
                    type="button"
                    onClick={() => setStep('phone')}
                    className="text-sm text-blue-600 hover:underline"
                  >
                    Утасны дугаараар нэвтрэх
                  </button>
                </div>
              ) : null}
            </form>
          )}

          {/* Step: OTP */}
          {step === 'otp' && (
            <form onSubmit={handleOtpSubmit} className="space-y-4">
              <div className="flex justify-center gap-3">
                {otp.map((digit, i) => (
                  <input
                    key={i}
                    ref={(el) => { otpRefs.current[i] = el; }}
                    type="text"
                    inputMode="numeric"
                    value={digit}
                    onChange={(e) => handleOtpChange(i, e.target.value)}
                    onKeyDown={(e) => handleOtpKeyDown(i, e)}
                    maxLength={1}
                    className="w-14 h-14 text-center text-2xl font-bold border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none"
                  />
                ))}
              </div>
              <div className="text-center text-sm text-gray-500">
                {countdown > 0 ? (
                  <span>Дахин илгээх ({formatCountdown(countdown)})</span>
                ) : (
                  <button type="button" onClick={handleResendOtp} className="text-blue-600 hover:underline">
                    Код дахин илгээх
                  </button>
                )}
              </div>
              <button
                type="submit"
                disabled={loading || otp.join('').length !== 4}
                className="w-full py-3 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
              >
                {loading ? <Loader2 className="w-5 h-5 animate-spin" /> : 'Баталгаажуулах'}
              </button>
            </form>
          )}

          {/* Step: Email OTP */}
          {step === 'email-otp' && (
            <form onSubmit={handleEmailOtpSubmit} className="space-y-4">
              <div className="flex justify-center gap-3">
                {otp.map((digit, i) => (
                  <input
                    key={i}
                    ref={(el) => { otpRefs.current[i] = el; }}
                    type="text"
                    inputMode="numeric"
                    value={digit}
                    onChange={(e) => handleOtpChange(i, e.target.value)}
                    onKeyDown={(e) => handleOtpKeyDown(i, e)}
                    maxLength={1}
                    className="w-14 h-14 text-center text-2xl font-bold border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none"
                  />
                ))}
              </div>
              <div className="text-center text-sm text-gray-500">
                {countdown > 0 ? (
                  <span>Дахин илгээх ({formatCountdown(countdown)})</span>
                ) : (
                  <button type="button" onClick={() => handleResendOtp()} className="text-blue-600 hover:underline">
                    Код дахин илгээх
                  </button>
                )}
              </div>
              <button
                type="submit"
                disabled={loading || otp.join('').length !== 4}
                className="w-full py-3 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
              >
                {loading ? <Loader2 className="w-5 h-5 animate-spin" /> : 'Баталгаажуулах'}
              </button>
            </form>
          )}

          {/* Step: Password Login */}
          {step === 'password' && (
            <form onSubmit={handlePasswordLogin} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Нууц үг</label>
                <input
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  autoFocus
                  className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none"
                />
              </div>
              <button
                type="submit"
                disabled={loading || !password}
                className="w-full py-3 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
              >
                {loading ? <Loader2 className="w-5 h-5 animate-spin" /> : 'Нэвтрэх'}
              </button>
              <button
                type="button"
                onClick={handleForgotPassword}
                className="w-full text-sm text-blue-600 hover:underline"
              >
                Нууц үг мартсан?
              </button>
            </form>
          )}

          {/* Step: Email Password Login */}
          {step === 'email-password' && (
            <form onSubmit={handleEmailPasswordLogin} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Нууц үг</label>
                <input
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  autoFocus
                  className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none"
                />
              </div>
              <button
                type="submit"
                disabled={loading || !password}
                className="w-full py-3 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
              >
                {loading ? <Loader2 className="w-5 h-5 animate-spin" /> : 'Нэвтрэх'}
              </button>
              <button
                type="button"
                onClick={() => {
                  setIsForgotPassword(true);
                  setStep('email');
                }}
                className="w-full text-sm text-blue-600 hover:underline"
              >
                Нууц үг мартсан?
              </button>
            </form>
          )}

          {/* Step: Set Password (Register / Reset) */}
          {step === 'set-password' && (
            <form onSubmit={handleSetPassword} className="space-y-4">
              {isNewUser && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Нэр *</label>
                  <input
                    type="text"
                    required
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="Таны нэр"
                    autoFocus
                    className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none"
                  />
                </div>
              )}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Нууц үг</label>
                <input
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="Хамгийн багадаа 6 тэмдэгт"
                  autoFocus={!isNewUser}
                  className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none"
                />
                <p className="text-xs text-gray-400 mt-1">Нууц үг хамгийн багадаа 6 тэмдэгт байх ёстой</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Нууц үг давтах</label>
                <input
                  type="password"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none"
                />
              </div>
              <button
                type="submit"
                disabled={loading || password.length < 6 || password !== confirmPassword || (isNewUser && !name.trim())}
                className="w-full py-3 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
              >
                {loading ? <Loader2 className="w-5 h-5 animate-spin" /> : (isNewUser ? 'Бүртгүүлэх' : 'Нууц үг шинэчлэх')}
              </button>
            </form>
          )}

          {/* Step: Email Set Password (Register / Reset) */}
          {step === 'email-set-password' && (
            <form onSubmit={handleEmailSetPassword} className="space-y-4">
              {isNewUser && (
                <>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Нэр *</label>
                    <input
                      type="text"
                      required
                      value={name}
                      onChange={(e) => setName(e.target.value)}
                      placeholder="Таны нэр"
                      autoFocus
                      className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Утасны дугаар *</label>
                    <input
                      type="tel"
                      required
                      value={phone}
                      onChange={(e) => setPhone(e.target.value)}
                      placeholder="99112233"
                      maxLength={8}
                      className="w-full px-4 py-3 border border-gray-300 rounded-xl text-lg text-center tracking-widest focus:ring-2 focus:ring-blue-500 outline-none"
                    />
                  </div>
                </>
              )}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Нууц үг</label>
                <input
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="Хамгийн багадаа 6 тэмдэгт"
                  autoFocus={!isNewUser}
                  className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none"
                />
                <p className="text-xs text-gray-400 mt-1">Нууц үг хамгийн багадаа 6 тэмдэгт байх ёстой</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Нууц үг давтах</label>
                <input
                  type="password"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none"
                />
              </div>
              <button
                type="submit"
                disabled={loading || password.length < 6 || password !== confirmPassword || (isNewUser && (!name.trim() || phone.replace(/\D/g, '').length !== 8))}
                className="w-full py-3 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
              >
                {loading ? <Loader2 className="w-5 h-5 animate-spin" /> : (isNewUser ? 'Бүртгүүлэх' : 'Нууц үг шинэчлэх')}
              </button>
            </form>
          )}

          {/* Step: Choose registration method (when both OTP and direct register are enabled) */}
          {step === 'register-choose' && (
            <div className="space-y-3">
              <button
                type="button"
                onClick={handleChooseOtp}
                disabled={loading}
                className="w-full py-3 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 disabled:opacity-50 flex items-center justify-center gap-2"
              >
                {loading ? <Loader2 className="w-5 h-5 animate-spin" /> : 'SMS код хүлээн авч баталгаажуулах'}
              </button>
              <button
                type="button"
                onClick={() => setStep('direct-register')}
                disabled={loading}
                className="w-full py-3 border border-gray-300 text-gray-800 rounded-xl font-semibold hover:bg-gray-50 disabled:opacity-50 flex items-center justify-center gap-2"
              >
                SMS-гүй шууд бүртгүүлэх
              </button>
              <p className="text-xs text-gray-400 text-center pt-2">
                SMS код хүлээж авах боломжгүй бол шууд бүртгүүлэх боломжтой.
              </p>
            </div>
          )}

          {/* Step: Direct Register (no OTP) */}
          {step === 'direct-register' && (
            <form onSubmit={handleDirectRegister} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Нэр *</label>
                <input
                  type="text"
                  required
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="Таны нэр"
                  maxLength={100}
                  autoFocus
                  className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Нууц үг</label>
                <input
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="Хамгийн багадаа 6 тэмдэгт"
                  className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none"
                />
                <p className="text-xs text-gray-400 mt-1">Нууц үг хамгийн багадаа 6 тэмдэгт байх ёстой</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Нууц үг давтах</label>
                <input
                  type="password"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none"
                />
              </div>
              <button
                type="submit"
                disabled={loading || password.length < 6 || password !== confirmPassword || !name.trim()}
                className="w-full py-3 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
              >
                {loading ? <Loader2 className="w-5 h-5 animate-spin" /> : 'Бүртгүүлэх'}
              </button>
            </form>
          )}

          {/* Step: Email Direct Register (no OTP) */}
          {step === 'email-direct-register' && (
            <form onSubmit={handleEmailDirectRegister} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Нэр *</label>
                <input
                  type="text"
                  required
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="Таны нэр"
                  maxLength={100}
                  autoFocus
                  className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Утасны дугаар *</label>
                <input
                  type="tel"
                  required
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="99112233"
                  maxLength={8}
                  className="w-full px-4 py-3 border border-gray-300 rounded-xl text-lg text-center tracking-widest focus:ring-2 focus:ring-blue-500 outline-none"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Нууц үг</label>
                <input
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="Хамгийн багадаа 6 тэмдэгт"
                  className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none"
                />
                <p className="text-xs text-gray-400 mt-1">Нууц үг хамгийн багадаа 6 тэмдэгт байх ёстой</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Нууц үг давтах</label>
                <input
                  type="password"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none"
                />
              </div>
              <button
                type="submit"
                disabled={loading || password.length < 6 || password !== confirmPassword || !name.trim() || phone.replace(/\D/g, '').length !== 8}
                className="w-full py-3 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
              >
                {loading ? <Loader2 className="w-5 h-5 animate-spin" /> : 'Бүртгүүлэх'}
              </button>
            </form>
          )}

          {/* Step: Social Phone Verification */}
          {step === 'social-phone' && (
            <form onSubmit={handleSocialPhoneSubmit} className="space-y-4">
              {socialProfile && (
                <div className="text-center text-sm text-gray-500 -mt-4 mb-4">
                  {socialProfile.email && <p>{socialProfile.email}</p>}
                </div>
              )}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Утасны дугаар</label>
                <input
                  type="tel"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="99112233"
                  maxLength={8}
                  autoFocus
                  className="w-full px-4 py-3 border border-gray-300 rounded-xl text-lg text-center tracking-widest focus:ring-2 focus:ring-blue-500 outline-none"
                />
              </div>
              <button
                type="submit"
                disabled={loading || phone.replace(/\D/g, '').length !== 8}
                className="w-full py-3 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
              >
                {loading ? <Loader2 className="w-5 h-5 animate-spin" /> : 'Код илгээх'}
              </button>
            </form>
          )}

          {/* Step: Social OTP Verification */}
          {step === 'social-otp' && (
            <form onSubmit={handleSocialOtpSubmit} className="space-y-4">
              <div className="flex justify-center gap-3">
                {otp.map((digit, i) => (
                  <input
                    key={i}
                    ref={(el) => { otpRefs.current[i] = el; }}
                    type="text"
                    inputMode="numeric"
                    value={digit}
                    onChange={(e) => handleOtpChange(i, e.target.value)}
                    onKeyDown={(e) => handleOtpKeyDown(i, e)}
                    maxLength={1}
                    className="w-14 h-14 text-center text-2xl font-bold border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none"
                  />
                ))}
              </div>
              <div className="text-center text-sm text-gray-500">
                {countdown > 0 ? (
                  <span>Дахин илгээх ({formatCountdown(countdown)})</span>
                ) : (
                  <button type="button" onClick={handleResendOtp} className="text-blue-600 hover:underline">
                    Код дахин илгээх
                  </button>
                )}
              </div>
              <button
                type="submit"
                disabled={loading || otp.join('').length !== 4}
                className="w-full py-3 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
              >
                {loading ? <Loader2 className="w-5 h-5 animate-spin" /> : 'Баталгаажуулах'}
              </button>
            </form>
          )}

          {/* Back button */}
          {(step !== 'phone' && !(step === 'email' && !hasPhoneLogin)) && (
            <button
              onClick={goBack}
              className="mt-4 w-full text-sm text-gray-500 hover:text-gray-700 flex items-center justify-center gap-1"
            >
              <ArrowLeft className="w-4 h-4" /> Буцах
            </button>
          )}
        </div>
      </div>
    </div>
  );
};
