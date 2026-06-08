import { Alert, Card, Col, Row, Statistic, Tag, Typography } from 'antd';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import {
    monitoringService,
    normalizeWorkers,
    summarizeQueues,
    unwrapPayload,
} from '../../services/monitoringService';

const statusColors = {
    healthy: 'success',
    warning: 'warning',
    critical: 'error',
    running: 'success',
    stopped: 'error',
    unknown: 'default',
};

export default function MonitoringPage() {
    const { role } = useAuth();

    const { data: health, isLoading: healthLoading } = useQuery({
        queryKey: ['monitoring', 'health'],
        queryFn: async () => {
            const response = await monitoringService.health();
            return response.data;
        },
        refetchInterval: 30_000,
    });

    const { data: queues, isLoading: queuesLoading } = useQuery({
        queryKey: ['monitoring', 'queues'],
        queryFn: async () => summarizeQueues(unwrapPayload(await monitoringService.queues())),
        refetchInterval: 15_000,
    });

    const { data: workers, isLoading: workersLoading } = useQuery({
        queryKey: ['monitoring', 'workers'],
        queryFn: async () => normalizeWorkers(unwrapPayload(await monitoringService.workers())),
        refetchInterval: 15_000,
    });

    return (
        <>
            <Typography.Title level={2}>Monitoring</Typography.Title>

            <Row gutter={[16, 16]}>
                <Col xs={24} lg={8}>
                    <Card title="System Health" loading={healthLoading}>
                        <Statistic
                            title="Overall"
                            value={health?.status ?? 'unknown'}
                            valueStyle={{
                                color: health?.status === 'healthy' ? '#3f8600' : '#cf1322',
                            }}
                        />
                        <div style={{ marginTop: 16 }}>
                            {health?.checks &&
                                Object.entries(health.checks).map(([key, check]) => (
                                    <div key={key} style={{ marginBottom: 8 }}>
                                        <Tag color={statusColors[check.status] ?? 'default'}>{key}</Tag>
                                        <Typography.Text>{check.message}</Typography.Text>
                                    </div>
                                ))}
                        </div>
                    </Card>
                </Col>

                <Col xs={24} lg={8}>
                    <Card title="Workers" loading={workersLoading}>
                        <div className="flex flex-wrap gap-2">
                            {(workers ?? []).map((worker) => (
                                <Tag key={worker.name} color={worker.alive ? 'success' : 'error'}>
                                    {worker.name}: {worker.alive ? 'online' : 'offline'}
                                </Tag>
                            ))}
                        </div>
                        {(workers ?? []).length === 0 && (
                            <Typography.Text type="secondary">No worker data</Typography.Text>
                        )}
                    </Card>
                </Col>

                <Col xs={24} lg={8}>
                    <Card title="Queue Summary" loading={queuesLoading}>
                        <Statistic title="Pending" value={queues?.pending_count ?? 0} />
                        <Statistic
                            title="Failed"
                            value={queues?.failed_count ?? 0}
                            style={{ marginTop: 16 }}
                            valueStyle={{
                                color: (queues?.failed_count ?? 0) > 0 ? '#cf1322' : undefined,
                            }}
                        />
                    </Card>
                </Col>
            </Row>

            <Card title="Queue Depth by Name" style={{ marginTop: 16 }} loading={queuesLoading}>
                <Row gutter={[16, 16]}>
                    {(queues?.queues ?? []).map((queue) => (
                        <Col xs={24} sm={12} md={8} lg={6} key={queue.name}>
                            <Card size="small">
                                <Typography.Text strong>{queue.name}</Typography.Text>
                                <div>Depth: {queue.depth}</div>
                                <div>Failed: {queue.failed}</div>
                            </Card>
                        </Col>
                    ))}
                </Row>
            </Card>

            {['super_admin', 'support'].includes(role) && (
                <Alert
                    style={{ marginTop: 16 }}
                    type="info"
                    showIcon
                    message="Queue Monitor"
                    description={
                        <Link to="/monitoring/queues">Open live queue monitor</Link>
                    }
                />
            )}
        </>
    );
}
