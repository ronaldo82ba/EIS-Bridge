import { Card, Col, Row, Statistic, Typography } from 'antd';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import {
    monitoringService,
    normalizeWorkers,
    summarizeQueues,
    unwrapPayload,
} from '../../services/monitoringService';

export default function QueueStatus() {
    const { data: queues, isLoading } = useQuery({
        queryKey: ['monitoring', 'queues'],
        queryFn: async () => summarizeQueues(unwrapPayload(await monitoringService.queues())),
        refetchInterval: 15_000,
    });

    const { data: workers, isLoading: workersLoading } = useQuery({
        queryKey: ['monitoring', 'workers'],
        queryFn: async () => normalizeWorkers(unwrapPayload(await monitoringService.workers())),
        refetchInterval: 15_000,
    });

    const onlineWorkers = (workers ?? []).filter((worker) => worker.alive).length;

    return (
        <>
            <Typography.Title level={2}>Queues & Jobs</Typography.Title>
            <Row gutter={[16, 16]}>
                <Col xs={24} sm={12} md={6}>
                    <Card title="Pending jobs" loading={isLoading}>
                        <Statistic value={queues?.pending_count ?? 0} />
                    </Card>
                </Col>
                <Col xs={24} sm={12} md={6}>
                    <Card title="Failed jobs" loading={isLoading}>
                        <Statistic
                            value={queues?.failed_count ?? 0}
                            valueStyle={{
                                color: (queues?.failed_count ?? 0) > 0 ? '#cf1322' : undefined,
                            }}
                        />
                    </Card>
                </Col>
                <Col xs={24} sm={12} md={6}>
                    <Card title="Worker status" loading={workersLoading}>
                        <Statistic value={`${onlineWorkers}/${workers?.length ?? 0}`} />
                        <Typography.Text type="secondary">online workers</Typography.Text>
                    </Card>
                </Col>
                <Col xs={24} sm={12} md={6}>
                    <Card title="Monitoring">
                        <Link to="/monitoring/queues">Open queue monitor</Link>
                    </Card>
                </Col>
            </Row>

            <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
                {(queues?.queues ?? []).map((queue) => (
                    <Col xs={24} sm={12} md={8} lg={6} key={queue.name}>
                        <Card title={queue.name} loading={isLoading} size="small">
                            <div>Depth: {queue.depth}</div>
                            <div>Failed: {queue.failed}</div>
                        </Card>
                    </Col>
                ))}
            </Row>
        </>
    );
}
