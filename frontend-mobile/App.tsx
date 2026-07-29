import { useState } from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';
import { StatusBar } from 'expo-status-bar';
import { AuthProvider } from './src/auth/AuthProvider';
import { useAuth } from './src/auth/useAuth';
import { LoginScreen } from './src/screens/LoginScreen';
import { OnboardingScreen } from './src/screens/OnboardingScreen';
import { CredentialSetupScreen } from './src/screens/CredentialSetupScreen';
import { MovementsScreen } from './src/screens/MovementsScreen';
import { TransferScreen } from './src/screens/TransferScreen';
import { ConfirmationScreen } from './src/screens/ConfirmationScreen';
import type { Transfer } from './src/api/types';

type Screen =
  | { name: 'login' }
  | { name: 'onboarding' }
  | { name: 'credential'; userId: string }
  | { name: 'movements' }
  | { name: 'transfer' }
  | { name: 'confirmation'; result: Transfer };

/**
 * Simple state-based routing instead of @react-navigation: this
 * environment has no Xcode Simulator or Android emulator to verify
 * additional native modules (react-native-screens, gesture-handler), so
 * the surface of unverifiable native dependencies is minimized. Documented
 * as a simplification in WORKLOG.md, not as a final architecture decision.
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
          onLogin={() => setScreen({ name: 'movements' })}
          onStartOnboarding={() => setScreen({ name: 'onboarding' })}
        />
      );
    case 'onboarding':
      return (
        <OnboardingScreen onApproved={(userId) => setScreen({ name: 'credential', userId })} />
      );
    case 'credential':
      return (
        <CredentialSetupScreen
          userId={screen.userId}
          onReady={() => setScreen({ name: 'movements' })}
        />
      );
    case 'movements':
      return <MovementsScreen onNewTransfer={() => setScreen({ name: 'transfer' })} />;
    case 'transfer':
      return (
        <TransferScreen onCompleted={(result) => setScreen({ name: 'confirmation', result })} />
      );
    case 'confirmation':
      return (
        <ConfirmationScreen
          result={screen.result}
          onGoBack={() => setScreen({ name: 'movements' })}
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
