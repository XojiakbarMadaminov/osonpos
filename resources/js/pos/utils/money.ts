export function formatMoney(amount: number): string {
    return Math.trunc(amount).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}
