import { Button, StyleSheet, Text, View } from 'react-native';
import type { Transfer } from '../api/types';

interface Props {
  result: Transfer;
  onGoBack: () => void;
}

export function ConfirmationScreen({ result, onGoBack }: Props) {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>
        {result.status === 'completed' ? 'Transfer completed' : 'Transfer not completed'}
      </Text>

      <Text>Status: {result.status}</Text>
      <Text>Source account: {result.source_account}</Text>
      <Text>Destination account: {result.destination_account}</Text>
      <Text>Amount: {result.amount.toFixed(2)}</Text>
      {result.failure_reason && <Text>Reason: {result.failure_reason}</Text>}

      <Button title="Back to my movements" onPress={onGoBack} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, padding: 24, gap: 8, justifyContent: 'center' },
  title: { fontSize: 20, fontWeight: '600' },
});
