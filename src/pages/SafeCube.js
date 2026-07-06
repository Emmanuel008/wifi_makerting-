import React, { useCallback, useState } from 'react';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import { FiCheck, FiDownload, FiPackage, FiUser } from 'react-icons/fi';
import { swalBase } from '../swalTheme';
import { downloadSafeCubeReceipt } from '../utils/downloadSafeCubeReceipt';

const STEPS = [
  { id: 1, label: 'Personal info', hint: 'Customer contact details', icon: FiUser },
  { id: 2, label: 'Item details', hint: 'Safe cube storage info', icon: FiPackage },
];

const STATUS_OPTIONS = [
  { value: 'stored', label: 'Stored' },
  { value: 'taken', label: 'Taken' },
  { value: 'pending', label: 'Pending' },
];

const INITIAL_FORM = {
  fullName: '',
  phone: '',
  email: '',
  itemNo: '',
  itemName: '',
  value: '',
  regNo: '',
  storedTime: '',
  duration: '',
  price: '',
  status: 'stored',
};

const INITIAL_RECEIPT = {
  receiptNo: '',
  receivedBy: '',
};

function validateStep(step, form) {
  if (step === 1) {
    if (!form.fullName.trim()) return 'Full name is required.';
    if (!form.phone.trim()) return 'Phone number is required.';
    if (!/\d/.test(form.phone)) return 'Enter a valid phone number.';
    if (!form.email.trim()) return 'Email is required.';
    if (!form.email.includes('@')) return 'Enter a valid email address.';
    return null;
  }

  if (!form.itemNo.trim()) return 'Item No is required.';
  const itemCount = Number(form.itemNo);
  if (!Number.isInteger(itemCount) || itemCount < 1) {
    return 'Item No must be the number of items received (at least 1).';
  }
  if (!form.itemName.trim()) return 'Item Name is required.';
  if (!form.value.trim()) return 'Value is required.';
  if (!form.regNo.trim()) return 'Reg. No. is required.';
  if (!form.storedTime) return 'Stored Time is required.';
  if (!form.duration.trim()) return 'Duration is required.';
  if (!form.price.trim()) return 'Price is required.';
  if (!form.status) return 'Status is required.';
  return null;
}

function validateReceipt(receipt) {
  if (!receipt.receiptNo.trim()) return 'Receipt No. is required.';
  if (!receipt.receivedBy.trim()) return 'Received by is required.';
  return null;
}

function statusLabel(status) {
  return STATUS_OPTIONS.find((option) => option.value === status)?.label || status;
}

export default function SafeCube() {
  const [step, setStep] = useState(1);
  const [form, setForm] = useState(INITIAL_FORM);
  const [savedEntry, setSavedEntry] = useState(null);
  const [receipt, setReceipt] = useState(INITIAL_RECEIPT);
  const [submitting, setSubmitting] = useState(false);

  const updateField = useCallback((field, value) => {
    setForm((prev) => ({ ...prev, [field]: value }));
  }, []);

  const updateReceiptField = useCallback((field, value) => {
    setReceipt((prev) => ({ ...prev, [field]: value }));
  }, []);

  const startNewEntry = useCallback(() => {
    setSavedEntry(null);
    setReceipt(INITIAL_RECEIPT);
    setForm(INITIAL_FORM);
    setStep(1);
  }, []);

  const goNext = useCallback(async () => {
    const error = validateStep(step, form);
    if (error) {
      await Swal.fire({ ...swalBase, icon: 'warning', title: 'Missing information', text: error });
      return;
    }
    setStep((current) => Math.min(current + 1, STEPS.length));
  }, [form, step]);

  const goBack = useCallback(() => {
    setStep((current) => Math.max(current - 1, 1));
  }, []);

  const handleSubmit = useCallback(async (event) => {
    event.preventDefault();
    const error = validateStep(2, form);
    if (error) {
      await Swal.fire({ ...swalBase, icon: 'warning', title: 'Missing information', text: error });
      return;
    }

    setSubmitting(true);
    try {
      setSavedEntry({ ...form });
      setReceipt(INITIAL_RECEIPT);
      await Swal.fire({
        ...swalBase,
        icon: 'success',
        title: 'Safe cube entry saved',
        html: `
          <div style="text-align:left;font-size:13px;line-height:1.6">
            <strong>${form.fullName.trim()}</strong><br />
            Item: ${form.itemName.trim()} · ${form.itemNo.trim()} item(s) received<br />
            Price: ${form.price.trim()}<br />
            Status: ${statusLabel(form.status)}<br /><br />
            You can now download the receipt.
          </div>
        `,
      });
    } finally {
      setSubmitting(false);
    }
  }, [form]);

  const handleDownloadReceipt = useCallback(async () => {
    if (!savedEntry) {
      await Swal.fire({
        ...swalBase,
        icon: 'info',
        title: 'Save entry first',
        text: 'Complete and save the Safe Cube details before downloading a receipt.',
      });
      return;
    }

    const error = validateReceipt(receipt);
    if (error) {
      await Swal.fire({ ...swalBase, icon: 'warning', title: 'Receipt details required', text: error });
      return;
    }

    try {
      await downloadSafeCubeReceipt({ ...savedEntry, ...receipt });
      await Swal.fire({
        ...swalBase,
        icon: 'success',
        title: 'Receipt downloaded',
        text: 'Your Safe Cube receipt PDF has been downloaded.',
        timer: 2200,
        showConfirmButton: false,
      });
    } catch (err) {
      await Swal.fire({
        ...swalBase,
        icon: 'error',
        title: 'Download failed',
        text: err?.message || 'Could not generate the receipt PDF.',
      });
    }
  }, [receipt, savedEntry]);

  return (
    <div className="card pageCard">
      <div className="cardHeader">
        <div>
          <div className="cardTitle">Safe Cube</div>
          <div className="cardSub">Register stored items with a guided step-by-step form</div>
        </div>
      </div>

      <div className="pageBody">
        {!savedEntry ? (
          <>
            <div className="stepper" aria-label="Safe cube form progress">
              {STEPS.map((item, index) => {
                const Icon = item.icon;
                const isComplete = step > item.id;
                const isActive = step === item.id;

                return (
                  <React.Fragment key={item.id}>
                    <div
                      className={`stepperStep ${isActive ? 'isActive' : ''} ${isComplete ? 'isComplete' : ''}`}
                    >
                      <div className="stepperMarker" aria-hidden="true">
                        {isComplete ? <FiCheck /> : <Icon />}
                      </div>
                      <div className="stepperCopy">
                        <div className="stepperLabel">{item.label}</div>
                        <div className="stepperHint">{item.hint}</div>
                      </div>
                    </div>
                    {index < STEPS.length - 1 ? (
                      <div className={`stepperLine ${step > item.id ? 'isComplete' : ''}`} aria-hidden="true" />
                    ) : null}
                  </React.Fragment>
                );
              })}
            </div>

            <form className="stepperForm" onSubmit={handleSubmit}>
              {step === 1 ? (
                <div className="formGrid">
                  <div className="field fieldWide">
                    <div className="fieldLabel">Full name</div>
                    <input
                      className="fieldInput"
                      value={form.fullName}
                      onChange={(e) => updateField('fullName', e.target.value)}
                      placeholder="John Doe"
                      autoComplete="name"
                      disabled={submitting}
                    />
                  </div>
                  <div className="field">
                    <div className="fieldLabel">Phone number</div>
                    <input
                      className="fieldInput"
                      value={form.phone}
                      onChange={(e) => updateField('phone', e.target.value)}
                      placeholder="0625313162"
                      autoComplete="tel"
                      disabled={submitting}
                    />
                  </div>
                  <div className="field">
                    <div className="fieldLabel">Email</div>
                    <input
                      className="fieldInput"
                      type="email"
                      value={form.email}
                      onChange={(e) => updateField('email', e.target.value)}
                      placeholder="name@example.com"
                      autoComplete="email"
                      disabled={submitting}
                    />
                  </div>
                </div>
              ) : (
                <div className="formGrid">
                  <div className="field">
                    <div className="fieldLabel">Item No</div>
                    <input
                      className="fieldInput"
                      type="number"
                      min="1"
                      step="1"
                      inputMode="numeric"
                      value={form.itemNo}
                      onChange={(e) => updateField('itemNo', e.target.value)}
                      placeholder="1"
                      disabled={submitting}
                    />
                    <div className="fieldHint">How many items have you received?</div>
                  </div>
                  <div className="field">
                    <div className="fieldLabel">Item Name</div>
                    <input
                      className="fieldInput"
                      value={form.itemName}
                      onChange={(e) => updateField('itemName', e.target.value)}
                      placeholder="Laptop bag"
                      disabled={submitting}
                    />
                  </div>
                  <div className="field">
                    <div className="fieldLabel">Value</div>
                    <input
                      className="fieldInput"
                      value={form.value}
                      onChange={(e) => updateField('value', e.target.value)}
                      placeholder="250,000 TZS"
                      disabled={submitting}
                    />
                  </div>
                  <div className="field">
                    <div className="fieldLabel">Reg. No.</div>
                    <input
                      className="fieldInput"
                      value={form.regNo}
                      onChange={(e) => updateField('regNo', e.target.value)}
                      placeholder="REG-2026-014"
                      disabled={submitting}
                    />
                  </div>
                  <div className="field">
                    <div className="fieldLabel">Stored Time</div>
                    <input
                      className="fieldInput"
                      type="datetime-local"
                      value={form.storedTime}
                      onChange={(e) => updateField('storedTime', e.target.value)}
                      disabled={submitting}
                    />
                  </div>
                  <div className="field">
                    <div className="fieldLabel">Duration</div>
                    <input
                      className="fieldInput"
                      value={form.duration}
                      onChange={(e) => updateField('duration', e.target.value)}
                      placeholder="24 hours"
                      disabled={submitting}
                    />
                  </div>
                  <div className="field">
                    <div className="fieldLabel">Price</div>
                    <input
                      className="fieldInput"
                      value={form.price}
                      onChange={(e) => updateField('price', e.target.value)}
                      placeholder="5,000 TZS"
                      disabled={submitting}
                    />
                    <div className="fieldHint">Amount paid for safe cube storage.</div>
                  </div>
                  <div className="field">
                    <div className="fieldLabel">Status</div>
                    <select
                      className="fieldInput fieldSelect"
                      value={form.status}
                      onChange={(e) => updateField('status', e.target.value)}
                      disabled={submitting}
                    >
                      {STATUS_OPTIONS.map((option) => (
                        <option key={option.value} value={option.value}>
                          {option.label}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>
              )}

              <div className="stepperMeta muted">
                Step {step} of {STEPS.length}
              </div>

              <div className="actions stepperActions">
                {step > 1 ? (
                  <button className="btnSecondary" type="button" onClick={goBack} disabled={submitting}>
                    Back
                  </button>
                ) : (
                  <span />
                )}

                {step < STEPS.length ? (
                  <button className="btnPrimary" type="button" onClick={goNext} disabled={submitting}>
                    Continue
                  </button>
                ) : (
                  <button className="btnPrimary" type="submit" disabled={submitting}>
                    {submitting ? 'Saving…' : 'Save entry'}
                  </button>
                )}
              </div>
            </form>
          </>
        ) : (
          <div className="savedEntryPanel">
            <div className="savedEntryBanner">
              <FiCheck aria-hidden="true" />
              <span>Entry saved. Download the receipt below.</span>
            </div>

            <div className="savedEntrySummary">
              <div><strong>{savedEntry.fullName}</strong></div>
              <div className="muted">
                {savedEntry.itemName} · {savedEntry.itemNo} item(s) · {savedEntry.price} · {statusLabel(savedEntry.status)}
              </div>
            </div>

            <div className="receiptSection">
              <div className="receiptSectionTitle">Download receipt</div>
              <div className="formGrid">
                <div className="field">
                  <div className="fieldLabel">Receipt No.</div>
                  <input
                    className="fieldInput"
                    value={receipt.receiptNo}
                    onChange={(e) => updateReceiptField('receiptNo', e.target.value)}
                    placeholder="RCP-2026-001"
                  />
                </div>
                <div className="field">
                  <div className="fieldLabel">Received by</div>
                  <input
                    className="fieldInput"
                    value={receipt.receivedBy}
                    onChange={(e) => updateReceiptField('receivedBy', e.target.value)}
                    placeholder="Staff or customer name"
                  />
                </div>
              </div>

              <div className="actions receiptActions">
                <button className="btnSecondary" type="button" onClick={startNewEntry}>
                  New entry
                </button>
                <button className="btnPrimary" type="button" onClick={handleDownloadReceipt}>
                  <span className="btnWithIcon">
                    <FiDownload aria-hidden="true" />
                    Download receipt
                  </span>
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
