type Props = {
    available: boolean;
    enabled: boolean;
    selectedCount: number;
    onEnable: () => void;
    onDisable: () => void;
    onClear: () => void;
    onCopy: () => void;
};

export function BatchControls({
    available,
    enabled,
    selectedCount,
    onEnable,
    onDisable,
    onClear,
    onCopy,
}: Props) {
    if (!available) {
        return null;
    }

    if (!enabled) {
        return (
            <button
                type="button"
                onClick={onEnable}
                className="py-1 px-2 bg-gray-800 hover:bg-gray-700 text-gray-100 dark:bg-gray-900 dark:hover:bg-gray-800"
            >
                Enable batch mode
            </button>
        );
    }

    return (
        <div className="flex items-center gap-2">
            <button
                type="button"
                onClick={onDisable}
                className="py-1 px-2 border border-gray-800 hover:bg-gray-700 text-gray-700 hover:text-gray-100 dark:text-gray-300 dark:hover:text-gray-200"
            >
                Disable batch mode
            </button>
            <button
                type="button"
                onClick={onClear}
                className="py-1 px-2 border border-gray-800 hover:bg-gray-700 text-gray-700 hover:text-gray-100 dark:text-gray-300 dark:hover:text-gray-200"
            >
                Clear selected
            </button>
            <button
                type="button"
                onClick={onCopy}
                className="py-1 px-2 bg-gray-800 hover:bg-gray-700 text-gray-700 hover:text-gray-100 dark:text-gray-300 dark:hover:text-gray-200"
            >
                Copy selected{selectedCount > 0 ? ` (${selectedCount})` : ''}
            </button>
        </div>
    );
}
