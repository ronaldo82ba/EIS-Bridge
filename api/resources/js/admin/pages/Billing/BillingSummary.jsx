import { Alert, Card, Col, Row, Statistic, Typography } from 'antd';
import { useQuery } from '@tanstack/react-query';
import { billingService } from '../../services/billingService';

export default function BillingSummary() {
    const { data, isLoading, isError, refetch } = useQuery({
        queryKey: ['billing', 'summary'],
        queryFn: async () => {
            const response = await billingService.summary();
            return response.data;
        },
    });

    return (
        <>
            <Typography.Title level={2}>Billing Summary</Typography.Title>

            {isError && (
                <Alert
                    type="error"
                    message="Failed to load billing summary"
                    showIcon
                    action={<Typography.Link onClick={() => refetch()}>Retry</Typography.Link>}
                    style={{ marginBottom: 16 }}
                />
            )}

            <Row gutter={[16, 16]}>
                <Col xs={24} sm={12} lg={6}>
                    <Card loading={isLoading}>
                        <Statistic
                            title="MRR"
                            prefix="₱"
                            value={data?.mrr ?? 0}
                            precision={2}
                        />
                    </Card>
                </Col>
                <Col xs={24} sm={12} lg={6}>
                    <Card loading={isLoading}>
                        <Statistic title="Active Vendor Licenses" value={data?.active_vendor_licenses ?? 0} />
                    </Card>
                </Col>
                <Col xs={24} sm={12} lg={6}>
                    <Card loading={isLoading}>
                        <Statistic title="Active Merchant Licenses" value={data?.active_merchant_licenses ?? 0} />
                    </Card>
                </Col>
                <Col xs={24} sm={12} lg={6}>
                    <Card loading={isLoading}>
                        <Statistic title="Overdue Invoices" value={data?.overdue_invoices ?? 0} />
                    </Card>
                </Col>
            </Row>

            {data?.saas && (
                <Card title="SaaS Breakdown" style={{ marginTop: 16 }} loading={isLoading}>
                    <Typography.Paragraph>
                        Merchants: {data.saas.merchant_count} × ₱{data.saas.merchant_unit_amount} = ₱
                        {data.saas.merchant_charge}
                    </Typography.Paragraph>
                    <Typography.Paragraph>
                        Branches: {data.saas.branch_count} × ₱{data.saas.branch_unit_amount} = ₱
                        {data.saas.branch_charge}
                    </Typography.Paragraph>
                </Card>
            )}
        </>
    );
}
