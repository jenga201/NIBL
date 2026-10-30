import { useState } from 'react';
import { copyToClipboard } from './clipboard';
import { formatSingleXDCC } from './format';
import type { Pack } from './types';

type Props = {
    pack: Pack;
    botName: string;
};

export function CopyButton({ pack, botName }: Props) {
    const [copied, setCopied] = useState(false);

    const handleCopy = async () => {
        const ok = await copyToClipboard(formatSingleXDCC(botName, pack.number));
        if (ok) {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1000);
        }
    };

    return (
        <button
            type="button"
            className="w-20 py-1 px-2 text-center whitespace-nowrap bg-gray-300 hover:bg-gray-200 dark:bg-gray-900 dark:hover:bg-gray-800"
            onClick={handleCopy}
        >
            {copied ? 'Copied' : 'Copy'}
        </button>
    );
}
