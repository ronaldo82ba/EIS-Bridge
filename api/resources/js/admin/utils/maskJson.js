const SENSITIVE_KEYS = ['private_key', 'password', 'secret', 'api_key'];
const UNMASKED_KEYS = ['signature_hash'];

function isSensitiveKey(key) {
    const lower = key.toLowerCase();

    if (UNMASKED_KEYS.includes(lower)) {
        return false;
    }

    if (lower === 'signature') {
        return true;
    }

    return SENSITIVE_KEYS.some((fragment) => lower.includes(fragment));
}

function maskSignatureValue(value, hash) {
    if (hash) {
        return `[masked — see signature_hash: ${hash}]`;
    }

    return '••••••••';
}

export function maskSensitiveJson(data) {
    if (data == null) {
        return null;
    }

    if (typeof data === 'string') {
        try {
            return maskSensitiveJson(JSON.parse(data));
        } catch {
            return '••••••••';
        }
    }

    if (Array.isArray(data)) {
        return data.map(maskSensitiveJson);
    }

    if (typeof data === 'object') {
        const signatureHash = data.signature_hash ?? data.signatureHash;

        return Object.fromEntries(
            Object.entries(data).map(([key, value]) => {
                if (key.toLowerCase() === 'signature') {
                    return [key, maskSignatureValue(value, signatureHash)];
                }

                if (isSensitiveKey(key)) {
                    return [key, '••••••••'];
                }

                return [key, maskSensitiveJson(value)];
            }),
        );
    }

    return data;
}
