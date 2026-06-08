import { useMemo, useState } from 'react';

function getColumnKey(column) {
    if (column.key) {
        return column.key;
    }
    if (Array.isArray(column.dataIndex)) {
        return column.dataIndex.join('.');
    }
    return column.dataIndex ?? '';
}

function getCellValue(row, column) {
    const key = column.key ?? column.dataIndex;
    if (!key) {
        return null;
    }
    if (Array.isArray(key)) {
        return key.reduce((value, part) => value?.[part], row);
    }
    if (String(key).includes('.')) {
        return String(key)
            .split('.')
            .reduce((value, part) => value?.[part], row);
    }
    return row[key];
}

function renderCell(row, column) {
    const value = getCellValue(row, column);
    if (column.render) {
        return column.render.length > 1 ? column.render(value, row) : column.render(value);
    }
    if (value === null || value === undefined || value === '') {
        return '—';
    }
    return value;
}

function compareValues(a, b) {
    if (a == null && b == null) {
        return 0;
    }
    if (a == null) {
        return 1;
    }
    if (b == null) {
        return -1;
    }
    if (typeof a === 'number' && typeof b === 'number') {
        return a - b;
    }
    return String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' });
}

export default function DataTable({
    columns = [],
    data = [],
    dataSource,
    loading = false,
    rowKey = 'id',
    emptyMessage = 'No records found',
    locale,
    onRow,
    size,
    className = '',
}) {
    const rows = dataSource ?? data;
    const [sortKey, setSortKey] = useState(null);
    const [sortDir, setSortDir] = useState('asc');

    const emptyText =
        locale?.emptyText?.props?.description ??
        (typeof locale?.emptyText === 'string' ? locale.emptyText : emptyMessage);

    const sortedRows = useMemo(() => {
        if (!sortKey) {
            return rows;
        }
        const column = columns.find((col) => getColumnKey(col) === sortKey);
        if (!column) {
            return rows;
        }
        return [...rows].sort((left, right) => {
            const result = compareValues(getCellValue(left, column), getCellValue(right, column));
            return sortDir === 'asc' ? result : -result;
        });
    }, [rows, sortKey, sortDir, columns]);

    const handleSort = (column) => {
        const key = getColumnKey(column);
        if (!key || column.sortable === false) {
            return;
        }
        if (sortKey === key) {
            setSortDir((dir) => (dir === 'asc' ? 'desc' : 'asc'));
            return;
        }
        setSortKey(key);
        setSortDir('asc');
    };

    const isCompact = size === 'small';
    const cellPadding = isCompact ? 'px-4 py-2' : 'px-5 py-3';

    return (
        <div className={`overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm ${className}`}>
            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50">
                        <tr>
                            {columns.map((column) => {
                                const key = getColumnKey(column);
                                const label = column.label ?? column.title ?? key;
                                const isSortable = column.sortable !== false && !!key;
                                const isActive = sortKey === key;

                                return (
                                    <th
                                        key={key || label}
                                        className={`${cellPadding} text-left font-medium text-slate-600 ${
                                            isSortable ? 'cursor-pointer select-none hover:bg-slate-100' : ''
                                        }`}
                                        onClick={isSortable ? () => handleSort(column) : undefined}
                                    >
                                        <span className="inline-flex items-center gap-1">
                                            {label}
                                            {isSortable && (
                                                <span className="text-xs text-slate-400">
                                                    {isActive ? (sortDir === 'asc' ? '↑' : '↓') : '↕'}
                                                </span>
                                            )}
                                        </span>
                                    </th>
                                );
                            })}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {loading ? (
                            <tr>
                                <td
                                    colSpan={columns.length || 1}
                                    className={`${cellPadding} text-center text-slate-500`}
                                >
                                    Loading…
                                </td>
                            </tr>
                        ) : sortedRows.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={columns.length || 1}
                                    className={`${cellPadding} text-center text-slate-500`}
                                >
                                    {emptyText}
                                </td>
                            </tr>
                        ) : (
                            sortedRows.map((row, index) => {
                                const key = row[rowKey] ?? index;
                                const rowProps = onRow?.(row) ?? {};

                                return (
                                    <tr
                                        key={key}
                                        className="hover:bg-slate-50"
                                        onClick={rowProps.onClick}
                                        style={rowProps.style}
                                    >
                                        {columns.map((column) => (
                                            <td
                                                key={getColumnKey(column) || column.label}
                                                className={`${cellPadding} text-slate-700`}
                                            >
                                                {renderCell(row, column)}
                                            </td>
                                        ))}
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
