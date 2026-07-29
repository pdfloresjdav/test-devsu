import { useState } from 'react';
import { ActivityIndicator, Button, StyleSheet, Text, View } from 'react-native';
import { useAuth } from '../auth/useAuth';

interface Props {
  usuarioId: string;
  onListo: () => void;
}

/**
 * Item 10.3. Ver el comentario en AuthProvider.registrarCredencial para la
 * simplificación documentada (login PKCE + bind a biometría nativa, en vez
 * de un ceremonial WebAuthn/FIDO2 real contra un Relying Party que no
 * existe en mock-oidc).
 */
export function CredentialSetupScreen({ usuarioId, onListo }: Props) {
  const { registrarCredencial, biometricAvailable } = useAuth();
  const [cargando, setCargando] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const vincular = async () => {
    setCargando(true);
    setError(null);

    const resultado = await registrarCredencial();

    setCargando(false);

    if (resultado.ok) {
      onListo();
    } else {
      setError(resultado.error ?? 'No se pudo vincular la credencial.');
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Cuenta {usuarioId} creada</Text>
      <Text>
        Para los próximos inicios de sesión vamos a usar la biometría de tu dispositivo en vez de
        pedirte la contraseña cada vez. Iniciá sesión una vez para vincularla.
      </Text>

      {!biometricAvailable && (
        <Text style={styles.aviso}>
          Este dispositivo no tiene biometría disponible (o estás en la vista web de desarrollo) —
          se va a guardar el acceso sin el gate biométrico.
        </Text>
      )}

      <Button title="Iniciar sesión y vincular" onPress={vincular} disabled={cargando} />
      {cargando && <ActivityIndicator testID="credential-loading" />}
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
  aviso: { color: '#8a6d00' },
  error: { color: '#b00020' },
});
