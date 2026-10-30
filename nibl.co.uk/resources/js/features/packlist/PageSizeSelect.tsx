import type { ChangeEvent } from 'react';
import type { PageSize } from './types';

type Props = {
    value: PageSize;
    onChange: (value: PageSize) => void;
};

const OPTIONS: { value: string; label: string }[] = [
    { value: '100', label: '100' },
    { value: '1000', label: '1000' },
    { value: 'all', label: 'All' },
];

export function PageSizeSelect({ value, onChange }: Props) {
    const handleChange = (event: ChangeEvent<HTMLSelectElement>) => {
        const raw = event.target.value;
        const next: PageSize = raw === 'all' ? 'all' : (Number(raw) as 100 | 1000);
        onChange(next);
    };

    return (
        <label className="inline-flex items-center gap-2 text-sm">
            <span>Show</span>
            <select
                value={String(value)}
                onChange={handleChange}
                className="py-1 px-2 border bg-white dark:bg-gray-900 dark:border-gray-700"
            >
                {OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </label>
    );
}
