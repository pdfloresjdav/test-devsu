import { useState } from 'react';
import { ActivityIndicator, Button, StyleSheet, Text, View } from 'react-native';
import { useAuth } from '../auth/useAuth';

interface Props {
  onLogin: () => void;
  onStartOnboarding: () => void;
}

/**
 * Item 10.4: recurring login. If there's already a credential linked on
 * the device (item 10.3), it asks for biometrics (when available) and
 * renews the access_token with the stored refresh_token — without going
 * through Authorization Code + PKCE or the KYC provider again.
 */
export function LoginScreen({ onLogin, onStartOnboarding }: Props) {
  const { hasLinkedCredential, recurringLogin, biometricAvailable } = useAuth();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const login = async () => {
    setLoading(true);
    setError(null);

    const result = await recurringLogin();

    setLoading(false);

    if (result.ok) {
      onLogin();
    } else {
      setError(result.error ?? 'Could not log in.');
    }
  };

  if (!hasLinkedCredential) {
    return (
      <View style={styles.container}>
        <Text style={styles.title}>BP Digital Banking</Text>
        <Text>You do not have a credential linked on this device yet.</Text>
        <Button title="Start customer sign-up" onPress={onStartOnboarding} />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <Text style={styles.title}>BP Digital Banking</Text>
      <Text>
        {biometricAvailable
          ? 'Confirm your identity with biometrics to continue.'
          : 'This device has no biometrics available; the stored session will be renewed directly.'}
      </Text>
      <Button title="Log in" onPress={login} disabled={loading} />
      {loading && <ActivityIndicator testID="login-loading" />}
      {error && (
        <Text style={styles.error} role="alert">
          {error}
        </Text>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, padding: 24, gap: 12, justifyContent: 'center' },
  title: { fontSize: 20, fontWeight: '600' },
  error: { color: '#b00020' },
});
