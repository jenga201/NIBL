import { createColumnHelper } from '@tanstack/react-table';
import { CopyButton } from './CopyButton';
import type { Pack, PackColumn } from './types';

const columnHelper = createColumnHelper<Pack>();

export type ColumnOptions = {
    showBot: boolean;
    batchMode: boolean;
    botBatchEnabled: boolean;
    botNameFor: (pack: Pack) => string;
    isSelected: (pack: Pack) => boolean;
    onToggleSelect: (pack: Pack, selected: boolean) => void;
};

export function buildColumns({
    showBot,
    batchMode,
    botBatchEnabled,
    botNameFor,
    isSelected,
    onToggleSelect,
}: ColumnOptions): PackColumn[] {
    const columns: PackColumn[] = [];

    if (showBot) {
        columns.push(
            columnHelper.accessor('bot_name', {
                id: 'bot_name',
                header: 'Bot',
                cell: (info) => {
                    const name = info.getValue() ?? '';
                    return (
                        <a href={`/bots/${encodeURIComponent(name)}`} className="block truncate hover:underline" title={name}>
                            {name}
                        </a>
                    );
                },
            }),
        );
    }

    columns.push(
        columnHelper.accessor('number', {
            id: 'number',
            header: 'Pack',
            cell: (info) => info.getValue(),
        }),
        columnHelper.accessor('size', {
            id: 'sizekbits',
            header: 'Size',
            cell: (info) => info.getValue(),
        }),
        columnHelper.accessor('name', {
            id: 'name',
            header: 'Filename',
            cell: (info) => {
                const name = info.getValue();
                return (
                    <span className="block truncate" title={name}>
                        {name}
                    </span>
                );
            },
        }),
        columnHelper.display({
            id: 'actions',
            header: 'Actions',
            enableSorting: false,
            cell: (info) => {
                const pack = info.row.original;
                const canBatch = showBot ? Boolean(pack.bot_batch_enabled) : botBatchEnabled;

                return (
                    <div className="flex items-center gap-2">
                        <CopyButton pack={pack} botName={botNameFor(pack)} />
                        {batchMode && canBatch && (
                            <label className="flex items-center space-x-2">
                                <input
                                    type="checkbox"
                                    className="form-tick appearance-none h-5 w-5 border border-gray-300 rounded checked:bg-blue-600 checked:border-transparent focus:outline-none dark:border-gray-600 dark:bg-gray-600"
                                    checked={isSelected(pack)}
                                    onChange={(event) => onToggleSelect(pack, event.target.checked)}
                                />
                                <span className="text-gray-900 font-medium dark:text-gray-100">Batch</span>
                            </label>
                        )}
                    </div>
                );
            },
        }),
    );

    return columns;
}
