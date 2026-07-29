import { useState } from 'react';
import { ActivityIndicator, Button, StyleSheet, Text, TextInput, View } from 'react-native';
import { useAuth } from '../auth/useAuth';
import { bffFetch, BffError } from '../api/bffClient';
import { getOrCreateIdempotencyKey, resetIdempotencyKey } from '../storage/idempotency';
import type { Transferencia } from '../api/types';

interface Props {
  onCompletada: (resultado: Transferencia) => void;
}

/**
 * Item 10.5 (con el mismo criterio de Idempotency-Key/step-up de
 * frontend-web, item 9.4): la Idempotency-Key se obtiene una vez por
 * intento y se reenvía igual ante reintentos. Ante un rechazo por
 * autenticación reforzada (step-up, `error.code === 'step_up_required'`
 * -- bp-common preserva ese código desde la Fase 10 en vez de aplanarlo
 * a "upstream_error"), acá una renovación silenciosa del token no
 * alcanza: el step-up exige una autenticación interactiva nueva, así que
 * se dispara `registrarCredencial()` (el login PKCE completo) en vez de
 * `loginRecurrente()`, conservando la misma Idempotency-Key para el
 * reintento posterior.
 */
export function TransferenciaScreen({ onCompletada }: Props) {
  const { accessToken, registrarCredencial } = useAuth();
  const [cuentaOrigen, setCuentaOrigen] = useState('');
  const [cuentaDestino, setCuentaDestino] = useState('');
  const [monto, setMonto] = useState('');
  const [descripcion, setDescripcion] = useState('');
  const [enviando, setEnviando] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const enviar = async () => {
    setEnviando(true);
    setError(null);

    const idempotencyKey = await getOrCreateIdempotencyKey();

    try {
      const resultado = await bffFetch<Transferencia>('/transferencias', {
        method: 'POST',
        accessToken: accessToken ?? undefined,
        idempotencyKey,
        body: JSON.stringify({
          cuenta_origen: cuentaOrigen,
          cuenta_destino: cuentaDestino,
          monto: Number(monto),
          descripcion,
        }),
      });

      await resetIdempotencyKey();
      onCompletada(resultado);
    } catch (err) {
      if (err instanceof BffError && err.code === 'step_up_required') {
        setError('Esta transferencia requiere autenticación reforzada. Iniciá sesión de nuevo.');
        await registrarCredencial();
        return;
      }

      setError(err instanceof BffError ? err.message : 'No se pudo procesar la transferencia.');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Nueva transferencia</Text>

      <TextInput
        style={styles.input}
        placeholder="Cuenta origen"
        value={cuentaOrigen}
        onChangeText={setCuentaOrigen}
        testID="input-cuenta-origen"
      />
      <TextInput
        style={styles.input}
        placeholder="Cuenta destino"
        value={cuentaDestino}
        onChangeText={setCuentaDestino}
        testID="input-cuenta-destino"
      />
      <TextInput
        style={styles.input}
        placeholder="Monto"
        value={monto}
        onChangeText={setMonto}
        keyboardType="numeric"
        testID="input-monto"
      />
      <TextInput
        style={styles.input}
        placeholder="Descripción"
        value={descripcion}
        onChangeText={setDescripcion}
        testID="input-descripcion"
      />

      <Button title={enviando ? 'Enviando…' : 'Transferir'} onPress={enviar} disabled={enviando} />
      {enviando && <ActivityIndicator testID="transferencia-loading" />}
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
  input: { borderWidth: 1, borderColor: '#ccc', borderRadius: 8, padding: 10 },
  error: { color: '#b00020' },
});
