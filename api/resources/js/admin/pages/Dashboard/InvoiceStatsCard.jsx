import { Card, Col, Row, Statistic } from 'antd';

export default function InvoiceStatsCard({ stats, loading }) {
    const items = [
        { title: 'Total Today', value: stats?.total_today ?? 0 },
        { title: 'Sent', value: stats?.sent ?? 0 },
        { title: 'Acknowledged', value: stats?.acknowledged ?? 0 },
        { title: 'Rejected', value: stats?.rejected ?? 0 },
    ];

    return (
        <Row gutter={[16, 16]}>
            {items.map((item) => (
                <Col key={item.title} xs={24} sm={12} lg={6}>
                    <Card loading={loading}>
                        <Statistic title={item.title} value={item.value} />
                    </Card>
                </Col>
            ))}
        </Row>
    );
}
