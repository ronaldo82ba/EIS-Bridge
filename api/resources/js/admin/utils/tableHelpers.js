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
