export function formatMoney(value: string | null | undefined): string {
    if (value === null || value === undefined) {
        return '—';
    }
    return Number(value).toLocaleString('zh-CN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
