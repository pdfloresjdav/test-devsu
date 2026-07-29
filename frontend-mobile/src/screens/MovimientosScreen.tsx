import { useState } from 'react';
import {
  ActivityIndicator,
  Button,
  FlatList,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useAuth } from '../auth/useAuth';
import { bffFetch, BffError } from '../api/bffClient';
import type { Dashboard } from '../api/types';

const CUENTA_DEMO = process.env.EXPO_PUBLIC_DEMO_CUENTA_ID ?? '1001';

interface Props {
  onNuevaTransferencia: () => void;
}

export function MovimientosScreen({ onNuevaTransferencia }: Props) {
  const { accessToken, logout } = useAuth();
  const [cuentaId, setCuentaId] = useState(CUENTA_DEMO);
  const [dashboard, setDashboard] = useState<Dashboard | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const consultar = async () => {
    setLoading(true);
    setError(null);

    try {
      const data = await bffFetch<Dashboard>(`/dashboard/${cuentaId}`, {
        method: 'GET',
        accessToken: accessToken ?? undefined,
      });
      setDashboard(data);
    } catch (err) {
      setDashboard(null);
      setError(err instanceof BffError ? err.message : 'No se pudo consultar la cuenta.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>Mis movimientos</Text>
        <Button title="Cerrar sesión" onPress={logout} />
      </View>

      <TextInput
        style={styles.input}
        value={cuentaId}
        onChangeText={setCuentaId}
        testID="input-cuenta-id"
      />
      <Button
        title={loading ? 'Consultando…' : 'Consultar'}
        onPress={consultar}
        disabled={loading}
      />

      {loading && <ActivityIndicator testID="movimientos-loading" />}
      {error && (
        <Text style={styles.error} role="alert">
          {error}
        </Text>
      )}

      {dashboard && (
        <View style={styles.resultado}>
          <Text style={styles.clienteNombre}>{dashboard.cliente.nombre}</Text>
          <FlatList
            data={dashboard.movimientos_recientes}
            keyExtractor={(item) => item.movimiento_id}
            renderItem={({ item }) => (
              <Text>
                {item.fecha} — {item.tipo} — {item.monto.toFixed(2)} — {item.descripcion}
              </Text>
            )}
          />
        </View>
      )}

      <Button title="Nueva transferencia" onPress={onNuevaTransferencia} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, padding: 24, gap: 12 },
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  title: { fontSize: 20, fontWeight: '600' },
  input: { borderWidth: 1, borderColor: '#ccc', borderRadius: 8, padding: 10 },
  error: { color: '#b00020' },
  resultado: { gap: 6 },
  clienteNombre: { fontSize: 16, fontWeight: '600' },
});
