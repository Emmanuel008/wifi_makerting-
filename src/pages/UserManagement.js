import React from 'react';
import { createPortal } from 'react-dom';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import { FiEdit2, FiTrash2 } from 'react-icons/fi';
import Pagination from '../components/Pagination';
import { swalBase } from '../swalTheme';

const USERS_API_URL =
  process.env.REACT_APP_USERS_API_URL || 'http://localhost:8080/api/managed-users.php';

const PAGE_SIZE = 10;

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

function afterSwalClose() {
  return new Promise((resolve) => {
    requestAnimationFrame(() => requestAnimationFrame(resolve));
  });
}

async function apiPost(payload) {
  const res = await fetch(USERS_API_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  let data = null;
  try {
    data = await res.json();
  } catch {
    /* ignore */
  }
  return { res, data };
}

export default function UserManagement() {
  const [users, setUsers] = React.useState([]);
  const [total, setTotal] = React.useState(0);
  const [page, setPage] = React.useState(1);
  const [listLoading, setListLoading] = React.useState(true);
  const [name, setName] = React.useState('');
  const [companyName, setCompanyName] = React.useState('');
  const [email, setEmail] = React.useState('');
  const [phone, setPhone] = React.useState('');
  const [role, setRole] = React.useState('business');
  const [showForm, setShowForm] = React.useState(false);
  const [editingId, setEditingId] = React.useState(null);
  const [saving, setSaving] = React.useState(false);
  const nameRef = React.useRef(null);
  const pageRef = React.useRef(1);
  pageRef.current = page;

  const loadUsers = React.useCallback(async (listPage) => {
    const p = listPage !== undefined && listPage !== null ? listPage : pageRef.current;
    setListLoading(true);
    try {
      const { res, data } = await apiPost({
        action: 'list',
        page: p,
        perPage: PAGE_SIZE,
      });
      if (!res.ok || !data || !data.ok || !Array.isArray(data.users)) {
        await Swal.fire({
          ...swalBase,
          icon: 'error',
          title: 'Could not load users',
          text:
            (data && data.error) ||
            'Check that the PHP API is running and the database table exists (managed_users.sql).',
        });
        setUsers([]);
        setTotal(0);
        return;
      }
      if (typeof data.page === 'number') {
        setPage(data.page);
      }
      setTotal(typeof data.total === 'number' ? data.total : 0);
      setUsers(data.users);
    } catch {
      await Swal.fire({
        ...swalBase,
        icon: 'error',
        title: 'Network error',
        text: 'Could not reach the user management API. Is PHP running on port 8080?',
      });
      setUsers([]);
      setTotal(0);
    } finally {
      setListLoading(false);
    }
  }, []);

  React.useEffect(() => {
    loadUsers(1);
  }, [loadUsers]);

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

  React.useEffect(() => {
    if (!showForm) return undefined;
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.body.style.overflow = prev;
    };
  }, [showForm]);

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
    if (!trimmedName) {
      await Swal.fire({
        ...swalBase,
        icon: 'warning',
        title: 'Name required',
        text: 'Enter the user’s full name.',
      });
      return;
    }

    const trimmedCompanyName = String(companyName || '').trim();
    if (!trimmedCompanyName) {
      await Swal.fire({
        ...swalBase,
        icon: 'warning',
        title: 'Company required',
        text: 'Enter the company name.',
      });
      return;
    }

    const normalizedEmail = normalizeEmail(email);
    if (!normalizedEmail || !normalizedEmail.includes('@')) {
      await Swal.fire({
        ...swalBase,
        icon: 'warning',
        title: 'Invalid email',
        text: 'Enter a valid email address.',
      });
      return;
    }

    const normalized = normalizePhone(phone);
    if (!normalized || normalized.length < 8) {
      await Swal.fire({
        ...swalBase,
        icon: 'warning',
        title: 'Invalid phone',
        text: 'Enter a valid phone number (include country code, e.g. +255…).',
      });
      return;
    }
    if (role !== 'admin' && role !== 'business') {
      await Swal.fire({
        ...swalBase,
        icon: 'warning',
        title: 'Role required',
        text: 'Choose Admin or Business.',
      });
      return;
    }

    setSaving(true);
    Swal.fire({
      ...swalBase,
      title: editingId ? 'Updating…' : 'Saving…',
      allowOutsideClick: false,
      showConfirmButton: false,
      didOpen: () => Swal.showLoading(),
    });

    try {
      const payload = editingId
        ? {
            action: 'update',
            id: editingId,
            name: trimmedName,
            companyName: trimmedCompanyName,
            email: normalizedEmail,
            phone: normalized,
            role,
          }
        : {
            action: 'save',
            name: trimmedName,
            companyName: trimmedCompanyName,
            email: normalizedEmail,
            phone: normalized,
            role,
          };

      const { res, data } = await apiPost(payload);
      Swal.close();
      await afterSwalClose();

      if (!res.ok || !data || !data.ok) {
        const msg =
          (data && typeof data.error === 'string' && data.error) ||
          (res.status === 409
            ? 'Email or phone is already in use.'
            : 'Could not save the user.');
        await Swal.fire({
          ...swalBase,
          icon: 'error',
          title: editingId ? 'Update failed' : 'Could not add user',
          text: msg,
        });
        return;
      }

      await Swal.fire({
        ...swalBase,
        icon: 'success',
        title: editingId ? 'User updated' : 'User added',
        text: editingId
          ? `${trimmedName} was updated successfully.`
          : `${trimmedName} was added to the directory.`,
      });
      closeModal();
      if (!editingId) {
        await loadUsers(1);
      } else {
        await loadUsers();
      }
    } catch {
      Swal.close();
      await afterSwalClose();
      await Swal.fire({
        ...swalBase,
        icon: 'error',
        title: 'Network error',
        text: 'Could not reach the user management API.',
      });
    } finally {
      setSaving(false);
    }
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
    if (!result.isConfirmed) {
      return;
    }

    Swal.fire({
      ...swalBase,
      title: 'Deleting…',
      allowOutsideClick: false,
      showConfirmButton: false,
      didOpen: () => Swal.showLoading(),
    });

    try {
      const { res, data } = await apiPost({ action: 'delete', id: user.id });
      Swal.close();
      await afterSwalClose();
      if (!res.ok || !data || !data.ok) {
        await Swal.fire({
          ...swalBase,
          icon: 'error',
          title: 'Delete failed',
          text: (data && data.error) || 'Could not delete this user.',
        });
        return;
      }
      await Swal.fire({
        ...swalBase,
        icon: 'success',
        title: 'User removed',
        text: `${user.name || user.email || 'The user'} was deleted from the directory.`,
      });
      await loadUsers();
    } catch {
      Swal.close();
      await afterSwalClose();
      await Swal.fire({
        ...swalBase,
        icon: 'error',
        title: 'Network error',
        text: 'Could not reach the user management API.',
      });
    }
  }

  const onPageChange = React.useCallback(
    (next) => {
      setPage(next);
      loadUsers(next);
    },
    [loadUsers]
  );

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
            onClick={() => loadUsers()}
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
