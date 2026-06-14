export function hasJsonContent(value) {
    if (value == null) {
        return false;
    }
    if (typeof value === 'string') {
        return value.trim().length > 0;
    }
    if (typeof value === 'object') {
        return Object.keys(value).length > 0;
    }
    return true;
}

export function filterRows(rows, search, keys) {
    const query = search?.trim().toLowerCase();
    if (!query) {
        return rows;
    }

    return rows.filter((row) =>
        keys.some((key) => {
            const value = key.split('.').reduce((current, part) => current?.[part], row);
            return String(value ?? '')
                .toLowerCase()
                .includes(query);
        }),
    );
}
