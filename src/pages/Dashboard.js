import React from 'react';

export default function Dashboard() {
  return (
    <>
      <section className="kpis" aria-label="Key metrics">
        <div className="card kpi">
          <div className="kpiHeader">Connected users</div>
          <div className="kpiValue">128</div>
          <div className="kpiDelta kpiUp">+12% vs. yesterday</div>
        </div>
        <div className="card kpi">
          <div className="kpiHeader">WiFi sessions (24h)</div>
          <div className="kpiValue">1,042</div>
          <div className="kpiDelta">Peak at 6:10 PM</div>
        </div>
        <div className="card kpi">
          <div className="kpiHeader">SMS sent (7d)</div>
          <div className="kpiValue">8,390</div>
          <div className="kpiDelta kpiDown">-3% week over week</div>
        </div>
        <div className="card kpi">
          <div className="kpiHeader">Deliverability</div>
          <div className="kpiValue">98.7%</div>
          <div className="kpiDelta">Stable</div>
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

