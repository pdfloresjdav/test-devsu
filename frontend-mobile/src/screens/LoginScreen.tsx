import { useState } from 'react';
import { ActivityIndicator, Button, StyleSheet, Text, View } from 'react-native';
import { useAuth } from '../auth/useAuth';

interface Props {
  onIngresar: () => void;
  onEmpezarOnboarding: () => void;
}

/**
 * Item 10.4: login recurrente. Si ya hay una credencial vinculada en el
 * dispositivo (item 10.3), pide biometría (cuando está disponible) y
 * renueva el access_token con el refresh_token guardado — sin volver a
 * pasar por Authorization Code + PKCE ni por el proveedor KYC.
 */
export function LoginScreen({ onIngresar, onEmpezarOnboarding }: Props) {
  const { hasLinkedCredential, loginRecurrente, biometricAvailable } = useAuth();
  const [cargando, setCargando] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const ingresar = async () => {
    setCargando(true);
    setError(null);

    const resultado = await loginRecurrente();

    setCargando(false);

    if (resultado.ok) {
      onIngresar();
    } else {
      setError(resultado.error ?? 'No se pudo iniciar sesión.');
    }
  };

  if (!hasLinkedCredential) {
    return (
      <View style={styles.container}>
        <Text style={styles.title}>Banca Digital BP</Text>
        <Text>Todavía no tenés una credencial vinculada en este dispositivo.</Text>
        <Button title="Empezar alta de cliente" onPress={onEmpezarOnboarding} />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Banca Digital BP</Text>
      <Text>
        {biometricAvailable
          ? 'Confirmá tu identidad con biometría para continuar.'
          : 'Este dispositivo no tiene biometría disponible; se va a renovar la sesión guardada directamente.'}
      </Text>
      <Button title="Ingresar" onPress={ingresar} disabled={cargando} />
      {cargando && <ActivityIndicator testID="login-loading" />}
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
