import { useCallback, useMemo, useState } from 'react';

export function useInvoiceBulkSelection() {
    const [selectedIds, setSelectedIds] = useState(() => new Set());

    const toggleSelect = useCallback((id) => {
        setSelectedIds((current) => {
            const next = new Set(current);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    }, []);

    const toggleSelectAll = useCallback((ids) => {
        setSelectedIds((current) => {
            const allSelected = ids.length > 0 && ids.every((id) => current.has(id));
            if (allSelected) {
                const next = new Set(current);
                ids.forEach((id) => next.delete(id));
                return next;
            }
            return new Set([...current, ...ids]);
        });
    }, []);

    const clear = useCallback(() => {
        setSelectedIds(new Set());
    }, []);

    const selectedCount = selectedIds.size;

    const isSelected = useCallback((id) => selectedIds.has(id), [selectedIds]);

    const ids = useMemo(() => [...selectedIds], [selectedIds]);

    return {
        selectedIds,
        ids,
        selectedCount,
        isSelected,
        toggleSelect,
        toggleSelectAll,
        clear,
    };
}
