import { useState } from 'react';
import { ActivityIndicator, Button, StyleSheet, Text, TextInput, View } from 'react-native';
import { bffFetch, BffError } from '../api/bffClient';
import type { OnboardingResult } from '../api/types';

interface Props {
  onApproved: (customerId: string) => void;
}

/**
 * Item 10.2: SIMULATED document/selfie capture (no expo-camera) — two
 * buttons mark the "capture" as done and build the string expected by the
 * bff-mobile POST /onboarding body. With an identity_document starting
 * with "REJECT-" (FakeKycProvider), the KYC provider simulates a
 * rejection — same deterministic criterion already used by bff-mobile and
 * frontend-web to be able to test both branches without a real KYC
 * provider.
 */
export function OnboardingScreen({ onApproved }: Props) {
  const [customerId, setCustomerId] = useState('1003');
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [identityDocument, setIdentityDocument] = useState('');
  const [documentCaptured, setDocumentCaptured] = useState(false);
  const [selfieCaptured, setSelfieCaptured] = useState(false);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const readyToSubmit =
    name.length > 0 &&
    email.length > 0 &&
    identityDocument.length > 0 &&
    documentCaptured &&
    selfieCaptured &&
    !sending;

  const submit = async () => {
    setSending(true);
    setError(null);

    try {
      const result = await bffFetch<OnboardingResult>('/onboarding', {
        method: 'POST',
        body: JSON.stringify({
          customer_id: customerId,
          name,
          email,
          identity_document: identityDocument,
          selfie: 'simulated-captured-selfie',
        }),
      });

      onApproved(result.user_id);
    } catch (err) {
      setError(err instanceof BffError ? err.message : 'Could not complete the onboarding.');
    } finally {
      setSending(false);
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Customer sign-up</Text>

      <TextInput
        style={styles.input}
        placeholder="Customer ID"
        value={customerId}
        onChangeText={setCustomerId}
        testID="input-customer-id"
      />
      <TextInput
        style={styles.input}
        placeholder="Full name"
        value={name}
        onChangeText={setName}
        testID="input-name"
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
        placeholder="Identity document"
        value={identityDocument}
        onChangeText={setIdentityDocument}
        testID="input-document"
      />

      <Button
        title={documentCaptured ? 'Document captured ✓' : 'Simulate document capture'}
        onPress={() => setDocumentCaptured(true)}
      />
      <Button
        title={selfieCaptured ? 'Selfie captured ✓' : 'Simulate selfie capture'}
        onPress={() => setSelfieCaptured(true)}
      />

      <Button title="Continue" onPress={submit} disabled={!readyToSubmit} />

      {sending && <ActivityIndicator testID="onboarding-loading" />}
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
