import React from 'react';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import { swalBase } from '../swalTheme';

const REGISTER_URL =
  process.env.REACT_APP_CAPTIVE_REGISTER_URL || 'http://localhost:8080/api/captive-register.php';
const SESSION_URL =
  process.env.REACT_APP_WIFI_SESSION_URL || 'http://localhost:8080/api/wifi-session.php';

const LOGO_SRC = `${process.env.PUBLIC_URL}/icon.jpeg`;

function pickRedirect(searchParams) {
  return (
    searchParams.get('dst') ||
    searchParams.get('link-orig') ||
    searchParams.get('link_orig') ||
    searchParams.get('link-orig-only') ||
    ''
  );
}

function pickSsid(searchParams) {
  return (
    searchParams.get('ssid') ||
    searchParams.get('essid') ||
    searchParams.get('SSID') ||
    ''
  );
}

function isSafeHttpUrl(s) {
  if (!s || typeof s !== 'string') return false;
  try {
    const u = new URL(s);
    return u.protocol === 'http:' || u.protocol === 'https:';
  } catch {
    return false;
  }
}

function clientDeviceHint() {
  if (typeof navigator === 'undefined') return '';
  const ua = navigator.userAgentData;
  if (ua && typeof ua.platform === 'string' && ua.platform.trim()) {
    return ua.platform.trim();
  }
  const p = navigator.platform;
  return typeof p === 'string' && p.trim() ? p.trim() : 'Web client';
}

export default function CaptivePortalForm({ searchParams, embedded, onBack }) {
  const mac = searchParams.get('mac') || searchParams.get('Mac') || '';
  const ssidFromRouter = pickSsid(searchParams).trim();
  const dstRaw = pickRedirect(searchParams);
  const dst = isSafeHttpUrl(dstRaw) ? dstRaw : '';

  const sessionRef = React.useRef({
    phone: '',
    mac: '',
    ssid: '',
    device: '',
    userAgent: '',
  });

  const [step, setStep] = React.useState('form');
  const [phone, setPhone] = React.useState('');
  const [wifiPassword, setWifiPassword] = React.useState('');
  const [loading, setLoading] = React.useState(false);
  const [destination, setDestination] = React.useState('');

  React.useEffect(() => {
    if (step !== 'done') return undefined;

    const pingBody = () => {
      const s = sessionRef.current;
      return JSON.stringify({
        action: 'ping',
        phone: s.phone || undefined,
        mac: s.mac || undefined,
        ssid: s.ssid || undefined,
        device: s.device || undefined,
        userAgent: s.userAgent || undefined,
      });
    };

    const ping = () => {
      fetch(SESSION_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: pingBody(),
      }).catch(() => {});
    };

    ping();
    const interval = window.setInterval(ping, 45_000);

    const disconnect = () => {
      const s = sessionRef.current;
      const payload =
        s.mac && s.mac.trim()
          ? { action: 'disconnect', mac: s.mac }
          : { action: 'disconnect', phone: s.phone };
      if (!payload.phone && !payload.mac) return;
      fetch(SESSION_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        keepalive: true,
      }).catch(() => {});
    };

    const onHide = () => disconnect();
    window.addEventListener('pagehide', onHide);

    return () => {
      window.clearInterval(interval);
      window.removeEventListener('pagehide', onHide);
      disconnect();
    };
  }, [step]);

  const submit = React.useCallback(async () => {
    const trimmed = phone.trim();
    if (!trimmed) {
      await Swal.fire({ ...swalBase, icon: 'warning', title: 'Phone required', text: 'Enter your mobile number.' });
      return;
    }
    if (!wifiPassword) {
      await Swal.fire({ ...swalBase, icon: 'warning', title: 'Password required', text: 'Enter the WiFi password.' });
      return;
    }

    const device = clientDeviceHint();
    const userAgent = typeof navigator !== 'undefined' ? navigator.userAgent : '';

    setLoading(true);
    try {
      const res = await fetch(REGISTER_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          phone: trimmed,
          wifiPassword,
          mac: mac || undefined,
          dst: dst || undefined,
          ssid: ssidFromRouter || undefined,
          device: device || undefined,
          userAgent: userAgent || undefined,
        }),
      });
      const data = await res.json().catch(() => null);
      if (!res.ok || !data?.ok) {
        const msg =
          (data && typeof data.error === 'string' && data.error) ||
          (res.status === 401 ? 'Wrong WiFi password.' : 'Could not connect. Try again.');
        await Swal.fire({ ...swalBase, icon: 'error', title: 'Sign-in failed', text: msg });
        return;
      }
      sessionRef.current = {
        phone: trimmed,
        mac: mac || '',
        ssid: ssidFromRouter || '',
        device,
        userAgent,
      };
      const next = typeof data.destination === 'string' && isSafeHttpUrl(data.destination) ? data.destination : dst;
      setDestination(next);
      setStep('done');
    } catch {
      await Swal.fire({
        ...swalBase,
        icon: 'error',
        title: 'Network',
        text: 'Could not reach the server. Is PHP running?',
      });
    } finally {
      setLoading(false);
    }
  }, [phone, wifiPassword, mac, dst, ssidFromRouter]);

  return (
    <div className="portalInner">
      <div className="portalBrand">
        <img className="brandMark" src={LOGO_SRC} alt="" width={34} height={34} decoding="async" aria-hidden="true" />
        <div>
          <div className="portalTitle">WiFi access</div>
          <div className="portalSub">Enter your phone number and WiFi password</div>
        </div>
      </div>

      <div className="card portalCard">
        <div className="portalCardBody">
          {step === 'form' ? (
            <>
              <div className="field">
                <div className="fieldLabel">Phone number</div>
                <input
                  className="fieldInput"
                  placeholder="+255 or 07…"
                  inputMode="tel"
                  autoComplete="tel"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  disabled={loading}
                />
              </div>
              <div className="field">
                <div className="fieldLabel">WiFi password</div>
                <input
                  className="fieldInput"
                  placeholder="Venue WiFi password"
                  type="password"
                  autoComplete="current-password"
                  value={wifiPassword}
                  onChange={(e) => setWifiPassword(e.target.value)}
                  disabled={loading}
                />
              </div>
              <button className="btnPrimary portalBtn" type="button" disabled={loading} onClick={submit}>
                {loading ? 'Connecting…' : 'Connect'}
              </button>
            </>
          ) : null}

          {step === 'done' ? (
            <div className="captiveDone">
              <div className="captiveDoneTitle">You&apos;re signed in</div>
              <p className="captiveDoneText">
                If the internet does not open automatically, use the button below. Your venue may still authorize the
                session on the router.
              </p>
              {destination ? (
                <a className="btnPrimary portalBtn captiveContinue" href={destination}>
                  Continue browsing
                </a>
              ) : (
                <div className="captiveDoneHint">You can close this page and try opening a website again.</div>
              )}
            </div>
          ) : null}
        </div>
      </div>

      {embedded && typeof onBack === 'function' ? (
        <button className="portalBack" type="button" onClick={onBack}>
          ← Back to dashboard
        </button>
      ) : null}
    </div>
  );
}
