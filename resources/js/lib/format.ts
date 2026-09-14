// Atheer figures are in Uzbek som (UZS). Amounts render as whole som with
// space-grouped thousands; the "сум" suffix keeps it unambiguous.
const numberFmt = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 });

export function money(value: number | null | undefined): string {
    return `${numberFmt.format(Math.round(value ?? 0))} сум`;
}

export function count(value: number | null | undefined): string {
    return numberFmt.format(value ?? 0);
}

export function shortDate(value: string | null | undefined): string {
    if (!value) return '—';
    const d = new Date(value);
    return Number.isNaN(d.getTime())
        ? value
        : d.toLocaleDateString('ru-RU', {
              day: '2-digit',
              month: 'short',
          });
}
