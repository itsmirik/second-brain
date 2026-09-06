// Shapes mirror Atheer's ReportService (read-only report API).

export type FunnelStage = {
    stage: string;
    label: string;
    count: number;
};

export type InTransitDelivery = {
    id: number;
    order_id: number | null;
    recipient_name: string | null;
    recipient_phone: string | null;
    cod_amount: number;
    dispatched_at: string | null;
};

export type FlaggedDelivery = {
    id: number;
    order_id: number | null;
    recipient_name: string | null;
    recipient_phone: string | null;
    cod_amount: number;
    dispatched_at: string | null;
    flag_reason: string | null;
};

export type DeliveriesNow = {
    in_transit: number;
    delivered_today: number;
    flagged: number;
    flagged_amount: number;
    in_transit_list: InTransitDelivery[];
};

export type Reconciliation = {
    flagged_count: number;
    flagged_amount: number;
    items: FlaggedDelivery[];
};

export type Finance = {
    from: string;
    to: string;
    revenue: number;
    profit: number;
    refunds: number;
    net: number;
    orders_count: number;
};

export type AtheerSummary = {
    funnel: FunnelStage[];
    deliveries: DeliveriesNow;
    reconciliation: Reconciliation;
    finance_month: Finance;
    generated_at: string;
};
