import axios from 'axios';

const SMS_API_BASE_URL =
  process.env.REACT_APP_SMS_API_BASE_URL || 'https://wifi.jitihada.co.tz';

const smsClient = axios.create({
  baseURL: SMS_API_BASE_URL,
  headers: {
    Accept: 'application/json',
  },
  timeout: 60000,
});

/**
 * POST /api/send-sms
 * JSON body: { senderId, message, contacts }
 */
export async function sendSmsJson(payload) {
  const { data } = await smsClient.post('/api/send-sms', payload, {
    headers: {
      'Content-Type': 'application/json',
    },
  });
  return data;
}

/**
 * Build multipart/form-data for file upload:
 * senderId, message, contactsFile (+ optional contacts)
 */
export function buildSendSmsFormData({ senderId, message, contactsFile, contacts }) {
  const formData = new FormData();
  formData.append('senderId', senderId);
  formData.append('message', message);
  formData.append(
    'contactsFile',
    contactsFile,
    contactsFile.name || 'contacts.csv'
  );
  if (contacts) {
    formData.append('contacts', contacts);
  }
  return formData;
}

/**
 * POST /api/send-sms
 * Content-Type: multipart/form-data (boundary set by the browser)
 */
export async function sendSmsWithFile({ senderId, message, contactsFile, contacts }) {
  const formData = buildSendSmsFormData({ senderId, message, contactsFile, contacts });

  const { data } = await smsClient.post('/api/send-sms', formData, {
    transformRequest: [(payload, headers) => {
      if (payload instanceof FormData) {
        delete headers['Content-Type'];
      }
      return payload;
    }],
  });
  return data;
}
