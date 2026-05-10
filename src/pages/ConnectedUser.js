import React, { useCallback, useEffect, useMemo, useState } from 'react';
import Pagination from '../components/Pagination';

const PAGE_SIZE = 10;
const MOCK_ROWS = [
  { id: 1, phone: '+255700000001', device: 'iPhone', ssid: 'Cafe Guest', onlineSeconds: 1200, isLive: true },
  { id: 2, phone: '+255700000002', device: 'Android', ssid: 'Cafe Guest', onlineSeconds: 540, isLive: true },
  { id: 3, phone: '+255700000003', device: 'Windows', ssid: 'Office WiFi', onlineSeconds: 4300, isLive: false },
];

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
    await new Promise((resolve) => window.setTimeout(resolve, 250));
    const start = (page - 1) * PAGE_SIZE;
    setRows(MOCK_ROWS.slice(start, start + PAGE_SIZE));
    setTotal(MOCK_ROWS.length);
    setLoading(false);
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
