const alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

export function generateUlid(now = Date.now()): string {
    let timestamp = now;
    let encodedTime = '';
    for (let index = 0; index < 10; index += 1) {
        encodedTime = alphabet[timestamp % 32] + encodedTime;
        timestamp = Math.floor(timestamp / 32);
    }

    const random = new Uint8Array(16);
    crypto.getRandomValues(random);

    return encodedTime + Array.from(random, (value) => alphabet[value % 32]).join('');
}
