import React from 'react';
import { NavLink, Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import Dashboard from '../pages/Dashboard';
import ConnectedUser from '../pages/ConnectedUser';
import BulkSMS from '../pages/BulkSMS';
import Portal from '../pages/Portal';
import UserManagement from '../pages/UserManagement';
import { swalBase } from '../swalTheme';
import BrandMark from './BrandMark';
import { useAuth } from './Auth';

const NAV_ITEMS = [
  { key: 'Dashboard', label: 'Dashboard', to: '/dashboard' },
  { key: 'ConnectedUser', label: 'Connected User', to: '/connecteduser' },
  { key: 'BulkSMS', label: 'Bulk SMS', to: '/bulksms' },
  { key: 'UserManagement', label: 'User Management', to: '/usermanagement' },
];

function usePageTitle() {
  const { pathname } = useLocation();
  switch (pathname) {
    case '/connecteduser':
      return 'Connected User';
    case '/dashboard':
      return 'Dashboard';
    case '/bulksms':
      return 'Bulk SMS';
    case '/usermanagement':
      return 'User Management';
    case '/portal':
      return 'Portal';
    default:
      return 'Dashboard';
  }
}

export default function AppShell() {
  const pageTitle = usePageTitle();
  const navigate = useNavigate();
  const location = useLocation();
  const { isAuthed, session, signOut } = useAuth();

  const onSignOut = React.useCallback(async () => {
    const result = await Swal.fire({
      ...swalBase,
      icon: 'question',
      title: 'Sign out?',
      text: 'You will need to sign in again to access the admin console.',
      showCancelButton: true,
      confirmButtonText: 'Sign out',
      cancelButtonText: 'Stay signed in',
    });
    if (!result.isConfirmed) {
      return;
    }
    signOut();
    navigate('/', { replace: true });
    await Swal.fire({
      ...swalBase,
      icon: 'success',
      title: 'Signed out',
      text: 'You have been signed out successfully.',
      timer: 2200,
      showConfirmButton: false,
    });
  }, [navigate, signOut]);

  if (!isAuthed) {
    return <Navigate to="/" replace state={{ from: location }} />;
  }

  return (
    <div className="appShell">
      <aside className="sidebar" aria-label="Primary">
        <div className="sidebarBrand">
          <BrandMark />
          <div className="brandText">
            <div className="brandName">WiFi Marketing</div>
            <div className="brandSub">Admin Console</div>
          </div>
        </div>

        <nav className="sidebarNav" aria-label="Sidebar">
          {NAV_ITEMS.map((item) => (
            <NavLink
              key={item.key}
              to={item.to}
              className={({ isActive }) => `navItem ${isActive ? 'isActive' : ''}`}
            >
              <span className="navDot" aria-hidden="true" />
              <span className="navLabel">{item.label}</span>
            </NavLink>
          ))}
        </nav>

        <div className="sidebarDivider" role="separator" />

        <div className="sidebarFooter">
          <div className="sidebarHint">v0.1 • Internal</div>
          <button className="portalLink" type="button" onClick={() => navigate('/portal')}>
            Open Portal
          </button>
          <button className="signOutLink" type="button" onClick={onSignOut}>
            Sign out
          </button>
        </div>
      </aside>

      <div className="mainColumn">
        <header className="topbar">
          <div className="topbarLeft">
            <div className="pageTitle">{pageTitle}</div>
            <div className="pageSubtitle">Overview and recent activity</div>
          </div>
          <div className="topbarRight">
            <label className="search" aria-label="Search">
              <span className="searchIcon" aria-hidden="true">
                ⌕
              </span>
              <input className="searchInput" placeholder="Search users, campaigns, logs…" />
            </label>
            <div className="userChip" role="group" aria-label="Signed in user">
              <div className="avatar" aria-hidden="true">
                {(session?.name || session?.email || 'A').trim().charAt(0).toUpperCase() || 'A'}
              </div>
              <div className="userMeta">
                <div className="userName">{session?.name || session?.email || 'Admin'}</div>
                {session?.email && session?.name ? (
                  <div className="userEmailMuted">{session.email}</div>
                ) : null}
              </div>
            </div>
          </div>
        </header>

        <main className="content">
          <Routes>
            <Route path="/dashboard" element={<Dashboard />} />
            <Route path="/connecteduser" element={<ConnectedUser />} />
            <Route path="/bulksms" element={<BulkSMS />} />
            <Route path="/usermanagement" element={<UserManagement />} />
            <Route
              path="/portal"
              element={
                isAuthed ? (
                  <Portal onBack={() => navigate('/dashboard')} />
                ) : (
                  <Navigate to="/" replace state={{ from: location }} />
                )
              }
            />
            <Route path="*" element={<Navigate to="/dashboard" replace />} />
          </Routes>
        </main>
      </div>
    </div>
  );
}

