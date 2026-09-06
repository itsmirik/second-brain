export type PeriodType = 'day' | 'week' | 'month' | 'quarter' | 'year';

export type PeriodMeta = {
    type: PeriodType;
    label: string;
    from: string;
    to: string;
    anchor: string;
    previous: string;
    next: string;
    canGoNext: boolean;
};
