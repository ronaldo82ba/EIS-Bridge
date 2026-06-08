import { Card, Col, Row, Statistic } from 'antd';

export default function QueueDepthCard({ queue, loading }) {
    return (
        <Row gutter={[16, 16]}>
            <Col xs={24} sm={12}>
                <Card title="Queue Depth" loading={loading}>
                    <Statistic title="Pending jobs" value={queue?.pending_count ?? 0} />
                </Card>
            </Col>
            <Col xs={24} sm={12}>
                <Card title="Failed jobs" loading={loading}>
                    <Statistic
                        title="Failed count"
                        value={queue?.failed_count ?? 0}
                        valueStyle={{ color: (queue?.failed_count ?? 0) > 0 ? '#cf1322' : undefined }}
                    />
                </Card>
            </Col>
        </Row>
    );
}
