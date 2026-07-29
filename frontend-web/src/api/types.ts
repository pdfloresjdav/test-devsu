export interface Customer {
  customer_id: string;
  name: string;
  document?: string;
  segment?: string;
  contact?: { email?: string; phone?: string };
}

export interface Movement {
  movement_id: string;
  account_id: string;
  type: string;
  amount: number;
  description: string;
  date: string;
}

export interface Dashboard {
  customer: Customer;
  recent_movements: Movement[];
}

export interface Transfer {
  transfer_id: string;
  source_account: string;
  destination_account: string;
  amount: number;
  description?: string | null;
  status: 'completed' | 'failed' | 'pending';
  failure_reason?: string | null;
}
