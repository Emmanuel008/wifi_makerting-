import { jsPDF } from 'jspdf';
import QRCode from 'qrcode';

const STATUS_LABELS = {
  stored: 'Stored',
  taken: 'Taken',
  pending: 'Pending',
};

const PAGE_WIDTH = 80;
const MARGIN = 5;
const LOGO_PATH = `${process.env.PUBLIC_URL || ''}/sample%201.jpeg`;
const PURPLE = [126, 58, 183];

function formatStoredTime(value) {
  if (!value) return '—';
  return value.replace('T', ' ').slice(0, 16);
}

function filenamePart(receiptNo) {
  return receiptNo.replace(/[^\w-]+/g, '-') || 'entry';
}

function statusLabel(status) {
  return STATUS_LABELS[status] || status;
}

async function loadImageDataUrl(url) {
  const response = await fetch(url);
  if (!response.ok) {
    throw new Error('Could not load receipt logo.');
  }
  const blob = await response.blob();
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = reject;
    reader.readAsDataURL(blob);
  });
}

function getImageDimensions(dataUrl) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => resolve({ width: img.width, height: img.height });
    img.onerror = reject;
    img.src = dataUrl;
  });
}

function drawDivider(doc, y, pageWidth) {
  doc.setDrawColor(225, 225, 225);
  doc.setLineWidth(0.25);
  doc.line(MARGIN, y, pageWidth - MARGIN, y);
  return y + 5;
}

function drawRow(doc, label, value, y, pageWidth) {
  const textY = y + 4.5;
  doc.setTextColor(20, 20, 20);
  doc.setFontSize(8.5);
  doc.setFont('helvetica', 'bold');
  doc.text(label, MARGIN, textY);
  doc.setFont('helvetica', 'normal');
  const valueText = String(value ?? '—').trim() || '—';
  doc.text(valueText, pageWidth - MARGIN, textY, { align: 'right', maxWidth: 42 });
  return y + 7.5;
}

function drawPriceBar(doc, price, y, pageWidth) {
  const barHeight = 9;
  doc.setFillColor(...PURPLE);
  doc.rect(0, y, pageWidth, barHeight, 'F');
  doc.setTextColor(255, 255, 255);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(10);
  doc.text('Price', MARGIN + 2, y + 6.2);
  doc.text(`${String(price).trim()}/=`, pageWidth - MARGIN - 2, y + 6.2, { align: 'right' });
  return y + barHeight + 8;
}

export async function downloadSafeCubeReceipt(form) {
  const receiptNo = form.receiptNo.trim();
  const receivedBy = form.receivedBy.trim();
  const regNo = form.regNo.trim();

  const logoDataUrl = await loadImageDataUrl(LOGO_PATH);
  const logoDims = await getImageDimensions(logoDataUrl);
  const headerHeight = PAGE_WIDTH * (logoDims.height / logoDims.width);

  const qrPayload = regNo || receiptNo || form.fullName.trim();
  const qrDataUrl = await QRCode.toDataURL(qrPayload, {
    margin: 1,
    width: 280,
    color: {
      dark: '#7E3AA7',
      light: '#FFFFFF',
    },
  });

  const qrSize = 28;
  const bodyHeight = 118;
  const pageHeight = headerHeight + bodyHeight + qrSize + 16;

  const doc = new jsPDF({
    unit: 'mm',
    format: [PAGE_WIDTH, pageHeight],
    compress: true,
  });

  doc.addImage(logoDataUrl, 'JPEG', 0, 0, PAGE_WIDTH, headerHeight);

  let y = headerHeight + 6;

  y = drawRow(doc, 'Name', form.fullName, y, PAGE_WIDTH);
  y = drawRow(doc, 'Phone', form.phone, y, PAGE_WIDTH);
  y = drawRow(doc, 'Email', form.email, y, PAGE_WIDTH);
  y = drawDivider(doc, y, PAGE_WIDTH);

  y = drawRow(doc, 'Item', form.itemName, y, PAGE_WIDTH);
  y = drawRow(doc, 'Item No', receiptNo, y, PAGE_WIDTH);
  y = drawRow(doc, 'Value', form.value, y, PAGE_WIDTH);
  y = drawRow(doc, 'Reg no', regNo, y, PAGE_WIDTH);
  y = drawDivider(doc, y, PAGE_WIDTH);

  y = drawRow(doc, 'Stored Time', formatStoredTime(form.storedTime), y, PAGE_WIDTH);
  y = drawRow(doc, 'Duration', form.duration, y, PAGE_WIDTH);
  y = drawRow(doc, 'Status', statusLabel(form.status), y, PAGE_WIDTH);
  y = drawRow(doc, 'Reg no', regNo, y, PAGE_WIDTH);
  y = drawDivider(doc, y, PAGE_WIDTH);

  y = drawRow(doc, 'Received By', receivedBy, y, PAGE_WIDTH);
  y = drawDivider(doc, y, PAGE_WIDTH);

  y = drawPriceBar(doc, form.price, y, PAGE_WIDTH);

  const qrX = (PAGE_WIDTH - qrSize) / 2;
  doc.addImage(qrDataUrl, 'PNG', qrX, y, qrSize, qrSize);

  doc.save(`safe-cube-receipt-${filenamePart(receiptNo)}.pdf`);
}
