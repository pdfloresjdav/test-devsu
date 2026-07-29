import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { MovimientosPage } from './MovimientosPage';
import { bffFetch } from '../api/bffClient';
import { useAuth } from '../auth/useAuth';

vi.mock('../api/bffClient', async () => {
  const actual = await vi.importActual<typeof import('../api/bffClient')>('../api/bffClient');
  return { ...actual, bffFetch: vi.fn() };
});

vi.mock('../auth/useAuth', () => ({
  useAuth: vi.fn(),
}));

describe('MovimientosPage', () => {
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

  it('consulta el dashboard y muestra el nombre del cliente y los movimientos', async () => {
    vi.mocked(bffFetch).mockResolvedValueOnce({
      cliente: { cliente_id: '1001', nombre: 'Ana Torres' },
      movimientos_recientes: [
        {
          movimiento_id: 'm1',
          cuenta_id: '1001',
          tipo: 'deposito',
          monto: 100,
          descripcion: 'Depósito inicial',
          fecha: '2026-07-01',
        },
      ],
    });

    render(
      <MemoryRouter>
        <MovimientosPage />
      </MemoryRouter>,
    );

    fireEvent.click(screen.getByRole('button', { name: /consultar/i }));

    await waitFor(() => expect(screen.getByText('Ana Torres')).toBeInTheDocument());
    expect(screen.getByText(/Depósito inicial/)).toBeInTheDocument();
    expect(bffFetch).toHaveBeenCalledWith('/dashboard/1001', {
      method: 'GET',
      accessToken: 'token-123',
    });
  });

  it('muestra un mensaje de error si la consulta falla', async () => {
    const { BffError } =
      await vi.importActual<typeof import('../api/bffClient')>('../api/bffClient');
    vi.mocked(bffFetch).mockRejectedValueOnce(
      new BffError(404, 'not_found', 'Cuenta no encontrada'),
    );

    render(
      <MemoryRouter>
        <MovimientosPage />
      </MemoryRouter>,
    );

    fireEvent.click(screen.getByRole('button', { name: /consultar/i }));

    await waitFor(() =>
      expect(screen.getByRole('alert')).toHaveTextContent('Cuenta no encontrada'),
    );
  });
});
