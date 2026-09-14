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
};

export type Entry = {
    id: number;
    section: string;
    body: string;
    amount: number | null;
    occurred_at: string;
    tags: string[];
};
