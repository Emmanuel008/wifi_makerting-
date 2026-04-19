import React from 'react';
import { useSearchParams } from 'react-router-dom';
import CaptivePortalForm from '../components/CaptivePortalForm';

export default function Portal({ onBack }) {
  const [searchParams] = useSearchParams();
  return (
    <div className="portal">
      <CaptivePortalForm searchParams={searchParams} embedded onBack={onBack} />
    </div>
  );
}

