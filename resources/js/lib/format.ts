// Atheer figures are in Uzbek som (UZS). Amounts render as whole som with
// space-grouped thousands; the "so'm" suffix keeps it unambiguous.
const numberFmt = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 });

export function money(value: number | null | undefined): string {
    return `${numberFmt.format(Math.round(value ?? 0))} so'm`;
}

export function count(value: number | null | undefined): string {
    return numberFmt.format(value ?? 0);
}

export function shortDate(value: string | null | undefined): string {
    if (!value) return '—';
    const d = new Date(value);
    return Number.isNaN(d.getTime())
        ? value
        : d.toLocaleDateString('en-GB', {
              day: '2-digit',
              month: 'short',
          });
}
