export function formatSingleXDCC(botName: string, packNumber: number): string {
    return `/msg ${botName} xdcc send #${packNumber}`;
}

export function formatBatchXDCC(selected: ReadonlyMap<string, number[]>): string {
    const lines: string[] = [];

    selected.forEach((numbers, botName) => {
        lines.push(`/msg ${botName} xdcc batch ${numbers.join(',')}`);
    });

    return lines.join('\n');
}
