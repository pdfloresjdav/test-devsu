import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { TransferPage } from './TransferPage';
import { bffFetch } from '../api/bffClient';
import { useAuth } from '../auth/useAuth';
import { getOrCreateIdempotencyKey } from '../api/idempotency';

const navigateMock = vi.fn();

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');
  return { ...actual, useNavigate: () => navigateMock };
});

vi.mock('../api/bffClient', async () => {
  const actual = await vi.importActual<typeof import('../api/bffClient')>('../api/bffClient');
  return { ...actual, bffFetch: vi.fn() };
});

vi.mock('../auth/useAuth', () => ({
  useAuth: vi.fn(),
}));

async function fillForm() {
  await userEvent.type(screen.getByLabelText(/source account/i), '1001');
  await userEvent.type(screen.getByLabelText(/destination account/i), '1002');
  await userEvent.type(screen.getByLabelText(/amount/i), '50');
}

describe('TransferPage', () => {
  const loginMock = vi.fn();

  beforeEach(() => {
    sessionStorage.clear();
    navigateMock.mockClear();
    loginMock.mockClear();
    vi.mocked(bffFetch).mockReset();
    vi.mocked(useAuth).mockReturnValue({
      user: null,
      isAuthenticated: true,
      isLoading: false,
      accessToken: 'token-123',
      login: loginMock,
      logout: vi.fn(),
    });
  });

  it('sends the generated Idempotency-Key and navigates to the confirmation once completed', async () => {
    const idempotencyKey = getOrCreateIdempotencyKey();

    vi.mocked(bffFetch).mockResolvedValueOnce({
      transfer_id: 't1',
      source_account: '1001',
      destination_account: '1002',
      amount: 50,
      status: 'completed',
    });

    render(
      <MemoryRouter>
        <TransferPage />
      </MemoryRouter>,
    );

    await fillForm();
    fireEvent.click(screen.getByRole('button', { name: /transfer/i }));

    await waitFor(() => expect(bffFetch).toHaveBeenCalled());

    expect(bffFetch).toHaveBeenCalledWith(
      '/transfers',
      expect.objectContaining({ method: 'POST', accessToken: 'token-123', idempotencyKey }),
    );
    await waitFor(() =>
      expect(navigateMock).toHaveBeenCalledWith('/transfers/confirmation', expect.any(Object)),
    );
  });

  it('reuses the same Idempotency-Key if the user retries after a network error', async () => {
    vi.mocked(bffFetch).mockRejectedValueOnce(new Error('network error'));

    render(
      <MemoryRouter>
        <TransferPage />
      </MemoryRouter>,
    );

    await fillForm();
    fireEvent.click(screen.getByRole('button', { name: /transfer/i }));
    await waitFor(() => expect(screen.getByRole('alert')).toBeInTheDocument());

    const firstKey = vi.mocked(bffFetch).mock.calls[0][1]?.idempotencyKey;

    vi.mocked(bffFetch).mockResolvedValueOnce({
      transfer_id: 't1',
      source_account: '1001',
      destination_account: '1002',
      amount: 50,
      status: 'completed',
    });

    fireEvent.click(screen.getByRole('button', { name: /transfer/i }));
    await waitFor(() => expect(bffFetch).toHaveBeenCalledTimes(2));

    const secondKey = vi.mocked(bffFetch).mock.calls[1][1]?.idempotencyKey;
    expect(secondKey).toBe(firstKey);
  });

  it('on a rejection due to reinforced authentication (step-up), redirects to re-authenticate', async () => {
    const { BffError } =
      await vi.importActual<typeof import('../api/bffClient')>('../api/bffClient');
    vi.mocked(bffFetch).mockRejectedValueOnce(
      new BffError(403, 'step_up_required', 'The operation requires reinforced authentication'),
    );

    render(
      <MemoryRouter>
        <TransferPage />
      </MemoryRouter>,
    );

    await fillForm();
    fireEvent.click(screen.getByRole('button', { name: /transfer/i }));

    await waitFor(() => expect(loginMock).toHaveBeenCalled());
  });
});
