import { flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table';
import { isSortKey, type Pack, type PackColumn, type SortState } from './types';

type Props = {
    data: Pack[];
    columns: PackColumn[];
    sort: SortState;
    onSortChange: (sort: SortState) => void;
};

function SortIndicator({ direction }: { direction: false | 'asc' | 'desc' }) {
    return (
        <svg
            aria-hidden="true"
            xmlns="http://www.w3.org/2000/svg"
            width="14"
            height="14"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            className={direction === 'asc' ? 'rotate-180' : ''}
        >
            <path d="M6 9l6 6 6-6" />
        </svg>
    );
}

function columnWidth(id: string): number | undefined {
    switch (id) {
        case 'bot_name':
            return 200;
        case 'number':
            return 80;
        case 'sizekbits':
            return 100;
        case 'actions':
            return 180;
        default:
            return undefined;
    }
}

export function DataTable({ data, columns, sort, onSortChange }: Props) {
    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    const handleSort = (id: string) => {
        if (!isSortKey(id)) {
            return;
        }
        onSortChange({
            key: id,
            direction: sort.key === id && sort.direction === 'asc' ? 'desc' : 'asc',
        });
    };

    return (
        <div className="my-4 bg-white p-0 dark:bg-gray-800 overflow-x-auto">
            <table className="border-collapse w-full table-fixed bg-white relative shadow-sm dark:bg-gray-800">
                <colgroup>
                    {columns.map((column) => {
                        const width = columnWidth(column.id ?? '');
                        return <col key={column.id ?? 'filename'} style={width ? { width: `${width}px` } : undefined} />;
                    })}
                </colgroup>
                <thead>
                    {table.getHeaderGroups().map((headerGroup) => (
                        <tr key={headerGroup.id} className="text-left">
                            {headerGroup.headers.map((header, index) => {
                                const id = header.column.id;
                                const canSort = header.column.getCanSort();
                                const direction: false | 'asc' | 'desc' = sort.key === id ? sort.direction : false;
                                const headerClass = [
                                    'py-2 px-3 border-b border-gray-800 bg-gray-800 text-gray-200 dark:bg-gray-900',
                                    index === 0 ? 'rounded-tl' : '',
                                    index === headerGroup.headers.length - 1 ? 'rounded-tr' : '',
                                ].join(' ');

                                return (
                                    <th key={header.id} className={headerClass}>
                                        {canSort ? (
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-1"
                                                onClick={() => handleSort(id)}
                                            >
                                                <span>{flexRender(header.column.columnDef.header, header.getContext())}</span>
                                                <SortIndicator direction={direction} />
                                            </button>
                                        ) : (
                                            flexRender(header.column.columnDef.header, header.getContext())
                                        )}
                                    </th>
                                );
                            })}
                        </tr>
                    ))}
                </thead>
                <tbody>
                    {table.getRowModel().rows.map((row) => (
                        <tr key={row.id} className="text-left hover:bg-gray-100 dark:hover:bg-gray-700">
                            {row.getVisibleCells().map((cell) => (
                                <td key={cell.id} className="border-dashed border-t border-gray-200 px-3 py-2 dark:border-gray-700 overflow-hidden">
                                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
