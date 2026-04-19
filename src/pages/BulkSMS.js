import React, { useCallback, useState } from 'react';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import { swalBase } from '../swalTheme';

const SMS_ENDPOINT =
  process.env.REACT_APP_SMS_API_URL || 'http://localhost:8080/api/send-sms.php';

const DEFAULT_DELIVERY_REPORT_URL = 'https://your-server.com/delivery-callback';

function normalizeContacts(raw) {
  return raw
    .split(/[\n,;]+/)
    .map((x) => x.trim().replace(/\s+/g, ''))
    .filter(Boolean)
    .join(',');
}

export default function BulkSMS() {
  const [senderId, setSenderId] = useState('CAFFEE COFFE');
  const [message, setMessage] = useState(
    'Hi! Show this message at checkout for 10% off today only.'
  );
  const [contacts, setContacts] = useState('');
  const [loading, setLoading] = useState(false);

  const saveDraft = useCallback(() => {
    Swal.fire({
      ...swalBase,
      icon: 'info',
      title: 'Draft saved',
      text: 'Your draft is kept in this form until you send or leave the page.',
    });
  }, []);

  const sendSms = useCallback(async () => {
    const normalized = normalizeContacts(contacts);
    if (!senderId.trim() || !message.trim() || !normalized) {
      await Swal.fire({
        ...swalBase,
        icon: 'warning',
        title: 'Missing information',
        text: 'Sender ID, message, and at least one phone number are required.',
      });
      return;
    }

    setLoading(true);
    Swal.fire({
      ...swalBase,
      title: 'Sending…',
      allowOutsideClick: false,
      showConfirmButton: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });

    try {
      const payload = {
        senderId: senderId.trim(),
        message: message.trim(),
        contacts: normalized,
        deliveryReportUrl: DEFAULT_DELIVERY_REPORT_URL,
      };

      const res = await fetch(SMS_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      let data = {};
      try {
        data = await res.json();
      } catch {
        /* ignore */
      }

      Swal.close();

      if (!res.ok) {
        await Swal.fire({
          ...swalBase,
          icon: 'error',
          title: 'Send failed',
          text: 'Could not send the message. Please try again.',
        });
        return;
      }

      if (!data.ok) {
        await Swal.fire({
          ...swalBase,
          icon: 'error',
          title: 'Send failed',
          text: 'The SMS provider did not accept this message.',
        });
        return;
      }

      await Swal.fire({
        ...swalBase,
        icon: 'success',
        title: 'SMS sent successfully',
        text: 'Your message was sent.',
      });
    } catch (err) {
      Swal.close();
      await Swal.fire({
        ...swalBase,
        icon: 'error',
        title: 'Network error',
        text: 'Could not reach the SMS service. Check your connection and try again.',
      });
    } finally {
      setLoading(false);
    }
  }, [contacts, message, senderId]);

  return (
    <div className="card pageCard">
      <div className="cardHeader">
        <div>
          <div className="cardTitle">Bulk SMS</div>
          <div className="cardSub">Compose and send messages</div>
        </div>
      </div>
      <div className="pageBody">
        <div className="formGrid">
          <div className="field">
            <div className="fieldLabel">Sender ID</div>
            <input
              className="fieldInput"
              value={senderId}
              onChange={(e) => setSenderId(e.target.value)}
            />
            <div className="fieldHint">This name appears as the message sender.</div>
          </div>
          <div className="field fieldWide">
            <div className="fieldLabel">Phone numbers</div>
            <textarea
              className="fieldInput fieldTextarea"
              style={{ minHeight: 88 }}
              placeholder="2557XXXXXXXX, 2557YYYYYYYY (comma, semicolon, or one per line)"
              value={contacts}
              onChange={(e) => setContacts(e.target.value)}
            />
            <div className="fieldHint">Sent to Kilakona as a comma-separated list.</div>
          </div>
          <div className="field fieldWide">
            <div className="fieldLabel">Message</div>
            <textarea
              className="fieldInput fieldTextarea"
              value={message}
              onChange={(e) => setMessage(e.target.value)}
            />
          </div>
        </div>

        <div className="actions">
          <button className="btnSecondary" type="button" onClick={saveDraft}>
            Save draft
          </button>
          <button
            className="btnPrimary"
            type="button"
            onClick={sendSms}
            disabled={loading}
          >
            {loading ? 'Sending…' : 'Send SMS'}
          </button>
        </div>
      </div>
    </div>
  );
}
