import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { MovementsPage } from './MovementsPage';
import { bffFetch } from '../api/bffClient';
import { useAuth } from '../auth/useAuth';

vi.mock('../api/bffClient', async () => {
  const actual = await vi.importActual<typeof import('../api/bffClient')>('../api/bffClient');
  return { ...actual, bffFetch: vi.fn() };
});

vi.mock('../auth/useAuth', () => ({
  useAuth: vi.fn(),
}));

describe('MovementsPage', () => {
  beforeEach(() => {
    vi.mocked(bffFetch).mockReset();
    vi.mocked(useAuth).mockReturnValue({
      user: null,
      isAuthenticated: true,
      isLoading: false,
      accessToken: 'token-123',
      login: vi.fn(),
      logout: vi.fn(),
    });
  });

  it('queries the dashboard and shows the customer name and the movements', async () => {
    vi.mocked(bffFetch).mockResolvedValueOnce({
      customer: { customer_id: '1001', name: 'Ana Torres' },
      recent_movements: [
        {
          movement_id: 'm1',
          account_id: '1001',
          type: 'deposit',
          amount: 100,
          description: 'Initial deposit',
          date: '2026-07-01',
        },
      ],
    });

    render(
      <MemoryRouter>
        <MovementsPage />
      </MemoryRouter>,
    );

    fireEvent.click(screen.getByRole('button', { name: /query/i }));

    await waitFor(() => expect(screen.getByText('Ana Torres')).toBeInTheDocument());
    expect(screen.getByText(/Initial deposit/)).toBeInTheDocument();
    expect(bffFetch).toHaveBeenCalledWith('/dashboard/1001', {
      method: 'GET',
      accessToken: 'token-123',
    });
  });

  it('shows an error message if the query fails', async () => {
    const { BffError } =
      await vi.importActual<typeof import('../api/bffClient')>('../api/bffClient');
    vi.mocked(bffFetch).mockRejectedValueOnce(new BffError(404, 'not_found', 'Account not found'));

    render(
      <MemoryRouter>
        <MovementsPage />
      </MemoryRouter>,
    );

    fireEvent.click(screen.getByRole('button', { name: /query/i }));

    await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent('Account not found'));
  });
});
