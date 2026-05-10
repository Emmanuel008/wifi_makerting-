import axios from 'axios';
import API_BASE_URL from './api';

const client = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  timeout: 15000,
});

export function setAuthToken(token) {
  if (!token) return;
  client.defaults.headers.common.Authorization = `Bearer ${token}`;
}

export function clearAuthToken() {
  delete client.defaults.headers.common.Authorization;
}

export default client;
