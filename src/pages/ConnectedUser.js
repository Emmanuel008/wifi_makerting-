import React, { useCallback, useEffect, useMemo, useState } from 'react';
import Pagination from '../components/Pagination';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import { swalBase } from '../swalTheme';

const LIST_URL =
  process.env.REACT_APP_WIFI_CONNECTED_LIST_URL || 'http://localhost:8080/api/wifi-connected-list.php';

const PAGE_SIZE = 10;

function formatDuration(seconds) {
  const s = Number(seconds);
  if (!Number.isFinite(s) || s < 0) return '—';
  const m = Math.floor(s / 60);
  const h = Math.floor(m / 60);
  const d = Math.floor(h / 24);
  if (d > 0) return `${d}d ${h % 24}h`;
  if (h > 0) return `${h}h ${m % 60}m`;
  if (m > 0) return `${m}m`;
  return `${s}s`;
}

export default function ConnectedUser() {
  const [page, setPage] = useState(1);
  const [rows, setRows] = useState([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);

  const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetch(LIST_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ page, pageSize: PAGE_SIZE }),
      });
      const data = await res.json().catch(() => null);
      if (!res.ok || !data?.ok) {
        const msg =
          (data && typeof data.error === 'string' && data.error) ||
          'Could not load sessions. Is PHP/MySQL running and wifi_sessions.sql applied?';
        await Swal.fire({ ...swalBase, icon: 'error', title: 'Connected users', text: msg });
        setRows([]);
        setTotal(0);
        return;
      }
      setRows(Array.isArray(data.rows) ? data.rows : []);
      setTotal(typeof data.total === 'number' ? data.total : 0);
    } catch {
      await Swal.fire({
        ...swalBase,
        icon: 'error',
        title: 'Network',
        text: 'Could not reach the WiFi sessions API.',
      });
      setRows([]);
      setTotal(0);
    } finally {
      setLoading(false);
    }
  }, [page]);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    setPage((p) => Math.min(p, totalPages));
  }, [totalPages]);

  const emptyHint = useMemo(() => {
    if (loading) return 'Loading…';
    if (total === 0) return 'No sessions yet. Guests appear after captive sign-in or session pings.';
    return null;
  }, [loading, total]);

  return (
    <div className="card pageCard">
      <div className="cardHeader">
        <div>
          <div className="cardTitle">Connected User</div>
          <div className="cardSub">Phone, device, SSID, and time online from wifi_sessions</div>
        </div>
        <button className="btnSecondary" type="button" disabled={loading} onClick={() => load()}>
          Refresh
        </button>
      </div>
      <div className="pageBody">
        <div className="table">
          <div className="row head row4">
            <div>Phone number</div>
            <div>Device</div>
            <div>SSID</div>
            <div>Online</div>
          </div>
          {rows.length === 0 ? (
            <div className="row row4">
              <div className="muted" style={{ gridColumn: '1 / -1' }}>
                {emptyHint}
              </div>
            </div>
          ) : (
            rows.map((r) => (
              <div className="row row4" key={r.id}>
                <div className="mono">{r.phone || '—'}</div>
                <div>{r.device || '—'}</div>
                <div className="muted">{r.ssid || '—'}</div>
                <div>
                  {formatDuration(r.onlineSeconds)}
                  {r.isLive ? <span className="captiveLiveDot"> · Live</span> : null}
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
