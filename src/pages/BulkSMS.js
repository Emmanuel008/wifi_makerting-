import React, { useCallback, useMemo, useState } from 'react';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import { swalBase } from '../swalTheme';
import { sendSmsJson, sendSmsWithFile } from '../api/sendSms';

function normalizeContacts(raw) {
  return raw
    .split(/[\n,;]+/)
    .map((x) => x.trim().replace(/\s+/g, ''))
    .filter(Boolean)
    .filter((x) => /\d/.test(x))
    .join(',');
}

function parseContactsFromSheetText(text) {
  return String(text || '')
    .split(/[\n,;\t\r]+/)
    .map((x) => x.trim())
    .filter(Boolean)
    .filter((x) => /\d/.test(x));
}

function countContacts(raw) {
  const normalized = normalizeContacts(raw);
  return normalized ? normalized.split(',').length : 0;
}

export default function BulkSMS() {
  const [senderId, setSenderId] = useState('NILETEE');
  const [message, setMessage] = useState(
    'Hi! Show this message at checkout for 10% off today only.'
  );
  const [contacts, setContacts] = useState('');
  const [contactsFile, setContactsFile] = useState(null);
  const [contactsFileName, setContactsFileName] = useState('');
  const [fileContactCount, setFileContactCount] = useState(0);
  const [loading, setLoading] = useState(false);

  const manualContactCount = useMemo(() => countContacts(contacts), [contacts]);
  const smsParts = useMemo(
    () => Math.max(1, Math.ceil(message.trim().length / 160)),
    [message]
  );

  const onSheetUpload = useCallback(async (e) => {
    const file = e.target.files && e.target.files[0];
    if (!file) return;
    const fileName = String(file.name || '').toLowerCase();
    if (!fileName.endsWith('.csv') && !fileName.endsWith('.txt')) {
      await Swal.fire({
        ...swalBase,
        icon: 'warning',
        title: 'Unsupported file',
        text: 'Please upload a CSV or TXT file with phone numbers in column A.',
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

      setContactsFile(file);
      setContactsFileName(file.name);
      setFileContactCount(parsed.length);

      await Swal.fire({
        ...swalBase,
        icon: 'success',
        title: 'Sheet attached',
        text: `${parsed.length} number(s) found in file and ready for multipart upload.`,
      });
    } finally {
      e.target.value = '';
    }
  }, []);

  const clearContactsFile = useCallback(() => {
    setContactsFile(null);
    setContactsFileName('');
    setFileContactCount(0);
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
    const trimmedSenderId = senderId.trim();
    const trimmedMessage = message.trim();

    if (!trimmedSenderId || !trimmedMessage) {
      await Swal.fire({
        ...swalBase,
        icon: 'warning',
        title: 'Missing information',
        text: 'Sender ID and message are required.',
      });
      return;
    }

    if (!contactsFile && !normalized) {
      await Swal.fire({
        ...swalBase,
        icon: 'warning',
        title: 'Missing phone numbers',
        text: 'Add phone numbers manually or upload a CSV/TXT sheet.',
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
      let data;

      if (contactsFile) {
        data = await sendSmsWithFile({
          senderId: trimmedSenderId,
          message: trimmedMessage,
          contactsFile,
          contacts: normalized || undefined,
        });
      } else {
        data = await sendSmsJson({
          senderId: trimmedSenderId,
          message: trimmedMessage,
          contacts: normalized,
        });
      }

      const isSuccess = Boolean(data?.success ?? data?.sucess);
      if (!isSuccess) {
        throw new Error(data?.error || data?.message || 'Failed to send SMS.');
      }

      const recipientCount = data.recipients ?? (contactsFile
        ? fileContactCount + (normalized ? manualContactCount : 0)
        : manualContactCount);

      Swal.close();

      await Swal.fire({
        ...swalBase,
        icon: 'success',
        title: 'SMS sent successfully',
        text: `Sent to ${recipientCount} recipient(s).`,
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
  }, [contacts, contactsFile, fileContactCount, manualContactCount, message, senderId]);

  return (
    <div className="card pageCard">
      <div className="cardHeader">
        <div>
          <div className="cardTitle">Bulk SMS</div>
          <div className="cardSub">
            Typed numbers send as JSON. An attached sheet sends as multipart/form-data.
          </div>
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
              disabled={loading}
            />
            <div className="fieldHint">This name appears as the message sender (e.g. NILETEE).</div>
          </div>
          <div className="field fieldWide">
            <div className="fieldLabel">Phone numbers</div>
            <textarea
              className="fieldInput fieldTextarea"
              style={{ minHeight: 88 }}
              placeholder="0625313162, 0656121885 (comma, semicolon, or one per line)"
              value={contacts}
              onChange={(e) => setContacts(e.target.value)}
              disabled={loading}
            />
            <div className="fieldHint">
              {manualContactCount > 0
                ? `${manualContactCount} number(s) ready.`
                : 'Add numbers manually or upload a CSV/TXT sheet.'}
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
            {contactsFileName ? (
              <div className="fieldRow" style={{ marginTop: 8 }}>
                <div className="fieldHint">
                  Attached for multipart send: <strong>{contactsFileName}</strong>
                  {fileContactCount > 0 ? ` (${fileContactCount} number(s) in column A)` : ''}
                </div>
                <button className="linkBtn" type="button" onClick={clearContactsFile} disabled={loading}>
                  Remove file
                </button>
              </div>
            ) : (
              <div className="fieldHint">
                Supported formats: `.csv` and `.txt`. Numbers in column A are sent via multipart upload.
              </div>
            )}
          </div>
          <div className="field fieldWide">
            <div className="fieldLabel">Message</div>
            <textarea
              className="fieldInput fieldTextarea"
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              disabled={loading}
            />
            <div className="fieldHint">
              {message.trim().length} characters · estimated {smsParts} SMS part(s)
            </div>
          </div>
        </div>

        <div className="actions">
          <button className="btnSecondary" type="button" onClick={saveDraft} disabled={loading}>
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
