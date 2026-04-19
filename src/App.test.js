import { render, screen } from '@testing-library/react';
import App from './App';

test('renders dashboard navigation', () => {
  window.localStorage.setItem(
    'wm_auth_v1',
    JSON.stringify({ email: 'test@example.com', userId: 1, at: Date.now() })
  );
  window.history.pushState({}, 'Dashboard', '/dashboard');
  render(<App />);
  const nav = screen.getByRole('navigation', { name: 'Sidebar' });
  expect(nav).toHaveTextContent('Dashboard');
  expect(nav).toHaveTextContent('Connected User');
  expect(nav).toHaveTextContent('Bulk SMS');
  expect(nav).toHaveTextContent('User Management');
});
