import { describe, it, expect, jest, beforeEach } from '@jest/globals';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';
import { MovementsScreen } from './MovementsScreen';
import { bffFetch } from '../api/bffClient';
import { useAuth } from '../auth/useAuth';

jest.mock('../api/bffClient', () => {
  const actual = jest.requireActual('../api/bffClient') as object;
  return { ...actual, bffFetch: jest.fn() };
});

jest.mock('../auth/useAuth', () => ({
  useAuth: jest.fn(),
}));

describe('MovementsScreen', () => {
  beforeEach(() => {
    jest.mocked(bffFetch).mockReset();
    jest.mocked(useAuth).mockReturnValue({
      isLoading: false,
      isAuthenticated: true,
      hasLinkedCredential: true,
      biometricAvailable: false,
      accessToken: 'token-123',
      request: null,
      registerCredential: jest.fn(async () => ({ ok: true })),
      recurringLogin: jest.fn(async () => ({ ok: true })),
      logout: jest.fn(async () => {}),
    });
  });

  it('queries the dashboard and shows the customer name and the movements', async () => {
    jest.mocked(bffFetch).mockResolvedValueOnce({
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

    render(<MovementsScreen onNewTransfer={jest.fn()} />);

    fireEvent.press(screen.getByText('Query'));

    await waitFor(() => expect(screen.getByText('Ana Torres')).toBeOnTheScreen());
    expect(screen.getByText(/Initial deposit/)).toBeOnTheScreen();
    expect(bffFetch).toHaveBeenCalledWith('/dashboard/1001', {
      method: 'GET',
      accessToken: 'token-123',
    });
  });

  it('shows an error message if the query fails', async () => {
    const { BffError } = jest.requireActual(
      '../api/bffClient',
    ) as typeof import('../api/bffClient');
    jest
      .mocked(bffFetch)
      .mockRejectedValueOnce(new BffError(404, 'not_found', 'Account not found'));

    render(<MovementsScreen onNewTransfer={jest.fn()} />);

    fireEvent.press(screen.getByText('Query'));

    await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent('Account not found'));
  });
});
