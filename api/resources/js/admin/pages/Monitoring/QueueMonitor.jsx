import { useCallback, useEffect, useState } from 'react';
import StatusBadge from '../../components/StatusBadge';
import { useAuth } from '../../hooks/useAuth';
import { queueService } from '../../services/queueService';
import {
    monitoringService,
    normalizeFailedJobs,
    normalizeQueues,
    normalizeWorkers,
    unwrapPayload,
} from '../../services/monitoringService';

const REFRESH_MS = 5000;
const echoEnabled = Boolean(import.meta.env.VITE_PUSHER_APP_KEY);

const QUEUE_ORDER = ['mapping', 'signing', 'transmission', 'retry', 'webhooks', 'default'];

function sortQueueEntries(entries) {
    return [...entries].sort(([a], [b]) => {
        const aIndex = QUEUE_ORDER.indexOf(a);
        const bIndex = QUEUE_ORDER.indexOf(b);

        if (aIndex === -1 && bIndex === -1) {
            return a.localeCompare(b);
        }

        if (aIndex === -1) {
            return 1;
        }

        if (bIndex === -1) {
            return -1;
        }

        return aIndex - bIndex;
    });
}

export default function QueueMonitor() {
    const { role } = useAuth();
    const [data, setData] = useState(null);
    const [error, setError] = useState(null);
    const [actionError, setActionError] = useState(null);
    const [actionLoadingId, setActionLoadingId] = useState(null);

    const load = useCallback(async () => {
        try {
            const [workersRes, queuesRes, failedRes] = await Promise.all([
                monitoringService.workers(),
                monitoringService.queues(),
                monitoringService.failed(),
            ]);

            setData({
                workers: normalizeWorkers(unwrapPayload(workersRes)),
                queues: normalizeQueues(unwrapPayload(queuesRes)),
                failed: normalizeFailedJobs(unwrapPayload(failedRes)),
            });
            setError(null);
        } catch (loadError) {
            setError(
                loadError.response?.data?.message ??
                    loadError.friendlyMessage ??
                    'Failed to load queue monitor data.',
            );
        }
    }, []);

    useEffect(() => {
        load();
        const interval = setInterval(load, REFRESH_MS);

        return () => clearInterval(interval);
    }, [load]);

    useEffect(() => {
        if (!echoEnabled) {
            return undefined;
        }

        let unsubscribe = () => {};

        import('../../echo').then(({ subscribeToChannel }) => {
            unsubscribe = subscribeToChannel('queues', '.queues.updated', (payload) => {
                const queues = normalizeQueues(payload?.queues ?? {});

                setData((previous) => ({
                    workers: previous?.workers ?? [],
                    failed: previous?.failed ?? [],
                    queues,
                }));
            });
        });

        return () => unsubscribe();
    }, []);

    const handleRetry = async (id) => {
        setActionError(null);
        setActionLoadingId(`retry-${id}`);

        try {
            await queueService.retryJob(id);
            await load();
        } catch (retryError) {
            setActionError(
                retryError.response?.data?.message ??
                    retryError.friendlyMessage ??
                    'Failed to retry job.',
            );
        } finally {
            setActionLoadingId(null);
        }
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Delete this failed job permanently?')) {
            return;
        }

        setActionError(null);
        setActionLoadingId(`delete-${id}`);

        try {
            await queueService.deleteJob(id);
            await load();
        } catch (deleteError) {
            setActionError(
                deleteError.response?.data?.message ??
                    deleteError.friendlyMessage ??
                    'Failed to delete job.',
            );
        } finally {
            setActionLoadingId(null);
        }
    };

    if (!data && !error) {
        return (
            <div className="flex items-center justify-center p-12">
                <p className="text-sm text-slate-500">Loading…</p>
            </div>
        );
    }

    const { workers = [], queues = {}, failed = [] } = data ?? {};
    const queueEntries = sortQueueEntries(Object.entries(queues));
    const totalWaiting = queueEntries.reduce((sum, [, stats]) => sum + (stats.waiting ?? 0), 0);
    const totalProcessing = queueEntries.reduce((sum, [, stats]) => sum + (stats.processing ?? 0), 0);

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold text-slate-900">Queue Monitor</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Live pipeline health
                        {echoEnabled
                            ? ' — realtime updates with 5s poll fallback'
                            : ' — auto-refreshes every 5 seconds'}
                    </p>
                </div>
                {role === 'super_admin' && (
                    <a
                        href="/horizon"
                        target="_blank"
                        rel="noreferrer"
                        className="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 transition hover:bg-slate-50"
                    >
                        Open Horizon
                    </a>
                )}
            </div>

            {error && (
                <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {error}
                </div>
            )}

            {actionError && (
                <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {actionError}
                </div>
            )}

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p className="text-sm text-slate-500">Jobs waiting</p>
                    <p className="mt-1 text-3xl font-bold text-slate-800">{totalWaiting}</p>
                </div>
                <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p className="text-sm text-slate-500">Jobs processing</p>
                    <p className="mt-1 text-3xl font-bold text-slate-800">{totalProcessing}</p>
                </div>
                <div className="rounded-lg border border-slate-200 border-l-4 border-l-red-500 bg-white p-5 shadow-sm">
                    <p className="text-sm text-slate-500">Failed jobs</p>
                    <p className="mt-1 text-3xl font-bold text-slate-800">{failed.length}</p>
                </div>
            </div>

            <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 className="mb-3 font-medium text-slate-900">Worker Status</h2>
                {workers.length === 0 ? (
                    <p className="text-sm text-slate-500">No worker heartbeat data available.</p>
                ) : (
                    <div className="flex flex-wrap gap-3">
                        {workers.map((worker) => (
                            <div
                                key={worker.name}
                                className={`rounded px-4 py-2 text-sm ${
                                    worker.alive
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'bg-rose-100 text-rose-700'
                                }`}
                            >
                                {worker.name}: {worker.alive ? 'Online' : 'Offline'}
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-6">
                {queueEntries.map(([queue, stats]) => (
                    <div key={queue} className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="mb-1 text-sm uppercase text-slate-500">{queue}</div>
                        <div className="text-2xl font-semibold text-slate-900">{stats.waiting ?? 0}</div>
                        <div className="text-xs text-slate-500">waiting</div>
                        <div className="mt-2 text-sm text-slate-600">
                            Processing: {stats.processing ?? 0}
                        </div>
                    </div>
                ))}
            </div>

            <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <div className="mb-3 flex items-center justify-between gap-3">
                    <h2 className="font-medium text-slate-900">Failed Jobs</h2>
                    {failed.length > 0 && <StatusBadge status="failed" label={`${failed.length} failed`} />}
                </div>

                {failed.length === 0 ? (
                    <p className="text-sm text-slate-500">No failed jobs — pipeline healthy</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-slate-50">
                                    <th className="px-3 py-2 text-left font-medium text-slate-600">Queue</th>
                                    <th className="px-3 py-2 text-left font-medium text-slate-600">Exception</th>
                                    <th className="px-3 py-2 text-left font-medium text-slate-600">Failed At</th>
                                    <th className="px-3 py-2 text-left font-medium text-slate-600">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {failed.map((job) => (
                                    <tr key={job.id ?? `${job.queue}-${job.failed_at}`} className="border-b">
                                        <td className="px-3 py-2">{job.queue}</td>
                                        <td className="max-w-md truncate px-3 py-2 text-rose-600" title={job.exception}>
                                            {job.exception}
                                        </td>
                                        <td className="whitespace-nowrap px-3 py-2 text-slate-600">
                                            {job.failed_at}
                                        </td>
                                        <td className="px-3 py-2">
                                            <div className="flex flex-wrap gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => handleRetry(job.id)}
                                                    disabled={!job.id || actionLoadingId === `retry-${job.id}`}
                                                    className="rounded border border-slate-200 px-2 py-1 text-xs text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                                                >
                                                    {actionLoadingId === `retry-${job.id}` ? 'Retrying…' : 'Retry'}
                                                </button>
                                                {role === 'super_admin' && (
                                                    <button
                                                        type="button"
                                                        onClick={() => handleDelete(job.id)}
                                                        disabled={
                                                            !job.id || actionLoadingId === `delete-${job.id}`
                                                        }
                                                        className="rounded border border-red-200 px-2 py-1 text-xs text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
                                                    >
                                                        {actionLoadingId === `delete-${job.id}`
                                                            ? 'Deleting…'
                                                            : 'Delete'}
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );
}
