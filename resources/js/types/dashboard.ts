export type SectionStatus = 'live' | 'planned';
export type SectionDriver = 'atheer' | 'entries';

export type Section = {
    key: string;
    label: string;
    description: string;
    icon: string;
    driver: SectionDriver;
    money: boolean;
    status: SectionStatus;
    // The section's entries are split by the owner's houses (home business).
    houses?: boolean;
};

export type Entry = {
    id: number;
    section: string;
    house_id: number | null;
    body: string;
    amount: number | null;
    occurred_at: string;
    tags: string[];
};

// Summed by the server over every matching entry, not just the listed page.
export type MoneyTotals = {
    entries: number;
    income: number;
    expense: number;
    net: number;
};

export type HouseSummary = {
    id: number;
    name: string;
    totals: MoneyTotals;
};

// What a section split by house shows: one house (its id), the entries filed
// under no house ('none'), or every house (null).
export type HouseFilter = number | 'none' | null;
