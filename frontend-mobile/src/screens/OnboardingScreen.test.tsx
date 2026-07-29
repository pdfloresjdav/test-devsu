import { describe, it, expect, jest, beforeEach } from '@jest/globals';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';
import { OnboardingScreen } from './OnboardingScreen';
import { bffFetch } from '../api/bffClient';

jest.mock('../api/bffClient', () => {
  const actual = jest.requireActual('../api/bffClient') as object;
  return { ...actual, bffFetch: jest.fn() };
});

describe('OnboardingScreen', () => {
  const onAprobado = jest.fn();

  beforeEach(() => {
    jest.mocked(bffFetch).mockReset();
    onAprobado.mockClear();
  });

  it('habilita "Continuar" solo cuando los datos y las dos capturas simuladas están completos', () => {
    render(<OnboardingScreen onAprobado={onAprobado} />);

    expect(screen.getByRole('button', { name: 'Continuar' })).toBeDisabled();

    fireEvent.changeText(screen.getByTestId('input-nombre'), 'Ana Torres');
    fireEvent.changeText(screen.getByTestId('input-email'), 'ana@bp.test');
    fireEvent.changeText(screen.getByTestId('input-documento'), '12345678');
    fireEvent.press(screen.getByText('Simular captura de documento'));
    fireEvent.press(screen.getByText('Simular captura de selfie'));

    expect(screen.getByRole('button', { name: 'Continuar' })).not.toBeDisabled();
  });

  it('envía el onboarding y llama a onAprobado cuando el KYC aprueba', async () => {
    jest.mocked(bffFetch).mockResolvedValueOnce({ usuario_id: 'auth0|abc123', estado: 'aprobado' });

    render(<OnboardingScreen onAprobado={onAprobado} />);

    fireEvent.changeText(screen.getByTestId('input-nombre'), 'Ana Torres');
    fireEvent.changeText(screen.getByTestId('input-email'), 'ana@bp.test');
    fireEvent.changeText(screen.getByTestId('input-documento'), '12345678');
    fireEvent.press(screen.getByText('Simular captura de documento'));
    fireEvent.press(screen.getByText('Simular captura de selfie'));
    fireEvent.press(screen.getByText('Continuar'));

    await waitFor(() => expect(onAprobado).toHaveBeenCalledWith('auth0|abc123'));

    const [path, options] = jest.mocked(bffFetch).mock.calls[0];
    expect(path).toBe('/onboarding');
    expect(JSON.parse((options as { body: string }).body)).toMatchObject({
      documento_identidad: '12345678',
    });
  });

  it('muestra un error si el KYC rechaza (documento_identidad "RECHAZA-...")', async () => {
    const { BffError } = jest.requireActual(
      '../api/bffClient',
    ) as typeof import('../api/bffClient');
    jest
      .mocked(bffFetch)
      .mockRejectedValueOnce(
        new BffError(422, 'onboarding_rechazado', 'Verificación de identidad rechazada'),
      );

    render(<OnboardingScreen onAprobado={onAprobado} />);

    fireEvent.changeText(screen.getByTestId('input-nombre'), 'Ana Torres');
    fireEvent.changeText(screen.getByTestId('input-email'), 'ana@bp.test');
    fireEvent.changeText(screen.getByTestId('input-documento'), 'RECHAZA-1');
    fireEvent.press(screen.getByText('Simular captura de documento'));
    fireEvent.press(screen.getByText('Simular captura de selfie'));
    fireEvent.press(screen.getByText('Continuar'));

    await waitFor(() =>
      expect(screen.getByRole('alert')).toHaveTextContent('Verificación de identidad rechazada'),
    );
    expect(onAprobado).not.toHaveBeenCalled();
  });
});
