import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ConfigProvider } from 'antd';
import App from './App';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            retry: 1,
            refetchOnWindowFocus: false,
        },
    },
});

const theme = {
    token: {
        colorPrimary: '#0057D9',
        colorSuccess: '#00A8A8',
        colorWarning: '#FFC300',
    },
};

createRoot(document.getElementById('admin-root')).render(
    <StrictMode>
        <QueryClientProvider client={queryClient}>
            <ConfigProvider theme={theme}>
                <BrowserRouter basename="/admin">
                    <App />
                </BrowserRouter>
            </ConfigProvider>
        </QueryClientProvider>
    </StrictMode>,
);
