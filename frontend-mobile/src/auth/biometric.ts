import { Platform } from 'react-native';
import * as LocalAuthentication from 'expo-local-authentication';

/**
 * `expo-local-authentication` no tiene implementación en web, y Face ID en
 * iOS no funciona dentro de Expo Go (requiere un development build) — ver
 * WORKLOG.md. En este entorno no hay Xcode Simulator ni emulador de
 * Android para probar la biometría real, así que en web se trata como "no
 * disponible" en vez de simularla: el flujo de login recurrente cae al
 * refresh silencioso sin gate biométrico, dejando documentado que la
 * verificación de la biometría real queda pendiente de un dispositivo o
 * development build real.
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
