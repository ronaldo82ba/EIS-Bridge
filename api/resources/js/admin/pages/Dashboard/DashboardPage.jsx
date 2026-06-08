import { Alert, Card, Col, Row, Statistic, Typography } from 'antd';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { dashboardService } from '../../services/dashboardService';
import { monitoringService, normalizeFailedJobs, summarizeQueues, unwrapPayload } from '../../services/monitoringService';
import ErrorRateChart from './ErrorRateChart';
import CertificateAlertsPanel from './CertificateAlertsPanel';
import InvoiceStatsCard from './InvoiceStatsCard';
import QueueDepthCard from './QueueDepthCard';
import RecentErrorsTable from './RecentErrorsTable';

export default function DashboardPage() {
    const {
        data: dashboard,
        isLoading: dashboardLoading,
        isError: dashboardError,
        refetch: refetchDashboard,
    } = useQuery({
        queryKey: ['dashboard'],
        queryFn: async () => {
            const response = await dashboardService.get();
            return response.data;
        },
    });

    const { data: queue, isLoading: queueLoading } = useQuery({
        queryKey: ['monitoring', 'queues'],
        queryFn: async () => summarizeQueues(unwrapPayload(await monitoringService.queues())),
    });

    const { data: failedJobs, isLoading: failedLoading } = useQuery({
        queryKey: ['monitoring', 'failed'],
        queryFn: async () => normalizeFailedJobs(unwrapPayload(await monitoringService.failed())),
    });

    const queueSummary = {
        ...(queue ?? {}),
        failed_count: failedJobs?.length ?? queue?.failed_count ?? 0,
    };

    const invoiceStats = dashboard?.invoice_stats ?? dashboard?.invoices ?? dashboard;
    const errorRate = dashboard?.error_rate;
    const errorSeries = dashboard?.error_rate_series ?? dashboard?.error_rate_by_hour ?? [];
    const recentErrors = dashboard?.recent_errors ?? dashboard?.latest_errors ?? [];
    const certificateAlerts = dashboard?.certificate_alerts;

    return (
        <>
            <Typography.Title level={2}>Dashboard</Typography.Title>

            {dashboardError && (
                <Alert
                    type="error"
                    message="Failed to load dashboard"
                    description="The admin dashboard API is not available yet. Showing queue data where possible."
                    showIcon
                    action={
                        <Typography.Link onClick={() => refetchDashboard()}>Retry</Typography.Link>
                    }
                    style={{ marginBottom: 16 }}
                />
            )}

            <InvoiceStatsCard stats={invoiceStats} loading={dashboardLoading} />

            {(dashboard?.alerts?.critical ?? 0) > 0 && (
                <Alert
                    type="error"
                    showIcon
                    style={{ marginTop: 16 }}
                    message={`${dashboard.alerts.critical} critical alert(s) active`}
                    description={<Link to="/alerts">View alerts</Link>}
                />
            )}

            <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
                <Col xs={24} sm={8}>
                    <Card loading={dashboardLoading}>
                        <Statistic
                            title="Critical alerts"
                            value={dashboard?.alerts?.critical ?? 0}
                            valueStyle={{
                                color: (dashboard?.alerts?.critical ?? 0) > 0 ? '#cf1322' : undefined,
                            }}
                        />
                    </Card>
                </Col>
                <Col xs={24} sm={8}>
                    <Card loading={dashboardLoading}>
                        <Statistic title="Warning alerts" value={dashboard?.alerts?.warning ?? 0} />
                    </Card>
                </Col>
                <Col xs={24} sm={8}>
                    <Card loading={dashboardLoading}>
                        <Statistic title="Unacknowledged" value={dashboard?.alerts?.unacknowledged ?? 0} />
                    </Card>
                </Col>
            </Row>

            <div style={{ marginTop: 16 }}>
                <QueueDepthCard queue={queueSummary ?? dashboard?.queue} loading={queueLoading || failedLoading || dashboardLoading} />
            </div>

            <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
                <Col xs={24} lg={12}>
                    <ErrorRateChart
                        errorRate={errorRate}
                        series={errorSeries}
                        loading={dashboardLoading}
                    />
                </Col>
                <Col xs={24} lg={12}>
                    <RecentErrorsTable errors={recentErrors} loading={dashboardLoading} />
                </Col>
            </Row>

            <div style={{ marginTop: 16 }}>
                <CertificateAlertsPanel summary={certificateAlerts} loading={dashboardLoading} />
            </div>
        </>
    );
}
