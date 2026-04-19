import React from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import { useAuth } from '../components/Auth';
import BrandMark from '../components/BrandMark';
import { swalBase } from '../swalTheme';

const LOGIN_ENDPOINT =
  process.env.REACT_APP_LOGIN_API_URL || 'http://localhost:8080/api/login.php';

export default function Login() {
  const navigate = useNavigate();
  const location = useLocation();
  const { signIn } = useAuth();

  const from = location.state?.from?.pathname || '/dashboard';

  const [email, setEmail] = React.useState('admin@admin.com');
  const [password, setPassword] = React.useState('');
  const [showPassword, setShowPassword] = React.useState(false);
  const [rememberMe, setRememberMe] = React.useState(true);
  const [loading, setLoading] = React.useState(false);

  async function onSubmit(e) {
    e.preventDefault();

    const trimmedEmail = String(email || '').trim();
    if (!trimmedEmail || !trimmedEmail.includes('@')) {
      await Swal.fire({
        ...swalBase,
        icon: 'warning',
        title: 'Invalid email',
        text: 'Enter a valid email address.',
      });
      return;
    }
    if (!password) {
      await Swal.fire({
        ...swalBase,
        icon: 'warning',
        title: 'Password required',
        text: 'Enter your password to continue.',
      });
      return;
    }

    if (!rememberMe) {
      try {
        localStorage.removeItem('wm_auth_v1');
      } catch {
        // ignore
      }
    }

    setLoading(true);
    Swal.fire({
      ...swalBase,
      title: 'Signing in…',
      allowOutsideClick: false,
      showConfirmButton: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });

    try {
      const res = await fetch(LOGIN_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: trimmedEmail, password }),
      });
      const data = await res.json().catch(() => null);
      Swal.close();

      if (!res.ok || !data || !data.ok) {
        const msg =
          (data && typeof data.error === 'string' && data.error) ||
          (res.status === 401 ? 'Invalid email or password.' : 'Sign in failed. Try again.');
        await Swal.fire({
          ...swalBase,
          icon: 'error',
          title: 'Sign in failed',
          text: msg,
        });
        return;
      }
      const user = data.user && typeof data.user === 'object' ? data.user : {};
      const userEmail = typeof user.email === 'string' ? user.email : trimmedEmail;
      const userId = typeof user.id === 'number' ? user.id : undefined;
      const userRole = user.role === 'admin' || user.role === 'business' ? user.role : undefined;
      const userName = typeof user.name === 'string' ? user.name : undefined;
      signIn({ email: userEmail, userId, role: userRole, name: userName });
      await Swal.fire({
        ...swalBase,
        icon: 'success',
        title: 'Welcome back',
        text: `Signed in as ${userEmail}`,
      });
      navigate(from, { replace: true });
    } catch {
      Swal.close();
      await Swal.fire({
        ...swalBase,
        icon: 'error',
        title: 'Network error',
        text: 'Could not reach the login server. Is PHP running on port 8080?',
      });
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="auth">
      <div className="authInner">
        <div className="loginFrame">
          <div className="loginLeft" aria-hidden="true">
            <div className="loginLeftInner">
              <div className="loginBrand">
                <BrandMark />
                <div className="loginBrandText">
                  <div className="loginBrandName">WiFi Marketing</div>
                </div>
              </div>

              <div className="loginHeroTitle">Welcome back</div>
              <div className="loginHeroSub">
                Track visitor engagement, connected users, and bulk SMS campaigns in one dashboard.
              </div>
            </div>
            <div className="loginGlow" />
          </div>

          <div className="loginRight">
            <div className="loginHeader">
              <div className="loginTitle">Sign in</div>
              <div className="loginSubtitle">Use your admin credentials to continue.</div>
            </div>

            <div className="card authCard">
              <form className="portalCardBody" onSubmit={onSubmit}>
                <div className="field">
                  <div className="fieldLabel">Email</div>
                  <input
                    className="fieldInput"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="admin@admin.com"
                    type="email"
                    autoComplete="username"
                    disabled={loading}
                  />
                </div>
                <div className="field">
                  <div className="fieldRow">
                    <div className="fieldLabel">Password</div>
                    <button
                      className="linkBtn"
                      type="button"
                      onClick={() => setShowPassword((v) => !v)}
                    >
                      {showPassword ? 'Hide' : 'Show'}
                    </button>
                  </div>
                  <input
                    className="fieldInput"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    type={showPassword ? 'text' : 'password'}
                    placeholder="••••••••"
                    autoComplete="current-password"
                    disabled={loading}
                  />
                </div>

                <div className="loginRow">
                  <label className="check">
                    <input
                      type="checkbox"
                      checked={rememberMe}
                      onChange={(e) => setRememberMe(e.target.checked)}
                    />
                    <span>Remember me</span>
                  </label>
                  <button
                    className="linkBtn"
                    type="button"
                    onClick={() =>
                      Swal.fire({
                        ...swalBase,
                        icon: 'info',
                        title: 'Password reset',
                        text: 'Password reset is not enabled yet. Contact your administrator.',
                      })
                    }
                  >
                    Forgot password?
                  </button>
                </div>

                <button className="btnPrimary portalBtn" type="submit" disabled={loading}>
                  {loading ? 'Signing in…' : 'Sign in'}
                </button>

                <div className="loginFineprint">
                  By signing in, you agree to your organization’s acceptable use policy.
                </div>
              </form>
            </div>

            <div className="loginFooter">
              <div className="loginHelp">
                Need help? <span className="muted">Contact support</span>
              </div>
              <button className="portalBack" type="button" onClick={() => navigate('/')}>
                Privacy & terms
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

