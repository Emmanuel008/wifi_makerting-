import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import Swal from 'sweetalert2';
import { FiDownload, FiLock, FiPause, FiTrash2 } from 'react-icons/fi';
import Pagination from '../components/Pagination';
import client from '../api/client';
import { swalBase } from '../swalTheme';
import { downloadConnectedUsersXlsx } from '../utils/exportConnectedUsers';

const PAGE_SIZE = 20;

const SESSION_PRESETS = [
  { label: '30 min', value: 30 },
  { label: '1 hr',   value: 60 },
  { label: '2 hr',   value: 120 },
  { label: '4 hr',   value: 240 },
  { label: '8 hr',   value: 480 },
  { label: '12 hr',  value: 720 },
  { label: '24 hr',  value: 1440 },
];

function formatCountdown(ms) {
  if (ms <= 0) return 'Expiring…';
  const totalSec = Math.floor(ms / 1000);
  const h = Math.floor(totalSec / 3600);
  const m = Math.floor((totalSec % 3600) / 60);
  const s = totalSec % 60;
  if (h > 0) return `${h}h ${String(m).padStart(2, '0')}m ${String(s).padStart(2, '0')}s`;
  return `${String(m).padStart(2, '0')}m ${String(s).padStart(2, '0')}s`;
}

function formatElapsed(ms) {
  const totalMin = Math.floor(ms / 60000);
  if (totalMin < 1) return 'just now';
  if (totalMin < 60) return `${totalMin}m ago`;
  const h = Math.floor(totalMin / 60);
  const m = totalMin % 60;
  return m === 0 ? `${h}h ago` : `${h}h ${m}m ago`;
}

// Laravel returns timestamps without timezone info — force UTC parsing by appending Z
function parseUtc(s) {
  if (!s) return null;
  return new Date(s.replace(' ', 'T') + 'Z');
}

function formatMinutes(min) {
  if (!min) return '8 hr';
  const h = Math.floor(min / 60);
  const m = min % 60;
  if (h === 0) return `${m} min`;
  if (m === 0) return `${h} hr`;
  return `${h}h ${m}m`;
}

function toIsoParam(localDateTime) {
  if (!localDateTime) return undefined;
  const date = new Date(localDateTime);
  if (Number.isNaN(date.getTime())) return undefined;
  return date.toISOString();
}

function isInvalidDateRange(fromValue, toValue) {
  if (!fromValue || !toValue) return false;
  return new Date(fromValue) > new Date(toValue);
}

export default function ConnectedUser() {
  const [page, setPage] = useState(1);
  const [rows, setRows] = useState([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);
  const [syncing, setSyncing] = useState(false);
  const [syncResult, setSyncResult] = useState(null);
  const [error, setError] = useState(null);
  const [now, setNow] = useState(Date.now());
  const [fromDate, setFromDate] = useState('');
  const [toDate, setToDate] = useState('');
  const [appliedFrom, setAppliedFrom] = useState('');
  const [appliedTo, setAppliedTo] = useState('');
  const autoExpiredRef = useRef(new Set());

  const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));

  // Tick every second for live timers
  useEffect(() => {
    const id = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(id);
  }, []);

  // Auto-deactivate sessions that have exceeded their time limit
  useEffect(() => {
    rows.forEach((r) => {
      if (!r.is_active || !r.session_started_at || !r.session_minutes) return;
      const endTime = parseUtc(r.session_started_at).getTime() + r.session_minutes * 60000;
      if (now >= endTime && !autoExpiredRef.current.has(r.id)) {
        autoExpiredRef.current.add(r.id);
        client.patch(`/api/wifi-clients/${r.id}`, { is_active: false })
          .then(() => setRows((prev) => prev.map((row) => row.id === r.id ? { ...row, is_active: false } : row)))
          .catch(() => {});
      }
    });
  }, [now, rows]);

  const handleDeactivate = useCallback(async (row) => {
    const result = await Swal.fire({
      ...swalBase,
      icon: 'warning',
      title: 'Deactivate client?',
      html: `<span style="font-family:monospace">${row.phone}</span> will be disconnected immediately. They must sign in again to get internet access.`,
      showCancelButton: true,
      confirmButtonText: 'Deactivate',
      cancelButtonText: 'Cancel',
    });
    if (!result.isConfirmed) return;

    try {
      await client.patch(`/api/wifi-clients/${row.id}`, { is_active: false });
      setRows((prev) =>
        prev.map((r) => r.id === row.id ? { ...r, is_active: false } : r)
      );
    } catch (err) {
      Swal.fire({
        ...swalBase,
        icon: 'error',
        title: 'Failed',
        text: err?.response?.data?.error || err?.message || 'Could not update client.',
      });
    }
  }, []);

  const handleSessionLimit = useCallback(async (row) => {
    const current = row.session_minutes ?? 480;
    const { value } = await Swal.fire({
      ...swalBase,
      title: 'Set session time limit',
      html: `
        <div style="display:flex;flex-direction:column;gap:10px;margin-top:8px">
          <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:center">
            ${SESSION_PRESETS.map(p =>
              `<button type="button" onclick="document.getElementById('swal-min-input').value='${p.value}'"
                style="padding:6px 12px;border-radius:8px;border:1px solid rgba(255,255,255,0.2);background:rgba(255,255,255,0.07);color:inherit;cursor:pointer">
                ${p.label}
              </button>`
            ).join('')}
          </div>
          <input id="swal-min-input" type="number" min="1" max="10080" value="${current}"
            style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid rgba(255,255,255,0.2);background:rgba(255,255,255,0.07);color:inherit;text-align:center;font-size:15px"
            placeholder="Minutes" />
          <div style="font-size:12px;opacity:0.5;text-align:center">Enter minutes (e.g. 480 = 8 hours)</div>
        </div>`,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: 'Save',
      cancelButtonText: 'Cancel',
      preConfirm: () => {
        const val = parseInt(document.getElementById('swal-min-input').value, 10);
        if (!val || val < 1) {
          Swal.showValidationMessage('Please enter a valid number of minutes.');
          return false;
        }
        return val;
      },
    });

    if (!value) return;

    try {
      await client.patch(`/api/wifi-clients/${row.id}`, { session_minutes: value });
      setRows((prev) =>
        prev.map((r) => r.id === row.id ? { ...r, session_minutes: value } : r)
      );
    } catch (err) {
      Swal.fire({
        ...swalBase,
        icon: 'error',
        title: 'Failed',
        text: err?.response?.data?.error || err?.message || 'Could not update time limit.',
      });
    }
  }, []);

  const handleDelete = useCallback(async (row) => {
    const result = await Swal.fire({
      ...swalBase,
      icon: 'warning',
      title: 'Remove client?',
      html: `<span style="font-family:monospace">${row.phone}</span> will be disconnected and must sign in again to get internet access.`,
      showCancelButton: true,
      confirmButtonText: 'Remove',
      cancelButtonText: 'Cancel',
    });
    if (!result.isConfirmed) return;

    try {
      await client.delete(`/api/wifi-clients/${row.id}`);
      setRows((prev) => prev.filter((r) => r.id !== row.id));
      setTotal((t) => Math.max(0, t - 1));
    } catch (err) {
      Swal.fire({
        ...swalBase,
        icon: 'error',
        title: 'Failed',
        text: err?.response?.data?.error || err?.message || 'Could not delete client.',
      });
    }
  }, []);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const params = { page };
      const from = toIsoParam(appliedFrom);
      const to = toIsoParam(appliedTo);
      if (from) params.from = from;
      if (to) params.to = to;

      const { data } = await client.get('/api/wifi-clients', { params });
      setRows(data.data ?? []);
      setTotal(data.total ?? 0);
    } catch (err) {
      setError(err?.response?.data?.error || err?.message || 'Failed to load clients.');
      setRows([]);
      setTotal(0);
    } finally {
      setLoading(false);
    }
  }, [page, appliedFrom, appliedTo]);

  const applyFilters = useCallback(() => {
    if (isInvalidDateRange(fromDate, toDate)) {
      Swal.fire({
        ...swalBase,
        icon: 'error',
        title: 'Invalid date range',
        text: 'From date/time must be before To date/time.',
      });
      return;
    }
    setAppliedFrom(fromDate);
    setAppliedTo(toDate);
    setPage(1);
  }, [fromDate, toDate]);

  const clearFilters = useCallback(() => {
    setFromDate('');
    setToDate('');
    setAppliedFrom('');
    setAppliedTo('');
    setPage(1);
  }, []);

  const handleSync = useCallback(async () => {
    setSyncing(true);
    setSyncResult(null);
    try {
      const { data } = await client.post('/api/wifi-clients/sync-mikrotik');
      setSyncResult(data);
      if (data.deactivated_count > 0) {
        // Refresh list so UI reflects the newly inactive records
        load();
      }
    } catch (err) {
      const msg = err?.response?.data?.error || err?.message || 'Sync failed.';
      setSyncResult({ success: false, mikrotik_error: msg });
    } finally {
      setSyncing(false);
    }
  }, [load]);

  const handleExport = useCallback(async () => {
    if (isInvalidDateRange(fromDate, toDate)) {
      Swal.fire({
        ...swalBase,
        icon: 'error',
        title: 'Invalid date range',
        text: 'From date/time must be before To date/time.',
      });
      return;
    }

    setExporting(true);
    try {
      const params = { export: '1' };
      const from = toIsoParam(fromDate);
      const to = toIsoParam(toDate);
      if (from) params.from = from;
      if (to) params.to = to;

      const { data } = await client.get('/api/wifi-clients', { params });
      const exportRows = data.data ?? [];

      if (exportRows.length === 0) {
        Swal.fire({
          ...swalBase,
          icon: 'info',
          title: 'No records',
          text: 'No users match the selected date/time range.',
        });
        return;
      }

      downloadConnectedUsersXlsx(exportRows, fromDate, toDate);
    } catch (err) {
      Swal.fire({
        ...swalBase,
        icon: 'error',
        title: 'Export failed',
        text: err?.response?.data?.error || err?.message || 'Could not export clients.',
      });
    } finally {
      setExporting(false);
    }
  }, [fromDate, toDate]);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    setPage((p) => Math.min(p, totalPages));
  }, [totalPages]);

  const emptyHint = useMemo(() => {
    if (loading) return 'Loading…';
    if (error) return error;
    if (total === 0) {
      if (appliedFrom || appliedTo) return 'No users match the selected date/time range.';
      return 'No sessions yet. Guests appear after captive sign-in.';
    }
    return null;
  }, [loading, error, total, appliedFrom, appliedTo]);

  return (
    <div className="card pageCard">
      <div className="cardHeader">
        <div>
          <div className="cardTitle">Connected User</div>
          <div className="cardSub">Phone numbers collected through the captive portal</div>
        </div>
        <div className="cardHeaderActions">
          <button className="btnSecondary" type="button" disabled={loading} onClick={() => load()}>
            Refresh
          </button>
          <button
            className="btnSecondary"
            type="button"
            disabled={syncing || loading}
            onClick={handleSync}
            title="Compare DB with MikroTik active sessions and deactivate stale records"
          >
            {syncing ? 'Syncing…' : 'Sync MikroTik'}
          </button>
          <button
            className="btnPrimary"
            type="button"
            disabled={exporting || loading}
            onClick={handleExport}
          >
            <span className="btnWithIcon">
              <FiDownload aria-hidden="true" />
              {exporting ? 'Exporting…' : 'Download Excel'}
            </span>
          </button>
        </div>
      </div>
      <div className="pageBody">
        {syncResult && (
          <div className={`syncBanner ${syncResult.mikrotik_error ? 'syncBannerError' : 'syncBannerOk'}`}>
            {syncResult.mikrotik_error ? (
              <span>MikroTik unreachable: {syncResult.mikrotik_error}</span>
            ) : (
              <span>
                MikroTik active: <strong>{syncResult.mikrotik_active_count}</strong>
                {' · '}Deactivated in DB: <strong>{syncResult.deactivated_count}</strong>
                {syncResult.deactivated_count === 0 ? ' · DB is in sync' : ''}
              </span>
            )}
            <button
              type="button"
              className="linkBtn"
              style={{ marginLeft: 12 }}
              onClick={() => setSyncResult(null)}
            >
              Dismiss
            </button>
          </div>
        )}
        <div className="filterToolbar" aria-label="Date filters">
          <div className="filterField">
            <label className="fieldLabel" htmlFor="connected-from">From</label>
            <input
              id="connected-from"
              className="fieldInput filterInput"
              type="datetime-local"
              value={fromDate}
              onChange={(e) => setFromDate(e.target.value)}
            />
          </div>
          <div className="filterField">
            <label className="fieldLabel" htmlFor="connected-to">To</label>
            <input
              id="connected-to"
              className="fieldInput filterInput"
              type="datetime-local"
              value={toDate}
              onChange={(e) => setToDate(e.target.value)}
            />
          </div>
          <div className="filterActions">
            <button className="btnSecondary" type="button" onClick={applyFilters} disabled={loading}>
              Apply filter
            </button>
            <button
              className="btnSecondary"
              type="button"
              onClick={clearFilters}
              disabled={loading || (!fromDate && !toDate && !appliedFrom && !appliedTo)}
            >
              Clear
            </button>
          </div>
        </div>
        {(appliedFrom || appliedTo) && (
          <div className="filterHint muted">
            Showing registrations
            {appliedFrom ? ` from ${appliedFrom.replace('T', ' ')}` : ''}
            {appliedTo ? ` to ${appliedTo.replace('T', ' ')}` : ''}.
          </div>
        )}
        <div className="table tableGrid7">
          <div className="row head row7">
            <div>Phone number</div>
            <div>MAC address</div>
            <div>IP address</div>
            <div>Session</div>
            <div>Time limit</div>
            <div>Registrations</div>
            <div>Actions</div>
          </div>
          {rows.length === 0 ? (
            <div className="row row7">
              <div className="muted" style={{ gridColumn: '1 / -1' }}>
                {emptyHint}
              </div>
            </div>
          ) : (
            rows.map((r) => (
              <div
                className="row row7"
                key={r.id}
                style={r.is_active === false ? { opacity: 0.45 } : undefined}
              >
                <div className="mono" data-label="Phone" style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                  <span
                    style={{
                      width: 8, height: 8, borderRadius: '50%', flexShrink: 0,
                      background: r.is_active === false ? '#ef4444' : '#22c55e',
                    }}
                  />
                  {r.phone || '—'}
                </div>
                <div className="mono" data-label="MAC">{r.mac_address || '—'}</div>
                <div className="mono" data-label="IP">{r.ip_address || '—'}</div>
                <div data-label="Session" style={{ fontSize: 12 }}>
                  {r.is_active === false ? (
                    <span style={{ color: '#ef4444' }}>
                      Disconnected {formatElapsed(now - parseUtc(r.updated_at).getTime())}
                    </span>
                  ) : r.session_started_at ? (() => {
                    const endTime = parseUtc(r.session_started_at).getTime() + (r.session_minutes ?? 480) * 60000;
                    const remaining = endTime - now;
                    const color = remaining < 5 * 60000 ? '#ef4444' : remaining < 15 * 60000 ? '#f59e0b' : '#22c55e';
                    return <span style={{ color, fontFamily: 'monospace' }}>{formatCountdown(remaining)}</span>;
                  })() : '—'}
                </div>
                <div>
                  <button
                    className="btnSecondary"
                    style={{ padding: '2px 10px', fontSize: 12, cursor: 'pointer' }}
                    title="Edit session time limit"
                    onClick={() => handleSessionLimit(r)}
                  >
                    {formatMinutes(r.session_minutes)}
                  </button>
                </div>
                <div data-label="Registrations" style={{ fontSize: 13, fontWeight: 600 }}>
                  {r.registration_count ?? '—'}
                </div>
                <div className="iconActions">
                  {r.is_active === false ? (
                    <span
                      className="iconBtn iconBtnMuted"
                      title="Suspended — user must sign in again through the portal"
                      aria-label="Suspended"
                    >
                      <FiLock aria-hidden="true" />
                    </span>
                  ) : (
                    <button
                      className="iconBtn iconBtnDanger"
                      type="button"
                      title="Deactivate (disconnect)"
                      aria-label={`Deactivate ${r.phone || 'client'}`}
                      onClick={() => handleDeactivate(r)}
                    >
                      <FiPause aria-hidden="true" />
                    </button>
                  )}
                  <button
                    className="iconBtn iconBtnDanger"
                    type="button"
                    title="Remove client"
                    aria-label={`Remove ${r.phone || 'client'}`}
                    onClick={() => handleDelete(r)}
                  >
                    <FiTrash2 aria-hidden="true" />
                  </button>
                </div>
              </div>
            ))
          )}
        </div>
        <Pagination page={page} pageSize={PAGE_SIZE} total={total} onPageChange={setPage} />
      </div>
    </div>
  );
}
