import { useState } from 'react';
import { ActivityIndicator, Button, StyleSheet, Text, TextInput, View } from 'react-native';
import { bffFetch, BffError } from '../api/bffClient';
import type { OnboardingResultado } from '../api/types';

interface Props {
  onAprobado: (clienteId: string) => void;
}

/**
 * Item 10.2: captura de documento/selfie SIMULADA (sin expo-camera) — dos
 * botones marcan la "captura" como hecha y arman el string que espera el
 * body de POST /onboarding de bff-mobile. Con documento_identidad que
 * empieza con "RECHAZA-" (FakeKycProvider), el proveedor KYC simula un
 * rechazo — mismo criterio determinista que ya usan bff-mobile y
 * frontend-web para poder probar ambas ramas sin un proveedor KYC real.
 */
export function OnboardingScreen({ onAprobado }: Props) {
  const [clienteId, setClienteId] = useState('1003');
  const [nombre, setNombre] = useState('');
  const [email, setEmail] = useState('');
  const [documentoIdentidad, setDocumentoIdentidad] = useState('');
  const [documentoCapturado, setDocumentoCapturado] = useState(false);
  const [selfieCapturada, setSelfieCapturada] = useState(false);
  const [enviando, setEnviando] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const listoParaEnviar =
    nombre.length > 0 &&
    email.length > 0 &&
    documentoIdentidad.length > 0 &&
    documentoCapturado &&
    selfieCapturada &&
    !enviando;

  const enviar = async () => {
    setEnviando(true);
    setError(null);

    try {
      const resultado = await bffFetch<OnboardingResultado>('/onboarding', {
        method: 'POST',
        body: JSON.stringify({
          cliente_id: clienteId,
          nombre,
          email,
          documento_identidad: documentoIdentidad,
          selfie: 'selfie-capturada-simulada',
        }),
      });

      onAprobado(resultado.usuario_id);
    } catch (err) {
      setError(err instanceof BffError ? err.message : 'No se pudo completar el onboarding.');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Alta de cliente</Text>

      <TextInput
        style={styles.input}
        placeholder="ID de cliente"
        value={clienteId}
        onChangeText={setClienteId}
        testID="input-cliente-id"
      />
      <TextInput
        style={styles.input}
        placeholder="Nombre completo"
        value={nombre}
        onChangeText={setNombre}
        testID="input-nombre"
      />
      <TextInput
        style={styles.input}
        placeholder="Email"
        value={email}
        onChangeText={setEmail}
        autoCapitalize="none"
        keyboardType="email-address"
        testID="input-email"
      />
      <TextInput
        style={styles.input}
        placeholder="Documento de identidad"
        value={documentoIdentidad}
        onChangeText={setDocumentoIdentidad}
        testID="input-documento"
      />

      <Button
        title={documentoCapturado ? 'Documento capturado ✓' : 'Simular captura de documento'}
        onPress={() => setDocumentoCapturado(true)}
      />
      <Button
        title={selfieCapturada ? 'Selfie capturada ✓' : 'Simular captura de selfie'}
        onPress={() => setSelfieCapturada(true)}
      />

      <Button title="Continuar" onPress={enviar} disabled={!listoParaEnviar} />

      {enviando && <ActivityIndicator testID="onboarding-loading" />}
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
  title: { fontSize: 20, fontWeight: '600', marginBottom: 8 },
  input: { borderWidth: 1, borderColor: '#ccc', borderRadius: 8, padding: 10 },
  error: { color: '#b00020' },
});
