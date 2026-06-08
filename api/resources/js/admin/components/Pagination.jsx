export default function Pagination({
    page,
    perPage,
    current,
    pageSize,
    total = 0,
    lastPage,
    onPageChange,
    onChange,
    showSizeChanger = false,
}) {
    const activePage = page ?? current ?? 1;
    const activePerPage = perPage ?? pageSize ?? 25;
    const handleChange = onPageChange ?? onChange;
    const computedLastPage = lastPage ?? Math.max(1, Math.ceil(total / activePerPage));
    const rangeStart = total === 0 ? 0 : (activePage - 1) * activePerPage + 1;
    const rangeEnd = Math.min(activePage * activePerPage, total);

    const goTo = (nextPage) => {
        if (!handleChange || nextPage < 1 || nextPage > computedLastPage || nextPage === activePage) {
            return;
        }
        handleChange(nextPage, activePerPage);
    };

    return (
        <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-slate-500">
                {total === 0 ? 'No records' : `${rangeStart}–${rangeEnd} of ${total}`}
            </p>
            <div className="flex items-center gap-2">
                <button
                    type="button"
                    onClick={() => goTo(activePage - 1)}
                    disabled={activePage <= 1}
                    className="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Previous
                </button>
                <span className="text-sm text-slate-600">
                    Page {activePage} of {computedLastPage}
                </span>
                <button
                    type="button"
                    onClick={() => goTo(activePage + 1)}
                    disabled={activePage >= computedLastPage}
                    className="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Next
                </button>
                {showSizeChanger && handleChange && (
                    <select
                        value={activePerPage}
                        onChange={(event) => handleChange(1, Number(event.target.value))}
                        className="rounded-md border border-slate-200 bg-white px-2 py-1.5 text-sm text-slate-700"
                    >
                        {[10, 25, 50, 100].map((size) => (
                            <option key={size} value={size}>
                                {size} / page
                            </option>
                        ))}
                    </select>
                )}
            </div>
        </div>
    );
}
