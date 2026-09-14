export type CharityMonth = {
    month: string;
    label: string;
    profit: number;
    auto_profit: number;
    is_override: boolean;
    percentage: number;
    obligation: number;
    given: number;
    remaining: number;
};

export type CharityGiving = {
    id: number;
    body: string;
    amount: number;
    occurred_at: string;
};
