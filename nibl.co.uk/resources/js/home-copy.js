import { copyToClipboard } from './features/packlist/clipboard';
import { formatSingleXDCC, formatBatchXDCC } from './features/packlist/format';

const setBatchControls = (enabled) => {
    const toggle = (id, show) => {
        const element = document.getElementById(id);
        if (element) {
            element.classList.toggle('hidden', !show);
        }
    };

    toggle('enable-copy-as-batch', !enabled);
    toggle('disable-copy-as-batch', enabled);
    toggle('clear-selected-batch', enabled);
    toggle('copy-as-batch', enabled);

    document.querySelectorAll('.batch-copy-checkbox').forEach((label) => {
        label.classList.toggle('hidden', !enabled);
        label.classList.toggle('flex', enabled);
    });

    document.querySelectorAll('.copy-data').forEach((button) => {
        button.classList.toggle('hidden', enabled);
    });
};

const clearBatchSelection = () => {
    document.querySelectorAll('input[name="batch"]:checked').forEach((input) => {
        input.checked = false;
    });
};

const batchSelections = () => {
    const grouped = new Map();

    document.querySelectorAll('input[name="batch"][data-botname]:checked').forEach((input) => {
        const botName = input.dataset.botname;
        const packNumber = Number(input.dataset.botpack);

        if (!grouped.has(botName)) {
            grouped.set(botName, []);
        }
        grouped.get(botName).push(packNumber);
    });

    return grouped;
};

document.addEventListener('click', (event) => {
    const target = event.target;

    if (target.matches('.copy-data')) {
        event.preventDefault();

        copyToClipboard(formatSingleXDCC(target.dataset.botname, Number(target.dataset.botpack))).then((ok) => {
            if (ok) {
                const label = target.textContent;
                target.textContent = 'Copied';
                window.setTimeout(() => {
                    target.textContent = label;
                }, 1000);
            }
        });
        return;
    }

    if (target.id === 'enable-copy-as-batch') {
        event.preventDefault();
        setBatchControls(true);
        return;
    }

    if (target.id === 'disable-copy-as-batch') {
        event.preventDefault();
        setBatchControls(false);
        clearBatchSelection();
        return;
    }

    if (target.id === 'clear-selected-batch') {
        event.preventDefault();
        clearBatchSelection();
        return;
    }

    if (target.id === 'copy-as-batch') {
        event.preventDefault();

        const grouped = batchSelections();
        if (grouped.size > 0) {
            copyToClipboard(formatBatchXDCC(grouped));
        }
    }
});
