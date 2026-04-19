import React from 'react';
import { useSearchParams } from 'react-router-dom';
import CaptivePortalForm from '../components/CaptivePortalForm';

export default function CaptiveGuest() {
  const [searchParams] = useSearchParams();
  return (
    <div className="portal">
      <CaptivePortalForm searchParams={searchParams} embedded={false} />
    </div>
  );
}
