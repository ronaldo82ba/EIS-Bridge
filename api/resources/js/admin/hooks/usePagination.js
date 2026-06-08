import { useMemo, useState } from 'react';

export function usePagination(initialPage = 1, initialPerPage = 25) {
    const [page, setPage] = useState(initialPage);
    const [perPage, setPerPage] = useState(initialPerPage);

    const params = useMemo(
        () => ({
            page,
            per_page: perPage,
        }),
        [page, perPage],
    );

    return {
        page,
        perPage,
        params,
        setPage,
        setPerPage,
        onPageChange: setPage,
        onPerPageChange: setPerPage,
    };
}
