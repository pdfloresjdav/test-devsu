import { describe, it, expect, jest, beforeEach } from '@jest/globals';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { TransferenciaScreen } from './TransferenciaScreen';
import { bffFetch } from '../api/bffClient';
import { useAuth } from '../auth/useAuth';
import { getOrCreateIdempotencyKey } from '../storage/idempotency';

jest.mock('@react-native-async-storage/async-storage', () =>
  jest.requireActual('@react-native-async-storage/async-storage/jest/async-storage-mock'),
);

let mockUuidCounter = 0;
jest.mock('expo-crypto', () => ({
  randomUUID: jest.fn(() => `test-uuid-${mockUuidCounter++}`),
}));

jest.mock('../api/bffClient', () => {
  const actual = jest.requireActual('../api/bffClient') as object;
  return { ...actual, bffFetch: jest.fn() };
});

jest.mock('../auth/useAuth', () => ({
  useAuth: jest.fn(),
}));

async function llenarFormulario() {
  fireEvent.changeText(screen.getByTestId('input-cuenta-origen'), '1001');
  fireEvent.changeText(screen.getByTestId('input-cuenta-destino'), '1002');
  fireEvent.changeText(screen.getByTestId('input-monto'), '50');
}

describe('TransferenciaScreen', () => {
  const registrarCredencial = jest.fn(async () => ({ ok: true }));

  beforeEach(async () => {
    await AsyncStorage.clear();
    jest.mocked(bffFetch).mockReset();
    registrarCredencial.mockClear();
    jest.mocked(useAuth).mockReturnValue({
      isLoading: false,
      isAuthenticated: true,
      hasLinkedCredential: true,
      biometricAvailable: false,
      accessToken: 'token-123',
      request: null,
      registrarCredencial,
      loginRecurrente: jest.fn(async () => ({ ok: true })),
      logout: jest.fn(async () => {}),
    });
  });

  it('envía la Idempotency-Key generada y llama a onCompletada al completarse', async () => {
    const idempotencyKey = await getOrCreateIdempotencyKey();

    jest.mocked(bffFetch).mockResolvedValueOnce({
      transferencia_id: 't1',
      cuenta_origen: '1001',
      cuenta_destino: '1002',
      monto: 50,
      estado: 'completada',
    });

    const onCompletada = jest.fn();
    render(<TransferenciaScreen onCompletada={onCompletada} />);

    await llenarFormulario();
    fireEvent.press(screen.getByText('Transferir'));

    await waitFor(() => expect(onCompletada).toHaveBeenCalled());

    expect(bffFetch).toHaveBeenCalledWith(
      '/transferencias',
      expect.objectContaining({ method: 'POST', accessToken: 'token-123', idempotencyKey }),
    );
  });

  it('reutiliza la misma Idempotency-Key si el usuario reintenta tras un error de red', async () => {
    jest.mocked(bffFetch).mockRejectedValueOnce(new Error('network error'));

    render(<TransferenciaScreen onCompletada={jest.fn()} />);

    await llenarFormulario();
    fireEvent.press(screen.getByText('Transferir'));
    await waitFor(() => expect(screen.getByRole('alert')).toBeOnTheScreen());

    const firstKey = (jest.mocked(bffFetch).mock.calls[0][1] as { idempotencyKey: string })
      .idempotencyKey;

    jest.mocked(bffFetch).mockResolvedValueOnce({
      transferencia_id: 't1',
      cuenta_origen: '1001',
      cuenta_destino: '1002',
      monto: 50,
      estado: 'completada',
    });

    fireEvent.press(screen.getByText('Transferir'));
    await waitFor(() => expect(bffFetch).toHaveBeenCalledTimes(2));

    const secondKey = (jest.mocked(bffFetch).mock.calls[1][1] as { idempotencyKey: string })
      .idempotencyKey;
    expect(secondKey).toBe(firstKey);
  });

  it('ante un rechazo por step_up_required, dispara registrarCredencial (login PKCE completo)', async () => {
    const { BffError } = jest.requireActual(
      '../api/bffClient',
    ) as typeof import('../api/bffClient');
    jest
      .mocked(bffFetch)
      .mockRejectedValueOnce(
        new BffError(
          403,
          'step_up_required',
          'Esta transferencia requiere autenticación reforzada.',
        ),
      );

    render(<TransferenciaScreen onCompletada={jest.fn()} />);

    await llenarFormulario();
    fireEvent.press(screen.getByText('Transferir'));

    await waitFor(() => expect(registrarCredencial).toHaveBeenCalled());
  });
});
