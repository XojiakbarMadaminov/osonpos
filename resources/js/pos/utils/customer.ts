export function nationalPhoneDigits(phone: string): string {
    return phone.replace(/^\+?998/, '').replace(/\D/g, '').slice(0, 9);
}

export function uzbekPhone(digits: string): string {
    const national = digits.replace(/\D/g, '').slice(0, 9);

    return national.length === 9 ? `+998${national}` : '';
}

export function formatUzbekPhone(phone: string): string {
    const digits = nationalPhoneDigits(phone);
    if (digits.length !== 9) return phone;

    return `+998 ${digits.slice(0, 2)} ${digits.slice(2, 5)} ${digits.slice(5, 7)} ${digits.slice(7, 9)}`;
}
