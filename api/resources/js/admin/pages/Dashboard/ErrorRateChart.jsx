import { Card, Progress, Table, Typography } from 'antd';

export default function ErrorRateChart({ errorRate, series = [], loading }) {
    const rate = errorRate?.rate ?? errorRate ?? 0;
    const percent = Math.round(Number(rate) * 100);

    const columns = [
        { title: 'Hour', dataIndex: 'hour', key: 'hour' },
        { title: 'Processed', dataIndex: 'processed', key: 'processed' },
        { title: 'Failed', dataIndex: 'failed', key: 'failed' },
        {
            title: 'Error rate',
            dataIndex: 'rate',
            key: 'rate',
            render: (value) => `${Math.round(Number(value ?? 0) * 100)}%`,
        },
    ];

    return (
        <Card title="Error Rate" loading={loading}>
            <Progress
                percent={percent}
                status={percent > 10 ? 'exception' : percent > 5 ? 'active' : 'success'}
                style={{ marginBottom: 16 }}
            />
            {series.length > 0 ? (
                <Table
                    columns={columns}
                    dataSource={series}
                    rowKey={(row) => row.hour ?? row.label}
                    pagination={false}
                    size="small"
                />
            ) : (
                <Typography.Text type="secondary">No error rate data for today.</Typography.Text>
            )}
        </Card>
    );
}
