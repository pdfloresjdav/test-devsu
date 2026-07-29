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

const ACCOUNT_DEMO = process.env.EXPO_PUBLIC_DEMO_ACCOUNT_ID ?? '1001';

interface Props {
  onNewTransfer: () => void;
}

export function MovementsScreen({ onNewTransfer }: Props) {
  const { accessToken, logout } = useAuth();
  const [accountId, setAccountId] = useState(ACCOUNT_DEMO);
  const [dashboard, setDashboard] = useState<Dashboard | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const query = async () => {
    setLoading(true);
    setError(null);

    try {
      const data = await bffFetch<Dashboard>(`/dashboard/${accountId}`, {
        method: 'GET',
        accessToken: accessToken ?? undefined,
      });
      setDashboard(data);
    } catch (err) {
      setDashboard(null);
      setError(err instanceof BffError ? err.message : 'Could not query the account.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>My movements</Text>
        <Button title="Log out" onPress={logout} />
      </View>

      <TextInput
        style={styles.input}
        value={accountId}
        onChangeText={setAccountId}
        testID="input-account-id"
      />
      <Button title={loading ? 'Querying…' : 'Query'} onPress={query} disabled={loading} />

      {loading && <ActivityIndicator testID="movements-loading" />}
      {error && (
        <Text style={styles.error} role="alert">
          {error}
        </Text>
      )}

      {dashboard && (
        <View style={styles.result}>
          <Text style={styles.customerName}>{dashboard.customer.name}</Text>
          <FlatList
            data={dashboard.recent_movements}
            keyExtractor={(item) => item.movement_id}
            renderItem={({ item }) => (
              <Text>
                {item.date} — {item.type} — {item.amount.toFixed(2)} — {item.description}
              </Text>
            )}
          />
        </View>
      )}

      <Button title="New transfer" onPress={onNewTransfer} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, padding: 24, gap: 12 },
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  title: { fontSize: 20, fontWeight: '600' },
  input: { borderWidth: 1, borderColor: '#ccc', borderRadius: 8, padding: 10 },
  error: { color: '#b00020' },
  result: { gap: 6 },
  customerName: { fontSize: 16, fontWeight: '600' },
});
