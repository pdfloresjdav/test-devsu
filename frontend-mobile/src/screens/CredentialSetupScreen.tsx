import { useState } from 'react';
import { ActivityIndicator, Button, StyleSheet, Text, View } from 'react-native';
import { useAuth } from '../auth/useAuth';

interface Props {
  userId: string;
  onReady: () => void;
}

/**
 * Item 10.3. See the comment in AuthProvider.registerCredential for the
 * documented simplification (PKCE login + bind to native biometrics,
 * instead of a real WebAuthn/FIDO2 ceremony against a Relying Party that
 * doesn't exist in mock-oidc).
 */
export function CredentialSetupScreen({ userId, onReady }: Props) {
  const { registerCredential, biometricAvailable } = useAuth();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const link = async () => {
    setLoading(true);
    setError(null);

    const result = await registerCredential();

    setLoading(false);

    if (result.ok) {
      onReady();
    } else {
      setError(result.error ?? 'Could not link the credential.');
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Account {userId} created</Text>
      <Text>
        For future logins we will use your device biometrics instead of asking for your password
        every time. Log in once to link it.
      </Text>

      {!biometricAvailable && (
        <Text style={styles.notice}>
          This device has no biometrics available (or you are in the web development view) — access
          will be stored without the biometric gate.
        </Text>
      )}

      <Button title="Log in and link" onPress={link} disabled={loading} />
      {loading && <ActivityIndicator testID="credential-loading" />}
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
  notice: { color: '#8a6d00' },
  error: { color: '#b00020' },
});
