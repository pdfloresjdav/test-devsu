import { describe, it, expect, jest, beforeEach } from '@jest/globals';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';
import { MovimientosScreen } from './MovimientosScreen';
import { bffFetch } from '../api/bffClient';
import { useAuth } from '../auth/useAuth';

jest.mock('../api/bffClient', () => {
  const actual = jest.requireActual('../api/bffClient') as object;
  return { ...actual, bffFetch: jest.fn() };
});

jest.mock('../auth/useAuth', () => ({
  useAuth: jest.fn(),
}));

describe('MovimientosScreen', () => {
  beforeEach(() => {
    jest.mocked(bffFetch).mockReset();
    jest.mocked(useAuth).mockReturnValue({
      isLoading: false,
      isAuthenticated: true,
      hasLinkedCredential: true,
      biometricAvailable: false,
      accessToken: 'token-123',
      request: null,
      registrarCredencial: jest.fn(async () => ({ ok: true })),
      loginRecurrente: jest.fn(async () => ({ ok: true })),
      logout: jest.fn(async () => {}),
    });
  });

  it('consulta el dashboard y muestra el nombre del cliente y los movimientos', async () => {
    jest.mocked(bffFetch).mockResolvedValueOnce({
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

    render(<MovimientosScreen onNuevaTransferencia={jest.fn()} />);

    fireEvent.press(screen.getByText('Consultar'));

    await waitFor(() => expect(screen.getByText('Ana Torres')).toBeOnTheScreen());
    expect(screen.getByText(/Depósito inicial/)).toBeOnTheScreen();
    expect(bffFetch).toHaveBeenCalledWith('/dashboard/1001', {
      method: 'GET',
      accessToken: 'token-123',
    });
  });

  it('muestra un mensaje de error si la consulta falla', async () => {
    const { BffError } = jest.requireActual(
      '../api/bffClient',
    ) as typeof import('../api/bffClient');
    jest
      .mocked(bffFetch)
      .mockRejectedValueOnce(new BffError(404, 'not_found', 'Cuenta no encontrada'));

    render(<MovimientosScreen onNuevaTransferencia={jest.fn()} />);

    fireEvent.press(screen.getByText('Consultar'));

    await waitFor(() =>
      expect(screen.getByRole('alert')).toHaveTextContent('Cuenta no encontrada'),
    );
  });
});
