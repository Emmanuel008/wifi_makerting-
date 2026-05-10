import React from 'react';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import { FiEye, FiEyeOff } from 'react-icons/fi';
import client from '../api/client';
import { swalBase } from '../swalTheme';

export default function WifiPassword() {
  const [wifiPassword, setWifiPassword] = React.useState('');
  const [saving, setSaving] = React.useState(false);
  const [showPassword, setShowPassword] = React.useState(false);

  const submit = React.useCallback(async () => {
    const trimmedPassword = wifiPassword.trim();
    if (!trimmedPassword) {
      await Swal.fire({
        ...swalBase,
        icon: 'warning',
        title: 'Password required',
        text: 'Please enter a WiFi password before saving.',
      });
      return;
    }

    setSaving(true);
    Swal.fire({
      ...swalBase,
      title: 'Saving…',
      allowOutsideClick: false,
      showConfirmButton: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });

    try {
      const { data } = await client.post('/api/store-wifi-password', {
        wifiPassword: trimmedPassword,
      });
      const isSuccess = Boolean(data?.success ?? data?.sucess);
      if (!isSuccess) {
        throw new Error(data?.error || data?.message || 'Failed to save WiFi password.');
      }
      Swal.close();
      await Swal.fire({
        ...swalBase,
        icon: 'success',
        title: 'WiFi password saved',
        text: 'The password was stored successfully.',
      });
      setWifiPassword('');
    } catch (error) {
      Swal.close();
      const apiMessage =
        error?.response?.data?.error ||
        error?.response?.data?.message ||
        error?.message ||
        'Unable to save WiFi password right now.';
      await Swal.fire({
        ...swalBase,
        icon: 'error',
        title: 'Save failed',
        text: apiMessage,
      });
    } finally {
      setSaving(false);
    }
  }, [wifiPassword]);

  return (
    <div className="card pageCard">
      <div className="cardHeader">
        <div>
          <div className="cardTitle">WiFi Password</div>
          <div className="cardSub">Save captive portal WiFi password</div>
        </div>
      </div>
      <div className="pageBody">
        <div className="formGrid">
          <div className="field fieldWide">
            <div className="fieldLabel">WiFi password</div>
            <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
              <input
                className="fieldInput"
                type={showPassword ? 'text' : 'password'}
                autoComplete="current-password"
                placeholder="Enter WiFi password"
                value={wifiPassword}
                onChange={(e) => setWifiPassword(e.target.value)}
                disabled={saving}
              />
              <button
                className="btnSecondary"
                type="button"
                onClick={() => setShowPassword((prev) => !prev)}
                disabled={saving}
                aria-label={showPassword ? 'Hide password' : 'Show password'}
                title={showPassword ? 'Hide password' : 'Show password'}
              >
                {showPassword ? <FiEyeOff aria-hidden="true" /> : <FiEye aria-hidden="true" />}
              </button>
            </div>
          </div>
        </div>
        <div className="actions">
          <button className="btnPrimary" type="button" onClick={submit} disabled={saving}>
            {saving ? 'Saving…' : 'Save WiFi password'}
          </button>
        </div>
      </div>
    </div>
  );
}
