import { useEffect, useRef } from 'react';
import { isEchoEnabled, subscribeToChannel } from '../echo';

const FAILED_STATUSES = ['failed', 'retry_failed', 'transmission_failed'];

function round(value, decimals = 2) {
    const factor = 10 ** decimals;
    return Math.round(value * factor) / factor;
}

function isErrorInvoice(processingStatus, eisStatus) {
    return eisStatus === 'rejected' || FAILED_STATUSES.includes(processingStatus);
}

function resolveStatusBucket(processingStatus, eisStatus) {
    if (eisStatus === 'acknowledged') {
        return 'acknowledged';
    }

    if (eisStatus === 'rejected') {
        return 'rejected';
    }

    if (FAILED_STATUSES.includes(processingStatus)) {
        return 'failed';
    }

    return 'pending';
}

function recalcEisAckRate(kpi) {
    const responded = kpi.ack + kpi.rejected;

    if (responded === 0) {
        return kpi.total > 0 ? round((kpi.ack / kpi.total) * 100, 1) : 0;
    }

    return round((kpi.ack / responded) * 100, 1);
}

function recalcErrorRate(kpi, errorCount) {
    return kpi.total > 0 ? round((errorCount / kpi.total) * 100, 2) : 0;
}

function incrementDailyBucket(daily, day) {
    const index = daily.labels.indexOf(day);

    if (index === -1) {
        return daily;
    }

    const values = [...daily.values];
    values[index] = (values[index] ?? 0) + 1;

    return { ...daily, values };
}

function incrementStatusBreakdown(statusBreakdown, bucket) {
    if (!statusBreakdown || bucket === undefined) {
        return statusBreakdown;
    }

    return {
        ...statusBreakdown,
        [bucket]: (statusBreakdown[bucket] ?? 0) + 1,
    };
}

function incrementEisBreakdown(eisBreakdown, eisStatus) {
    if (!eisBreakdown) {
        return eisBreakdown;
    }

    const next = { ...eisBreakdown };

    if (eisStatus === 'acknowledged') {
        next.ack = (next.ack ?? 0) + 1;
    } else if (eisStatus === 'rejected') {
        next.rejected = (next.rejected ?? 0) + 1;
    } else {
        next.pending = (next.pending ?? 0) + 1;
    }

    return next;
}

function incrementTopMerchant(topMerchants, merchantCode) {
    if (!topMerchants || !merchantCode) {
        return topMerchants;
    }

    const index = topMerchants.findIndex((merchant) => merchant.merchant_code === merchantCode);

    if (index === -1) {
        return topMerchants;
    }

    const next = [...topMerchants];
    next[index] = { ...next[index], count: next[index].count + 1 };

    return next;
}

function matchesFilters(event, filters) {
    if (filters.merchantId && String(event.merchant_id) !== String(filters.merchantId)) {
        return false;
    }

    if (filters.vendorId && String(event.vendor_id) !== String(filters.vendorId)) {
        return false;
    }

    return true;
}

function eventDayInRange(daily, createdAt) {
    if (!daily?.labels?.length || !createdAt) {
        return false;
    }

    const day = createdAt.slice(0, 10);

    return daily.labels.includes(day);
}

/**
 * Apply a lightweight analytics delta from a broadcast event.
 */
export function applyRealtimeUpdate(prevData, event, filters = {}) {
    if (!prevData || !event || !matchesFilters(event, filters)) {
        return prevData;
    }

    if (!eventDayInRange(prevData.daily, event.created_at)) {
        return prevData;
    }

    const { processing_status: processingStatus, eis_status: eisStatus, event_type: eventType } = event;
    const bucket = resolveStatusBucket(processingStatus, eisStatus);
    const prevTotal = prevData.kpi?.total ?? 0;
    let errorCount = Math.round(((prevData.kpi?.error_rate ?? 0) / 100) * prevTotal);

    const kpi = { ...prevData.kpi };

    if (eventType === 'new_invoice') {
        kpi.total = prevTotal + 1;

        if (eisStatus === 'acknowledged') {
            kpi.ack = (kpi.ack ?? 0) + 1;
        }

        if (eisStatus === 'rejected') {
            kpi.rejected = (kpi.rejected ?? 0) + 1;
        }

        if (isErrorInvoice(processingStatus, eisStatus)) {
            errorCount += 1;
        }
    } else {
        if (eisStatus === 'acknowledged') {
            kpi.ack = (kpi.ack ?? 0) + 1;
        }

        if (eisStatus === 'rejected') {
            kpi.rejected = (kpi.rejected ?? 0) + 1;
        }

        if (isErrorInvoice(processingStatus, eisStatus)) {
            errorCount += 1;
        }
    }

    kpi.error_rate = recalcErrorRate(kpi, errorCount);

    if (kpi.eis_ack_rate !== undefined) {
        kpi.eis_ack_rate = recalcEisAckRate(kpi);
    }

    if (kpi.retry_failed !== undefined && processingStatus === 'retry_failed') {
        kpi.retry_failed = (kpi.retry_failed ?? 0) + 1;
    }

    const next = {
        ...prevData,
        kpi,
        daily: eventType === 'new_invoice'
            ? incrementDailyBucket(prevData.daily, event.created_at.slice(0, 10))
            : prevData.daily,
        status_breakdown: incrementStatusBreakdown(prevData.status_breakdown, bucket),
        top_merchants: eventType === 'new_invoice'
            ? incrementTopMerchant(prevData.top_merchants, event.merchant_code)
            : prevData.top_merchants,
    };

    if (prevData.eis_ack_rate !== undefined && kpi.eis_ack_rate === undefined) {
        next.eis_ack_rate = recalcEisAckRate(kpi);
    }

    if (prevData.eis_breakdown) {
        next.eis_breakdown = incrementEisBreakdown(prevData.eis_breakdown, eisStatus);
    }

    if (prevData.retry_pressure && processingStatus === 'retry_failed') {
        next.retry_pressure = {
            ...prevData.retry_pressure,
            retry_failed: (prevData.retry_pressure.retry_failed ?? 0) + 1,
        };
    }

    if (prevData.retry_pressure && processingStatus === 'transmission_failed') {
        next.retry_pressure = {
            ...next.retry_pressure ?? prevData.retry_pressure,
            transmission_failed: (prevData.retry_pressure.transmission_failed ?? 0) + 1,
        };
    }

    return next;
}

/**
 * Subscribe to analytics broadcast updates when Echo is enabled.
 */
export function useRealtimeAnalytics({ data, setData, filters = {}, onRetryFailed }) {
    const setDataRef = useRef(setData);
    const filtersRef = useRef(filters);
    const onRetryFailedRef = useRef(onRetryFailed);

    useEffect(() => {
        setDataRef.current = setData;
        filtersRef.current = filters;
        onRetryFailedRef.current = onRetryFailed;
    });

    useEffect(() => {
        if (!isEchoEnabled()) {
            return undefined;
        }

        return subscribeToChannel('analytics', '.analytics.updated', (payload) => {
            setDataRef.current((previous) => {
                if (!previous) {
                    return previous;
                }

                return applyRealtimeUpdate(previous, payload, filtersRef.current);
            });

            if (payload.processing_status === 'retry_failed' && onRetryFailedRef.current) {
                onRetryFailedRef.current(payload);
            }
        });
    }, []);
}
