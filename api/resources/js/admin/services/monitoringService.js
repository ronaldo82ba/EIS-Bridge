import api from './api';

export function unwrapPayload(response) {
    const body = response?.data;

    if (body?.data !== undefined && body?.data !== null) {
        return body.data;
    }

    return body;
}

export function normalizeWorkers(payload) {
    if (Array.isArray(payload)) {
        return payload;
    }

    if (Array.isArray(payload?.heartbeats)) {
        return payload.heartbeats;
    }

    return [];
}

export function normalizeQueues(payload) {
    if (!payload || typeof payload !== 'object') {
        return {};
    }

    if (Array.isArray(payload.queues)) {
        return payload.queues.reduce((acc, queue) => {
            acc[queue.name] = {
                waiting: queue.depth ?? 0,
                processing: 0,
            };

            return acc;
        }, {});
    }

    return Object.fromEntries(
        Object.entries(payload).filter(([key]) => !['pending_count', 'failed_count', 'checked_at'].includes(key)),
    );
}

export function summarizeQueues(payload) {
    if (payload?.queues && typeof payload.pending_count === 'number') {
        return payload;
    }

    const map = normalizeQueues(payload);
    const entries = Object.entries(map);

    return {
        pending_count: entries.reduce(
            (sum, [, stats]) => sum + (stats.waiting ?? 0) + (stats.processing ?? 0),
            0,
        ),
        failed_count: payload?.failed_count ?? 0,
        queues: entries.map(([name, stats]) => ({
            name,
            depth: (stats.waiting ?? 0) + (stats.processing ?? 0),
            failed: stats.failed ?? 0,
        })),
    };
}

export function normalizeFailedJobs(payload) {
    if (Array.isArray(payload)) {
        return payload;
    }

    if (Array.isArray(payload?.data)) {
        return payload.data;
    }

    return [];
}

export const monitoringService = {
    queues: () => api.get('/monitoring/queues'),
    workers: () => api.get('/monitoring/workers'),
    failed: () => api.get('/monitoring/failed'),
    health: () => api.get('/monitoring/health'),
};
