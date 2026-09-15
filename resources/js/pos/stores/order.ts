import { defineStore } from 'pinia';
import type { CreatedOrder, CustomerSummary } from '../services/api';
import type { OrderType } from '../types/bootstrap';

export const useOrderStore = defineStore('order', {
    state: () => ({
        type: 'TAKEAWAY' as OrderType,
        tableId: null as number | null,
        selectedCustomer: null as CustomerSummary | null,
        customerPhone: '',
        customerName: '',
        deliveryAddress: '',
        deliveryFee: 0,
        current: null as CreatedOrder | null,
    }),
    actions: {
        start(type: OrderType): void {
            this.type = type;
            this.current = null;
            this.selectedCustomer = null;
            if (type !== 'DINE_IN') this.tableId = null;
        },
        selectTable(tableId: number): void {
            this.type = 'DINE_IN';
            this.tableId = tableId;
            this.current = null;
            this.selectedCustomer = null;
        },
        openExisting(existingOrder: CreatedOrder): void {
            this.type = existingOrder.type;
            this.tableId = existingOrder.table_id;
            this.selectedCustomer = existingOrder.customer;
            this.current = existingOrder;
        },
        reset(): void {
            this.$reset();
        },
    },
});
