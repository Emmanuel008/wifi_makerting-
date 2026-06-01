import React from 'react';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import { swalBase } from '../swalTheme';
import client from '../api/client';

const LOGO_SRC = `${process.env.PUBLIC_URL}/view.png`;

function pickRedirect(searchParams) {
  return (
    searchParams.get('dst') ||
    searchParams.get('link-orig') ||
    searchParams.get('link_orig') ||
    searchParams.get('link-orig-only') ||
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

export default function CaptivePortalForm({ searchParams, embedded, onBack }) {
  const dstRaw = pickRedirect(searchParams);
  const dst = isSafeHttpUrl(dstRaw) ? dstRaw : '';

  const linkLoginRaw = searchParams.get('link-login') || '';
  const linkLogin = isSafeHttpUrl(linkLoginRaw) ? linkLoginRaw : '';
  const mac = searchParams.get('mac') || '';
  const ip = searchParams.get('ip') || '';

  const [step, setStep] = React.useState('form');
  const [phone, setPhone] = React.useState('');
  const [wifiPassword, setWifiPassword] = React.useState('');
  const [loading, setLoading] = React.useState(false);
  const [destination, setDestination] = React.useState('');
  const [hotspotPassword, setHotspotPassword] = React.useState('');

  // Redirect to MikroTik login via window.location (navigation is allowed HTTPS→HTTP,
  // unlike form POST which is blocked as mixed content)
  React.useEffect(() => {
    if (step === 'done' && linkLogin && hotspotPassword !== null) {
      try {
        const url = new URL(linkLogin);
        url.searchParams.set('username', 'guest');
        url.searchParams.set('password', hotspotPassword);
        if (destination) url.searchParams.set('dst', destination);
        window.location.href = url.toString();
      } catch {
        // linkLogin was not a valid URL — fall through to manual button
      }
    }
  }, [step, linkLogin, hotspotPassword, destination]);

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

    setLoading(true);
    try {
      const { data } = await client.post('/api/wifi-client-auth', {
        phone: trimmed,
        wifiPassword: wifiPassword.trim(),
        mac: mac || undefined,
        ip: ip || undefined,
      });
      const isSuccess = Boolean(data?.success ?? data?.sucess);
      if (!isSuccess) {
        throw new Error(data?.error || data?.message || 'Authentication failed.');
      }
      setDestination(dst || 'https://example.com');
      setHotspotPassword(data.hotspot_password || '');
      setStep('done');
    } catch (error) {
      const apiMessage =
        error?.response?.data?.error ||
        error?.response?.data?.message ||
        error?.message ||
        'Unable to authenticate right now. Please try again.';
      await Swal.fire({
        ...swalBase,
        icon: 'error',
        title: 'Connection failed',
        text: apiMessage,
      });
    } finally {
      setLoading(false);
    }
  }, [phone, wifiPassword, dst, mac, ip]);

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
                {linkLogin
                  ? 'Opening your connection…'
                  : 'If the internet does not open automatically, use the button below. Your venue may still authorize the session on the router.'}
              </p>
              {linkLogin ? (
                <div className="captiveDoneHint">Opening your connection…</div>
              ) : destination ? (
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
