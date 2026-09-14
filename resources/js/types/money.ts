export type MoneySource = {
    key: string;
    label: string;
    income: number;
    expense: number;
    net: number;
    available: boolean;
};

export type MoneyReport = {
    sources: MoneySource[];
    income: number;
    expense: number;
    net: number;
    charity_given: number;
};
