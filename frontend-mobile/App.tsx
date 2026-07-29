import { useState } from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';
import { StatusBar } from 'expo-status-bar';
import { AuthProvider } from './src/auth/AuthProvider';
import { useAuth } from './src/auth/useAuth';
import { LoginScreen } from './src/screens/LoginScreen';
import { OnboardingScreen } from './src/screens/OnboardingScreen';
import { CredentialSetupScreen } from './src/screens/CredentialSetupScreen';
import { MovimientosScreen } from './src/screens/MovimientosScreen';
import { TransferenciaScreen } from './src/screens/TransferenciaScreen';
import { ConfirmacionScreen } from './src/screens/ConfirmacionScreen';
import type { Transferencia } from './src/api/types';

type Screen =
  | { name: 'login' }
  | { name: 'onboarding' }
  | { name: 'credencial'; usuarioId: string }
  | { name: 'movimientos' }
  | { name: 'transferencia' }
  | { name: 'confirmacion'; resultado: Transferencia };

/**
 * Enrutamiento simple basado en estado de React en vez de
 * @react-navigation: en este entorno no hay Xcode Simulator ni emulador de
 * Android para verificar módulos nativos adicionales (react-native-screens,
 * gesture-handler), así que se minimiza la superficie de dependencias
 * nativas no verificables. Documentado como simplificación en WORKLOG.md,
 * no como decisión definitiva de arquitectura.
 */
function Router() {
  const { isLoading } = useAuth();
  const [screen, setScreen] = useState<Screen>({ name: 'login' });

  if (isLoading) {
    return <ActivityIndicator testID="app-loading" />;
  }

  switch (screen.name) {
    case 'login':
      return (
        <LoginScreen
          onIngresar={() => setScreen({ name: 'movimientos' })}
          onEmpezarOnboarding={() => setScreen({ name: 'onboarding' })}
        />
      );
    case 'onboarding':
      return (
        <OnboardingScreen
          onAprobado={(usuarioId) => setScreen({ name: 'credencial', usuarioId })}
        />
      );
    case 'credencial':
      return (
        <CredentialSetupScreen
          usuarioId={screen.usuarioId}
          onListo={() => setScreen({ name: 'movimientos' })}
        />
      );
    case 'movimientos':
      return (
        <MovimientosScreen onNuevaTransferencia={() => setScreen({ name: 'transferencia' })} />
      );
    case 'transferencia':
      return (
        <TransferenciaScreen
          onCompletada={(resultado) => setScreen({ name: 'confirmacion', resultado })}
        />
      );
    case 'confirmacion':
      return (
        <ConfirmacionScreen
          resultado={screen.resultado}
          onVolver={() => setScreen({ name: 'movimientos' })}
        />
      );
  }
}

export default function App() {
  return (
    <View style={styles.safeArea}>
      <AuthProvider>
        <Router />
      </AuthProvider>
      <StatusBar style="auto" />
    </View>
  );
}

const styles = StyleSheet.create({
  safeArea: { flex: 1, backgroundColor: '#fff' },
});
