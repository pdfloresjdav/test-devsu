import { useState } from 'react';
import { ActivityIndicator, Button, StyleSheet, Text, TextInput, View } from 'react-native';
import { useAuth } from '../auth/useAuth';
import { bffFetch, BffError } from '../api/bffClient';
import { getOrCreateIdempotencyKey, resetIdempotencyKey } from '../storage/idempotency';
import type { Transfer } from '../api/types';

interface Props {
  onCompleted: (result: Transfer) => void;
}

/**
 * Item 10.5 (same Idempotency-Key/step-up criterion as frontend-web, item
 * 9.4): the Idempotency-Key is obtained once per attempt and resent as-is
 * on retries. On a rejection due to reinforced authentication (step-up,
 * `error.code === 'step_up_required'` -- bp-common preserves that code
 * since Fase 10 instead of flattening it to "upstream_error"), a silent
 * token renewal isn't enough here: step-up requires a new interactive
 * authentication, so `registerCredential()` (the full PKCE login) is
 * triggered instead of `recurringLogin()`, keeping the same
 * Idempotency-Key for the subsequent retry.
 */
export function TransferScreen({ onCompleted }: Props) {
  const { accessToken, registerCredential } = useAuth();
  const [sourceAccount, setSourceAccount] = useState('');
  const [destinationAccount, setDestinationAccount] = useState('');
  const [amount, setAmount] = useState('');
  const [description, setDescription] = useState('');
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async () => {
    setSending(true);
    setError(null);

    const idempotencyKey = await getOrCreateIdempotencyKey();

    try {
      const result = await bffFetch<Transfer>('/transfers', {
        method: 'POST',
        accessToken: accessToken ?? undefined,
        idempotencyKey,
        body: JSON.stringify({
          source_account: sourceAccount,
          destination_account: destinationAccount,
          amount: Number(amount),
          description,
        }),
      });

      await resetIdempotencyKey();
      onCompleted(result);
    } catch (err) {
      if (err instanceof BffError && err.code === 'step_up_required') {
        setError('This transfer requires reinforced authentication. Please log in again.');
        await registerCredential();
        return;
      }

      setError(err instanceof BffError ? err.message : 'Could not process the transfer.');
    } finally {
      setSending(false);
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>New transfer</Text>

      <TextInput
        style={styles.input}
        placeholder="Source account"
        value={sourceAccount}
        onChangeText={setSourceAccount}
        testID="input-source-account"
      />
      <TextInput
        style={styles.input}
        placeholder="Destination account"
        value={destinationAccount}
        onChangeText={setDestinationAccount}
        testID="input-destination-account"
      />
      <TextInput
        style={styles.input}
        placeholder="Amount"
        value={amount}
        onChangeText={setAmount}
        keyboardType="numeric"
        testID="input-amount"
      />
      <TextInput
        style={styles.input}
        placeholder="Description"
        value={description}
        onChangeText={setDescription}
        testID="input-description"
      />

      <Button title={sending ? 'Sending…' : 'Transfer'} onPress={submit} disabled={sending} />
      {sending && <ActivityIndicator testID="transfer-loading" />}
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
