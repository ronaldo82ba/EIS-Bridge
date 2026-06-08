export function getApiErrorMessage(error, fallback = 'Request failed.') {
    const data = error?.response?.data;

    if (data?.message && typeof data.message === 'string') {
        return data.message;
    }

    if (data?.errors && typeof data.errors === 'object') {
        const messages = Object.values(data.errors).flat().filter(Boolean);
        if (messages.length > 0) {
            return messages.join(' ');
        }
    }

    return error?.friendlyMessage ?? error?.message ?? fallback;
}
