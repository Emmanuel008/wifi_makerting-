import React, { useCallback, useState } from 'react';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import { swalBase } from '../swalTheme';
import client from '../api/client';

function normalizeContacts(raw) {
  return raw
    .split(/[\n,;]+/)
    .map((x) => x.trim().replace(/\s+/g, ''))
    .filter(Boolean)
    .join(',');
}

function parseContactsFromSheetText(text) {
  return String(text || '')
    .split(/[\n,;\t\r]+/)
    .map((x) => x.trim())
    .filter(Boolean)
    .filter((x) => /\d/.test(x));
}

export default function BulkSMS() {
  const [senderId, setSenderId] = useState('NILETEE');
  const [message, setMessage] = useState(
    'Hi! Show this message at checkout for 10% off today only.'
  );
  const [contacts, setContacts] = useState('');
  const [loading, setLoading] = useState(false);

  const onSheetUpload = useCallback(async (e) => {
    const file = e.target.files && e.target.files[0];
    if (!file) return;
    const fileName = String(file.name || '').toLowerCase();
    if (!fileName.endsWith('.csv') && !fileName.endsWith('.txt')) {
      await Swal.fire({
        ...swalBase,
        icon: 'warning',
        title: 'Unsupported file',
        text: 'Please upload a CSV or TXT file with phone numbers.',
      });
      e.target.value = '';
      return;
    }

    try {
      const text = await file.text();
      const parsed = parseContactsFromSheetText(text);
      if (parsed.length === 0) {
        await Swal.fire({
          ...swalBase,
          icon: 'warning',
          title: 'No phone numbers found',
          text: 'The uploaded file did not contain valid phone numbers.',
        });
        return;
      }

      setContacts((prev) => {
        const merged = [...parseContactsFromSheetText(prev), ...parsed];
        const unique = Array.from(new Set(merged));
        return unique.join(',');
      });

      await Swal.fire({
        ...swalBase,
        icon: 'success',
        title: 'Sheet imported',
        text: `${parsed.length} number(s) imported from file.`,
      });
    } finally {
      e.target.value = '';
    }
  }, []);

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
      };

      const { data } = await client.post('/api/send-sms', payload);
      const isSuccess = Boolean(data?.success ?? data?.sucess);
      if (!isSuccess) {
        throw new Error(data?.error || data?.message || 'Failed to send SMS.');
      }
      Swal.close();

      await Swal.fire({
        ...swalBase,
        icon: 'success',
        title: 'SMS sent successfully',
        text: `Prepared ${payload.contacts.split(',').length} recipient(s) for sending.`,
      });
    } catch (error) {
      Swal.close();
      const apiMessage =
        error?.response?.data?.error ||
        error?.response?.data?.message ||
        error?.message ||
        'An unexpected error occurred while sending SMS.';
      await Swal.fire({
        ...swalBase,
        icon: 'error',
        title: 'Failed to send SMS',
        text: apiMessage,
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
            <div className="fieldHint">
              Add manually or upload a CSV/TXT sheet. Numbers are merged and duplicates are removed.
            </div>
          </div>
          <div className="field fieldWide">
            <div className="fieldLabel">Upload numbers sheet</div>
            <input
              className="fieldInput"
              type="file"
              accept=".csv,.txt"
              onChange={onSheetUpload}
              disabled={loading}
            />
            <div className="fieldHint">Supported formats: `.csv` and `.txt`.</div>
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
