import React from 'react';
import { createPortal } from 'react-dom';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import { FiEdit2, FiTrash2 } from 'react-icons/fi';
import Pagination from '../components/Pagination';
import { swalBase } from '../swalTheme';

const PAGE_SIZE = 10;
const USERS_STORAGE_KEY = 'wm_users_v1';
const DEFAULT_USERS = [
  {
    id: 1,
    name: 'Admin User',
    companyName: 'WiFi Marketing',
    email: 'admin@admin.com',
    phone: '+255700000001',
    role: 'admin',
  },
];

function normalizeEmail(value) {
  return String(value || '').trim().toLowerCase();
}

function normalizePhone(value) {
  const v = String(value || '').trim();
  if (!v) return '';
  if (v.startsWith('+')) return v.replace(/[^\d+]/g, '');
  return `+${v.replace(/[^\d]/g, '')}`;
}

function roleLabel(role) {
  return role === 'admin' ? 'Admin' : 'Business';
}

function loadStoredUsers() {
  try {
    const raw = localStorage.getItem(USERS_STORAGE_KEY);
    const parsed = raw ? JSON.parse(raw) : null;
    return Array.isArray(parsed) ? parsed : DEFAULT_USERS;
  } catch {
    return DEFAULT_USERS;
  }
}

function saveStoredUsers(users) {
  localStorage.setItem(USERS_STORAGE_KEY, JSON.stringify(users));
}

export default function UserManagement() {
  const [allUsers, setAllUsers] = React.useState(() => loadStoredUsers());
  const [page, setPage] = React.useState(1);
  const [name, setName] = React.useState('');
  const [companyName, setCompanyName] = React.useState('');
  const [email, setEmail] = React.useState('');
  const [phone, setPhone] = React.useState('');
  const [role, setRole] = React.useState('business');
  const [showForm, setShowForm] = React.useState(false);
  const [editingId, setEditingId] = React.useState(null);
  const [saving, setSaving] = React.useState(false);
  const nameRef = React.useRef(null);

  React.useEffect(() => {
    saveStoredUsers(allUsers);
  }, [allUsers]);

  const users = React.useMemo(() => {
    const start = (page - 1) * PAGE_SIZE;
    return allUsers.slice(start, start + PAGE_SIZE);
  }, [allUsers, page]);
  const total = allUsers.length;
  const listLoading = false;

  const closeModal = React.useCallback(() => {
    setShowForm(false);
    setEditingId(null);
    setName('');
    setCompanyName('');
    setEmail('');
    setPhone('');
    setRole('business');
  }, []);

  React.useEffect(() => {
    if (!showForm) return undefined;
    const t = window.setTimeout(() => nameRef.current?.focus(), 50);
    return () => window.clearTimeout(t);
  }, [showForm]);

  React.useEffect(() => {
    if (!showForm) return undefined;
    function onKey(e) {
      if (e.key === 'Escape') closeModal();
    }
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [showForm, closeModal]);

  function focusAddUser() {
    setEditingId(null);
    setName('');
    setCompanyName('');
    setEmail('');
    setPhone('');
    setRole('business');
    setShowForm(true);
  }

  function startEdit(user) {
    setEditingId(user.id);
    setName(user.name || '');
    setCompanyName(user.companyName || '');
    setEmail(user.email || '');
    setPhone(user.phone || '');
    setRole(user.role === 'admin' ? 'admin' : 'business');
    setShowForm(true);
  }

  async function submitUser(e) {
    e.preventDefault();
    const trimmedName = String(name || '').trim();
    const trimmedCompanyName = String(companyName || '').trim();
    const normalizedEmail = normalizeEmail(email);
    const normalized = normalizePhone(phone);

    if (!trimmedName || !trimmedCompanyName || !normalizedEmail.includes('@') || normalized.length < 8) {
      await Swal.fire({ ...swalBase, icon: 'warning', title: 'Invalid fields', text: 'Please complete all fields correctly.' });
      return;
    }

    setSaving(true);
    const nextUsers = [...allUsers];
    if (editingId) {
      const idx = nextUsers.findIndex((u) => u.id === editingId);
      if (idx >= 0) {
        nextUsers[idx] = { ...nextUsers[idx], name: trimmedName, companyName: trimmedCompanyName, email: normalizedEmail, phone: normalized, role };
      }
    } else {
      nextUsers.unshift({
        id: Date.now(),
        name: trimmedName,
        companyName: trimmedCompanyName,
        email: normalizedEmail,
        phone: normalized,
        role,
      });
    }
    setAllUsers(nextUsers);
    setPage(1);
    setSaving(false);
    closeModal();
  }

  async function removeUser(user) {
    const result = await Swal.fire({
      ...swalBase,
      icon: 'warning',
      title: 'Delete user?',
      text: `Remove ${user.name || user.email || 'this user'}? This cannot be undone.`,
      showCancelButton: true,
      confirmButtonText: 'Delete',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#c0392b',
    });
    if (!result.isConfirmed) return;
    setAllUsers((prev) => prev.filter((u) => u.id !== user.id));
  }

  const onPageChange = React.useCallback((next) => setPage(next), []);

  const modal =
    showForm &&
    createPortal(
      <div className="modalBackdrop" role="presentation" onClick={saving ? undefined : closeModal}>
        <div
          className="modalPanel"
          role="dialog"
          aria-modal="true"
          aria-labelledby="userModalTitle"
          onClick={(e) => e.stopPropagation()}
        >
          <div className="modalHeader">
            <div id="userModalTitle" className="modalTitle">
              {editingId ? 'Edit user' : 'Add user'}
            </div>
            <button
              type="button"
              className="modalClose"
              onClick={closeModal}
              aria-label="Close"
              disabled={saving}
            >
              ×
            </button>
          </div>

          <form className="userModalForm" onSubmit={submitUser}>
            <div className="field">
              <div className="fieldLabel">Name</div>
              <input
                ref={nameRef}
                className="fieldInput"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="John Doe"
                autoComplete="name"
                disabled={saving}
              />
            </div>

            <div className="field">
              <div className="fieldLabel">Email</div>
              <input
                className="fieldInput"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="john@company.com"
                inputMode="email"
                autoComplete="email"
                disabled={saving}
              />
            </div>

            <div className="field">
              <div className="fieldLabel">Company name</div>
              <input
                className="fieldInput"
                value={companyName}
                onChange={(e) => setCompanyName(e.target.value)}
                placeholder="ACME Ltd"
                autoComplete="organization"
                disabled={saving}
              />
            </div>

            <div className="field">
              <div className="fieldLabel">Phone number</div>
              <input
                className="fieldInput"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                placeholder="+255625313162"
                inputMode="tel"
                autoComplete="tel"
                disabled={saving}
              />
            </div>

            <div className="field">
              <div className="fieldLabel">Role</div>
              <select
                className="fieldInput"
                value={role}
                onChange={(e) => setRole(e.target.value)}
                disabled={saving}
              >
                <option value="admin">Admin</option>
                <option value="business">Business</option>
              </select>
            </div>

            <div className="userFormActions">
              <button className="btnSecondary" type="button" onClick={closeModal} disabled={saving}>
                Cancel
              </button>
              <button className="btnPrimary" type="submit" disabled={saving}>
                {editingId ? 'Update' : 'Save'}
              </button>
            </div>
          </form>
        </div>
      </div>,
      document.body
    );

  return (
    <div className="card pageCard">
      <div className="cardHeader">
        <div>
          <div className="cardTitle">User Management</div>
          <div className="cardSub">Create users and assign roles</div>
        </div>
        <div className="cardHeaderActions">
          <button
            className="btnSecondary"
            type="button"
            onClick={() => setAllUsers(loadStoredUsers())}
            disabled={listLoading}
          >
            Refresh
          </button>
          <button className="btnSecondary" type="button" onClick={focusAddUser} disabled={listLoading}>
            Add user
          </button>
        </div>
      </div>

      <div className="pageBody">
        {modal}

        <div className="table" aria-label="Users">
          <div className="row head row6">
            <div>Name</div>
            <div>Company</div>
            <div>Email</div>
            <div>Phone number</div>
            <div>Role</div>
            <div>Actions</div>
          </div>

          {listLoading ? (
            <div className="emptyState">Loading users…</div>
          ) : total === 0 ? (
            <div className="emptyState">
              No users yet.{' '}
              <button className="linkBtn" type="button" onClick={focusAddUser}>
                Add your first user
              </button>
              .
            </div>
          ) : (
            users.map((u) => (
              <div className="row row6" key={u.id}>
                <div>{u.name || '—'}</div>
                <div>{u.companyName || '—'}</div>
                <div className="muted">{u.email || '—'}</div>
                <div className="mono">{u.phone || '—'}</div>
                <div>
                  <span className={`pill ${u.role === 'admin' ? 'pillRoleAdmin' : 'pillRoleBusiness'}`}>
                    {roleLabel(u.role)}
                  </span>
                </div>
                <div>
                  <div className="iconActions">
                    <button
                      className="iconBtn"
                      type="button"
                      onClick={() => startEdit(u)}
                      aria-label={`Edit ${u.name || 'user'}`}
                      title="Edit"
                    >
                      <FiEdit2 aria-hidden="true" />
                    </button>
                    <button
                      className="iconBtn iconBtnDanger"
                      type="button"
                      onClick={() => removeUser(u)}
                      aria-label={`Delete ${u.name || 'user'}`}
                      title="Delete"
                    >
                      <FiTrash2 aria-hidden="true" />
                    </button>
                  </div>
                </div>
              </div>
            ))
          )}
        </div>

        {!listLoading && total > 0 ? (
          <Pagination page={page} pageSize={PAGE_SIZE} total={total} onPageChange={onPageChange} />
        ) : null}
      </div>
    </div>
  );
}
