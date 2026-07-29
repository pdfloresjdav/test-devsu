import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { TransferenciaPage } from './TransferenciaPage';
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

async function llenarFormulario() {
  await userEvent.type(screen.getByLabelText(/cuenta origen/i), '1001');
  await userEvent.type(screen.getByLabelText(/cuenta destino/i), '1002');
  await userEvent.type(screen.getByLabelText(/monto/i), '50');
}

describe('TransferenciaPage', () => {
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

  it('envía la Idempotency-Key generada y navega a la confirmación al completarse', async () => {
    const idempotencyKey = getOrCreateIdempotencyKey();

    vi.mocked(bffFetch).mockResolvedValueOnce({
      transferencia_id: 't1',
      cuenta_origen: '1001',
      cuenta_destino: '1002',
      monto: 50,
      estado: 'completada',
    });

    render(
      <MemoryRouter>
        <TransferenciaPage />
      </MemoryRouter>,
    );

    await llenarFormulario();
    fireEvent.click(screen.getByRole('button', { name: /transferir/i }));

    await waitFor(() => expect(bffFetch).toHaveBeenCalled());

    expect(bffFetch).toHaveBeenCalledWith(
      '/transferencias',
      expect.objectContaining({ method: 'POST', accessToken: 'token-123', idempotencyKey }),
    );
    await waitFor(() =>
      expect(navigateMock).toHaveBeenCalledWith('/transferencias/confirmacion', expect.any(Object)),
    );
  });

  it('reutiliza la misma Idempotency-Key si el usuario reintenta tras un error de red', async () => {
    vi.mocked(bffFetch).mockRejectedValueOnce(new Error('network error'));

    render(
      <MemoryRouter>
        <TransferenciaPage />
      </MemoryRouter>,
    );

    await llenarFormulario();
    fireEvent.click(screen.getByRole('button', { name: /transferir/i }));
    await waitFor(() => expect(screen.getByRole('alert')).toBeInTheDocument());

    const firstKey = vi.mocked(bffFetch).mock.calls[0][1]?.idempotencyKey;

    vi.mocked(bffFetch).mockResolvedValueOnce({
      transferencia_id: 't1',
      cuenta_origen: '1001',
      cuenta_destino: '1002',
      monto: 50,
      estado: 'completada',
    });

    fireEvent.click(screen.getByRole('button', { name: /transferir/i }));
    await waitFor(() => expect(bffFetch).toHaveBeenCalledTimes(2));

    const secondKey = vi.mocked(bffFetch).mock.calls[1][1]?.idempotencyKey;
    expect(secondKey).toBe(firstKey);
  });

  it('ante un rechazo por autenticación reforzada (step-up), redirige a reautenticarse', async () => {
    const { BffError } =
      await vi.importActual<typeof import('../api/bffClient')>('../api/bffClient');
    vi.mocked(bffFetch).mockRejectedValueOnce(
      new BffError(403, 'step_up_required', 'La operación requiere autenticación reforzada'),
    );

    render(
      <MemoryRouter>
        <TransferenciaPage />
      </MemoryRouter>,
    );

    await llenarFormulario();
    fireEvent.click(screen.getByRole('button', { name: /transferir/i }));

    await waitFor(() => expect(loginMock).toHaveBeenCalled());
  });
});
