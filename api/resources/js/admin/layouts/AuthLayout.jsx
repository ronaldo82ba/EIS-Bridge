import { Card, Layout, Typography } from 'antd';
import { Outlet } from 'react-router-dom';

const { Content } = Layout;

export default function AuthLayout() {
    return (
        <Layout style={{ minHeight: '100vh', background: '#F7F9FA' }}>
            <Content
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    padding: 24,
                }}
            >
                <Card style={{ width: 400, maxWidth: '100%' }}>
                    <Typography.Title level={3} style={{ marginTop: 0 }}>
                        EIS Bridge Console
                    </Typography.Title>
                    <Outlet />
                </Card>
            </Content>
        </Layout>
    );
}
