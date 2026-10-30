import { useCallback, useMemo, useState } from 'react';
import type { PackListSource } from './api';
import { BatchControls } from './BatchControls';
import { copyToClipboard } from './clipboard';
import { buildColumns } from './columns';
import { formatBatchXDCC } from './format';
import { InfinitePacksTable } from './InfinitePacksTable';
import { PageSizeSelect } from './PageSizeSelect';
import { PaginatedPacksTable } from './PaginatedPacksTable';
import { SearchBox } from './SearchBox';
import type { Pack, SortState } from './types';
import { usePackListState } from './usePackListState';

type Props = {
    botId?: number;
    botName?: string;
    batchEnabled: boolean;
};

export function PackApp({ botId, botName, batchEnabled }: Props) {
    const { state, update } = usePackListState();
    const { query, sort, direction, page, pageSize } = state;
    const sortState: SortState = { key: sort, direction };

    const showBot = botId === undefined;
    const hasValidQuery = query.trim().length >= 3;
    const enabled = !showBot || hasValidQuery;

    const source: PackListSource =
        botId !== undefined
            ? { mode: 'bot', botId, query }
            : { mode: 'search', query };

    const [batchMode, setBatchMode] = useState(false);
    const [selected, setSelected] = useState<Map<string, number[]>>(new Map());

    const botNameFor = useCallback(
        (pack: Pack) => (botId !== undefined ? (botName ?? '') : (pack.bot_name ?? '')),
        [botId, botName],
    );

    const toggleSelect = useCallback(
        (pack: Pack, checked: boolean) => {
            const name = botNameFor(pack);
            setSelected((previous) => {
                const next = new Map(previous);
                const list = (next.get(name) ?? []).filter((number) => number !== pack.number);
                if (checked) {
                    list.push(pack.number);
                }
                if (list.length === 0) {
                    next.delete(name);
                } else {
                    next.set(name, list);
                }
                return next;
            });
        },
        [botNameFor],
    );

    const isSelected = useCallback(
        (pack: Pack) => (selected.get(botNameFor(pack)) ?? []).includes(pack.number),
        [selected, botNameFor],
    );

    const clearSelection = useCallback(() => setSelected(new Map()), []);
    const copyBatch = useCallback(async () => {
        await copyToClipboard(formatBatchXDCC(selected));
    }, [selected]);

    const selectedCount = useMemo(
        () => [...selected.values()].reduce((sum, list) => sum + list.length, 0),
        [selected],
    );

    const columns = useMemo(
        () =>
            buildColumns({
                showBot,
                batchMode,
                botBatchEnabled: batchEnabled,
                botNameFor,
                isSelected,
                onToggleSelect: toggleSelect,
            }),
        [showBot, batchMode, batchEnabled, botNameFor, isSelected, toggleSelect],
    );

    const handleSortChange = useCallback(
        (next: SortState) => update({ sort: next.key, direction: next.direction, page: 1 }),
        [update],
    );

    const heading = showBot ? (hasValidQuery ? 'Search results' : 'Latest packages') : 'Search results';
    const emptyMessage = hasValidQuery ? 'No results' : 'No packs found';
    const showPrompt = showBot && !hasValidQuery;

    return (
        <div className="pack-list">
            <SearchBox
                heading={showBot ? 'Search in selected bot packs' : 'Search form'}
                value={query}
                onSubmit={(nextQuery) => update({ query: nextQuery, page: 1 })}
            />

            {showPrompt ? (
                <div className="my-4 bg-white rounded-sm shadow-sm p-4 text-center dark:bg-gray-800">
                    <h3 className="text-2xl">Search for packs</h3>
                    <p className="text-sm mt-2">Enter at least 3 characters to search across all bots.</p>
                </div>
            ) : (
                <>
                    <div className="flex flex-wrap items-center justify-between gap-2 my-4">
                        <div className="flex items-center gap-3">
                            <h3 className="font-bold text-2xl">{heading}</h3>
                            <PageSizeSelect value={pageSize} onChange={(value) => update({ pageSize: value, page: 1 })} />
                        </div>
                        <BatchControls
                            available={showBot ? batchEnabled : true}
                            enabled={batchMode}
                            selectedCount={selectedCount}
                            onEnable={() => setBatchMode(true)}
                            onDisable={() => setBatchMode(false)}
                            onClear={clearSelection}
                            onCopy={copyBatch}
                        />
                    </div>

                    {pageSize === 'all' ? (
                        <InfinitePacksTable
                            source={source}
                            sort={sortState}
                            enabled={enabled}
                            columns={columns}
                            emptyMessage={emptyMessage}
                            onSortChange={handleSortChange}
                        />
                    ) : (
                        <PaginatedPacksTable
                            source={source}
                            sort={sortState}
                            page={page}
                            pageSize={pageSize}
                            enabled={enabled}
                            columns={columns}
                            emptyMessage={emptyMessage}
                            onSortChange={handleSortChange}
                            onPageChange={(nextPage) => update({ page: nextPage })}
                        />
                    )}
                </>
            )}
        </div>
    );
}
