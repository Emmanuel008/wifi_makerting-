import React from 'react';
import './App.css';
import AppShell from './components/AppShell';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './components/Auth';
import Login from './pages/Login';
import CaptiveGuest from './pages/CaptiveGuest';

function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/wifi" element={<CaptiveGuest />} />
          <Route path="/" element={<Login />} />
          <Route path="/login" element={<Navigate to="/" replace />} />
          <Route path="/*" element={<AppShell />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}

export default App;
