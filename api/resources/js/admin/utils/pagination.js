export function extractPaginated(response) {
    const body = response.data ?? {};
    const meta = body.meta ?? {};

    return {
        data: body.data ?? [],
        pagination: {
            current: body.current_page ?? meta.current_page ?? 1,
            pageSize: body.per_page ?? meta.per_page ?? 25,
            total: body.total ?? meta.total ?? 0,
            lastPage: body.last_page ?? meta.last_page ?? 1,
        },
    };
}
