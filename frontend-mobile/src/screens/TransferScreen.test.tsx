import { describe, it, expect, jest, beforeEach } from '@jest/globals';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { TransferScreen } from './TransferScreen';
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

async function fillForm() {
  fireEvent.changeText(screen.getByTestId('input-source-account'), '1001');
  fireEvent.changeText(screen.getByTestId('input-destination-account'), '1002');
  fireEvent.changeText(screen.getByTestId('input-amount'), '50');
}

describe('TransferScreen', () => {
  const registerCredential = jest.fn(async () => ({ ok: true }));

  beforeEach(async () => {
    await AsyncStorage.clear();
    jest.mocked(bffFetch).mockReset();
    registerCredential.mockClear();
    jest.mocked(useAuth).mockReturnValue({
      isLoading: false,
      isAuthenticated: true,
      hasLinkedCredential: true,
      biometricAvailable: false,
      accessToken: 'token-123',
      request: null,
      registerCredential,
      recurringLogin: jest.fn(async () => ({ ok: true })),
      logout: jest.fn(async () => {}),
    });
  });

  it('sends the generated Idempotency-Key and calls onCompleted once completed', async () => {
    const idempotencyKey = await getOrCreateIdempotencyKey();

    jest.mocked(bffFetch).mockResolvedValueOnce({
      transfer_id: 't1',
      source_account: '1001',
      destination_account: '1002',
      amount: 50,
      status: 'completed',
    });

    const onCompleted = jest.fn();
    render(<TransferScreen onCompleted={onCompleted} />);

    await fillForm();
    fireEvent.press(screen.getByText('Transfer'));

    await waitFor(() => expect(onCompleted).toHaveBeenCalled());

    expect(bffFetch).toHaveBeenCalledWith(
      '/transfers',
      expect.objectContaining({ method: 'POST', accessToken: 'token-123', idempotencyKey }),
    );
  });

  it('reuses the same Idempotency-Key if the user retries after a network error', async () => {
    jest.mocked(bffFetch).mockRejectedValueOnce(new Error('network error'));

    render(<TransferScreen onCompleted={jest.fn()} />);

    await fillForm();
    fireEvent.press(screen.getByText('Transfer'));
    await waitFor(() => expect(screen.getByRole('alert')).toBeOnTheScreen());

    const firstKey = (jest.mocked(bffFetch).mock.calls[0][1] as { idempotencyKey: string })
      .idempotencyKey;

    jest.mocked(bffFetch).mockResolvedValueOnce({
      transfer_id: 't1',
      source_account: '1001',
      destination_account: '1002',
      amount: 50,
      status: 'completed',
    });

    fireEvent.press(screen.getByText('Transfer'));
    await waitFor(() => expect(bffFetch).toHaveBeenCalledTimes(2));

    const secondKey = (jest.mocked(bffFetch).mock.calls[1][1] as { idempotencyKey: string })
      .idempotencyKey;
    expect(secondKey).toBe(firstKey);
  });

  it('on a step_up_required rejection, triggers registerCredential (full PKCE login)', async () => {
    const { BffError } = jest.requireActual(
      '../api/bffClient',
    ) as typeof import('../api/bffClient');
    jest
      .mocked(bffFetch)
      .mockRejectedValueOnce(
        new BffError(403, 'step_up_required', 'This transfer requires reinforced authentication.'),
      );

    render(<TransferScreen onCompleted={jest.fn()} />);

    await fillForm();
    fireEvent.press(screen.getByText('Transfer'));

    await waitFor(() => expect(registerCredential).toHaveBeenCalled());
  });
});
