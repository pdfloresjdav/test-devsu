import { Button, StyleSheet, Text, View } from 'react-native';
import type { Transferencia } from '../api/types';

interface Props {
  resultado: Transferencia;
  onVolver: () => void;
}

export function ConfirmacionScreen({ resultado, onVolver }: Props) {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>
        {resultado.estado === 'completada'
          ? 'Transferencia realizada'
          : 'Transferencia no realizada'}
      </Text>

      <Text>Estado: {resultado.estado}</Text>
      <Text>Cuenta origen: {resultado.cuenta_origen}</Text>
      <Text>Cuenta destino: {resultado.cuenta_destino}</Text>
      <Text>Monto: {resultado.monto.toFixed(2)}</Text>
      {resultado.motivo_falla && <Text>Motivo: {resultado.motivo_falla}</Text>}

      <Button title="Volver a mis movimientos" onPress={onVolver} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, padding: 24, gap: 8, justifyContent: 'center' },
  title: { fontSize: 20, fontWeight: '600' },
});
