export interface Cliente {
  cliente_id: string;
  nombre: string;
  documento?: string;
  segmento?: string;
  contacto?: { email?: string; telefono?: string };
}

export interface Movimiento {
  movimiento_id: string;
  cuenta_id: string;
  tipo: string;
  monto: number;
  descripcion: string;
  fecha: string;
}

export interface Dashboard {
  cliente: Cliente;
  movimientos_recientes: Movimiento[];
}

export interface Transferencia {
  transferencia_id: string;
  cuenta_origen: string;
  cuenta_destino: string;
  monto: number;
  descripcion?: string | null;
  estado: 'completada' | 'fallida' | 'pendiente';
  motivo_falla?: string | null;
}
