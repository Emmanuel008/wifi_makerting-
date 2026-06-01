import * as XLSX from 'xlsx';

function formatMinutes(min) {
  if (!min) return '8 hr';
  const h = Math.floor(min / 60);
  const m = min % 60;
  if (h === 0) return `${m} min`;
  if (m === 0) return `${h} hr`;
  return `${h}h ${m}m`;
}

function filenamePart(value) {
  if (!value) return 'all';
  return value.replace(/[:T]/g, '-').replace(/\.\d{3}Z$/, '').slice(0, 19);
}

export function downloadConnectedUsersXlsx(rows, fromValue, toValue) {
  const sheetRows = rows.map((row) => ({
    'Phone number': row.phone || '',
    'MAC address': row.mac_address || '',
    'IP address': row.ip_address || '',
    Status: row.is_active ? 'Active' : 'Disconnected',
    'Session started': row.session_started_at || '',
    'Time limit': formatMinutes(row.session_minutes),
    Registrations: row.registration_count ?? '',
    'Registered at': row.created_at || '',
    'Last updated': row.updated_at || '',
  }));

  const worksheet = XLSX.utils.json_to_sheet(sheetRows);
  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, worksheet, 'Connected Users');

  const filename = `connected-users_${filenamePart(fromValue)}_${filenamePart(toValue)}.xlsx`;
  XLSX.writeFile(workbook, filename);
}
