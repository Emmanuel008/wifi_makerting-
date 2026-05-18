import React from 'react';
import client from '../api/client';

export default function Dashboard() {
  const [stats, setStats] = React.useState(null);

  React.useEffect(() => {
    client.get('/api/dashboard-stats').then(({ data }) => setStats(data)).catch(() => {});
  }, []);

  const totalClients   = stats?.total_clients   ?? '—';
  const totalSms       = stats?.total_sms        ?? '—';
  const deliverability = stats?.deliverability != null ? `${stats.deliverability}%` : '—';

  return (
    <>
      <section className="kpis" aria-label="Key metrics">
        <div className="card kpi">
          <div className="kpiHeader">Registered users</div>
          <div className="kpiValue">{totalClients}</div>
          <div className="kpiDelta">Total phone numbers collected</div>
        </div>
        <div className="card kpi">
          <div className="kpiHeader">SMS campaigns</div>
          <div className="kpiValue">{totalSms}</div>
          <div className="kpiDelta">Total campaigns sent</div>
        </div>
        <div className="card kpi">
          <div className="kpiHeader">SMS delivered</div>
          <div className="kpiValue">{stats?.delivered_sms ?? '—'}</div>
          <div className="kpiDelta">Successfully delivered messages</div>
        </div>
        <div className="card kpi">
          <div className="kpiHeader">Deliverability</div>
          <div className="kpiValue">{deliverability}</div>
          <div className="kpiDelta">{stats ? 'Calculated from outbox' : 'Loading…'}</div>
        </div>
      </section>

      <section className="grid2" aria-label="Dashboard content">
        <div className="card">
          <div className="cardHeader">
            <div>
              <div className="cardTitle">WiFi overview</div>
              <div className="cardSub">Hotspots and bandwidth snapshot</div>
            </div>
            <button className="btnSecondary" type="button">
              View details
            </button>
          </div>
          <div className="campaigns">
            <div className="campaign">
              <div className="campaignMain">
                <div className="campaignName">Store-WiFi</div>
                <div className="campaignMeta">Primary SSID • 128 devices</div>
              </div>
              <div className="campaignRight">
                <div className="campaignStat">42%</div>
                <div className="campaignMeta">Capacity</div>
              </div>
            </div>
            <div className="campaign">
              <div className="campaignMain">
                <div className="campaignName">Store-Guest</div>
                <div className="campaignMeta">Guest VLAN • 64 devices</div>
              </div>
              <div className="campaignRight">
                <div className="campaignStat">28%</div>
                <div className="campaignMeta">Capacity</div>
              </div>
            </div>
          </div>
        </div>

        <div className="card">
          <div className="cardHeader">
            <div>
              <div className="cardTitle">Bulk SMS</div>
              <div className="cardSub">Create and monitor campaigns</div>
            </div>
            <button className="btnPrimary" type="button">
              New campaign
            </button>
          </div>
          <div className="campaigns">
            <div className="campaign">
              <div className="campaignMain">
                <div className="campaignName">Spring Promo — Segment A</div>
                <div className="campaignMeta">Scheduled • Today 6:30 PM</div>
              </div>
              <div className="campaignRight">
                <div className="campaignStat">2,400</div>
                <div className="campaignMeta">Recipients</div>
              </div>
            </div>
            <div className="campaign">
              <div className="campaignMain">
                <div className="campaignName">Re-engagement</div>
                <div className="campaignMeta">Sent • Yesterday</div>
              </div>
              <div className="campaignRight">
                <div className="campaignStat">97.9%</div>
                <div className="campaignMeta">Delivered</div>
              </div>
            </div>
            <div className="divider" role="separator" />
            <div className="hint">
              Tip: Keep messages under 160 characters to avoid split segments.
            </div>
          </div>
        </div>
      </section>
    </>
  );
}

