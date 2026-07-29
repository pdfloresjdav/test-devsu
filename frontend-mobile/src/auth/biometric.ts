import { Platform } from 'react-native';
import * as LocalAuthentication from 'expo-local-authentication';

/**
 * `expo-local-authentication` has no implementation on web, and Face ID on
 * iOS doesn't work inside Expo Go (requires a development build) -- see
 * WORKLOG.md. There's no Xcode Simulator or Android emulator in this
 * environment to test real biometrics, so on web it's treated as "not
 * available" instead of simulating it: the recurring login flow falls
 * back to the silent refresh without the biometric gate, documenting that
 * verifying real biometrics is pending a real device or development
 * build.
 */
export async function isBiometricAvailable(): Promise<boolean> {
  if (Platform.OS === 'web') {
    return false;
  }

  const hasHardware = await LocalAuthentication.hasHardwareAsync();
  const isEnrolled = await LocalAuthentication.isEnrolledAsync();
  return hasHardware && isEnrolled;
}

export async function promptBiometric(promptMessage: string): Promise<boolean> {
  if (Platform.OS === 'web') {
    return false;
  }

  const result = await LocalAuthentication.authenticateAsync({
    promptMessage,
    disableDeviceFallback: false,
  });

  return result.success;
}
