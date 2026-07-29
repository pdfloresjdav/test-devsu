import { describe, it, expect, jest, beforeEach } from '@jest/globals';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';
import { OnboardingScreen } from './OnboardingScreen';
import { bffFetch } from '../api/bffClient';

jest.mock('../api/bffClient', () => {
  const actual = jest.requireActual('../api/bffClient') as object;
  return { ...actual, bffFetch: jest.fn() };
});

describe('OnboardingScreen', () => {
  const onApproved = jest.fn();

  beforeEach(() => {
    jest.mocked(bffFetch).mockReset();
    onApproved.mockClear();
  });

  it('enables "Continue" only once the data and both simulated captures are complete', () => {
    render(<OnboardingScreen onApproved={onApproved} />);

    expect(screen.getByRole('button', { name: 'Continue' })).toBeDisabled();

    fireEvent.changeText(screen.getByTestId('input-name'), 'Ana Torres');
    fireEvent.changeText(screen.getByTestId('input-email'), 'ana@bp.test');
    fireEvent.changeText(screen.getByTestId('input-document'), '12345678');
    fireEvent.press(screen.getByText('Simulate document capture'));
    fireEvent.press(screen.getByText('Simulate selfie capture'));

    expect(screen.getByRole('button', { name: 'Continue' })).not.toBeDisabled();
  });

  it('submits the onboarding and calls onApproved when KYC approves', async () => {
    jest.mocked(bffFetch).mockResolvedValueOnce({ user_id: 'auth0|abc123', status: 'approved' });

    render(<OnboardingScreen onApproved={onApproved} />);

    fireEvent.changeText(screen.getByTestId('input-name'), 'Ana Torres');
    fireEvent.changeText(screen.getByTestId('input-email'), 'ana@bp.test');
    fireEvent.changeText(screen.getByTestId('input-document'), '12345678');
    fireEvent.press(screen.getByText('Simulate document capture'));
    fireEvent.press(screen.getByText('Simulate selfie capture'));
    fireEvent.press(screen.getByText('Continue'));

    await waitFor(() => expect(onApproved).toHaveBeenCalledWith('auth0|abc123'));

    const [path, options] = jest.mocked(bffFetch).mock.calls[0];
    expect(path).toBe('/onboarding');
    expect(JSON.parse((options as { body: string }).body)).toMatchObject({
      identity_document: '12345678',
    });
  });

  it('shows an error if KYC rejects (identity_document "REJECT-...")', async () => {
    const { BffError } = jest.requireActual(
      '../api/bffClient',
    ) as typeof import('../api/bffClient');
    jest
      .mocked(bffFetch)
      .mockRejectedValueOnce(
        new BffError(422, 'onboarding_rejected', 'Identity verification rejected'),
      );

    render(<OnboardingScreen onApproved={onApproved} />);

    fireEvent.changeText(screen.getByTestId('input-name'), 'Ana Torres');
    fireEvent.changeText(screen.getByTestId('input-email'), 'ana@bp.test');
    fireEvent.changeText(screen.getByTestId('input-document'), 'REJECT-1');
    fireEvent.press(screen.getByText('Simulate document capture'));
    fireEvent.press(screen.getByText('Simulate selfie capture'));
    fireEvent.press(screen.getByText('Continue'));

    await waitFor(() =>
      expect(screen.getByRole('alert')).toHaveTextContent('Identity verification rejected'),
    );
    expect(onApproved).not.toHaveBeenCalled();
  });
});
