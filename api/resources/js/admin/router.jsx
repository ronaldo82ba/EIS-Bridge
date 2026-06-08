import { lazy, Suspense } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import ProtectedRoute from './components/ProtectedRoute';
import AdminLayout from './layouts/AdminLayout';
import DashboardPage from './pages/DashboardPage';
import LoginPage from './pages/LoginPage';

const VendorList = lazy(() => import('./pages/Vendors/VendorList'));
const VendorCreate = lazy(() => import('./pages/Vendors/VendorCreate'));
const VendorDetail = lazy(() => import('./pages/Vendors/VendorDetail'));
const VendorAnalytics = lazy(() => import('./pages/Vendors/VendorAnalytics'));
const VendorHealth = lazy(() => import('./pages/Vendors/VendorHealth'));
const VendorWebhookConfig = lazy(() => import('./pages/Vendors/VendorWebhookConfig'));
const MerchantList = lazy(() => import('./pages/Merchants/MerchantList'));
const MerchantCreate = lazy(() => import('./pages/Merchants/MerchantCreate'));
const MerchantDetail = lazy(() => import('./pages/Merchants/MerchantDetail'));
const MerchantDetailDashboard = lazy(() => import('./pages/Merchants/MerchantDetailDashboard'));
const MerchantBranches = lazy(() => import('./pages/Merchants/MerchantBranches'));
const MerchantDevices = lazy(() => import('./pages/Merchants/MerchantDevices'));
const MerchantCertificate = lazy(() => import('./pages/Merchants/MerchantCertificate'));
const MerchantPTT = lazy(() => import('./pages/Merchants/MerchantPTT'));
const MerchantReadiness = lazy(() => import('./pages/Merchants/MerchantReadiness'));
const MerchantActivityTimeline = lazy(() => import('./pages/Merchants/MerchantActivityTimeline'));
const MerchantAnalytics = lazy(() => import('./pages/Merchants/MerchantAnalytics'));
const MerchantHealth = lazy(() => import('./pages/Merchants/MerchantHealth'));
const BranchList = lazy(() => import('./pages/Branches/BranchList'));
const BranchDetail = lazy(() => import('./pages/Branches/BranchDetail'));
const InvoiceList = lazy(() => import('./pages/Invoices/InvoiceList'));
const InvoiceSearch = lazy(() => import('./pages/Invoices/InvoiceSearch'));
const InvoiceAnalytics = lazy(() => import('./pages/Invoices/InvoiceAnalytics'));
const InvoiceDetail = lazy(() => import('./pages/Invoices/InvoiceDetail'));
const QueuesPage = lazy(() => import('./pages/Queues/QueuesPage'));
const MonitoringPage = lazy(() => import('./pages/Monitoring/MonitoringPage'));
const QueueMonitor = lazy(() => import('./pages/Monitoring/QueueMonitor'));
const AlertsCenter = lazy(() => import('./pages/Alerts/AlertsCenter'));
const CertificateList = lazy(() => import('./pages/Certificates/CertificateList'));
const CertificateUpload = lazy(() => import('./pages/Certificates/CertificateUpload'));
const CertificateViewer = lazy(() => import('./pages/Certificates/CertificateViewer'));
const WebhooksPage = lazy(() => import('./pages/Webhooks/WebhooksPage'));
const BillingPage = lazy(() => import('./pages/Billing/BillingPage'));
const LogsPage = lazy(() => import('./pages/Logs/LogsPage'));
const SettingsPage = lazy(() => import('./pages/Settings/SettingsPage'));

function LazyPage({ children }) {
    return (
        <Suspense
            fallback={
                <div className="flex items-center justify-center p-12">
                    <p className="text-sm text-slate-500">Loading…</p>
                </div>
            }
        >
            {children}
        </Suspense>
    );
}

export default function AppRouter() {
    return (
        <Routes>
            <Route path="/login" element={<LoginPage />} />

            <Route element={<ProtectedRoute />}>
                <Route path="/" element={<AdminLayout />}>
                    <Route index element={<Navigate to="dashboard" replace />} />
                    <Route path="dashboard" element={<DashboardPage />} />

                    <Route
                        path="vendors"
                        element={
                            <LazyPage>
                                <VendorList />
                            </LazyPage>
                        }
                    />
                    <Route
                        path="vendors/create"
                        element={
                            <LazyPage>
                                <VendorCreate />
                            </LazyPage>
                        }
                    />
                    <Route
                        path="vendors/analytics"
                        element={
                            <LazyPage>
                                <VendorAnalytics />
                            </LazyPage>
                        }
                    />
                    <Route
                        path="vendors/health"
                        element={
                            <LazyPage>
                                <VendorHealth />
                            </LazyPage>
                        }
                    />
                    <Route
                        path="vendors/:id"
                        element={
                            <LazyPage>
                                <VendorDetail />
                            </LazyPage>
                        }
                    />
                    <Route
                        path="vendors/:id/webhooks"
                        element={
                            <LazyPage>
                                <VendorWebhookConfig />
                            </LazyPage>
                        }
                    />

                    <Route
                        path="merchants/analytics"
                        element={
                            <LazyPage>
                                <MerchantAnalytics />
                            </LazyPage>
                        }
                    />
                    <Route
                        path="merchants/health"
                        element={
                            <LazyPage>
                                <MerchantHealth />
                            </LazyPage>
                        }
                    />
                    <Route
                        path="merchants"
                        element={
                            <LazyPage>
                                <MerchantList />
                            </LazyPage>
                        }
                    />
                    <Route
                        path="merchants/new"
                        element={
                            <LazyPage>
                                <MerchantCreate />
                            </LazyPage>
                        }
                    />
                    <Route path="merchants/create" element={<Navigate to="/merchants/new" replace />} />
                    <Route
                        path="merchants/:id/activity"
                        element={
                            <LazyPage>
                                <MerchantActivityTimeline />
                            </LazyPage>
                        }
                    />
                    <Route
                        path="merchants/:id"
                        element={
                            <LazyPage>
                                <MerchantDetail />
                            </LazyPage>
                        }
                    >
                        <Route
                            index
                            element={
                                <LazyPage>
                                    <MerchantDetailDashboard />
                                </LazyPage>
                            }
                        />
                        <Route
                            path="branches"
                            element={
                                <LazyPage>
                                    <MerchantBranches />
                                </LazyPage>
                            }
                        />
                        <Route
                            path="devices"
                            element={
                                <LazyPage>
                                    <MerchantDevices />
                                </LazyPage>
                            }
                        />
                        <Route
                            path="certificate"
                            element={
                                <LazyPage>
                                    <MerchantCertificate />
                                </LazyPage>
                            }
                        />
                        <Route
                            path="ptt"
                            element={
                                <LazyPage>
                                    <MerchantPTT />
                                </LazyPage>
                            }
                        />
                        <Route
                            path="readiness"
                            element={
                                <LazyPage>
                                    <MerchantReadiness />
                                </LazyPage>
                            }
                        />
                    </Route>

                    <Route
                        path="branches"
                        element={
                            <LazyPage>
                                <BranchList />
                            </LazyPage>
                        }
                    />
                    <Route
                        path="branches/:id"
                        element={
                            <LazyPage>
                                <BranchDetail />
                            </LazyPage>
                        }
                    />

                    <Route
                        path="invoices/search"
                        element={
                            <LazyPage>
                                <InvoiceSearch />
                            </LazyPage>
                        }
                    />
                    <Route
                        path="invoices/analytics"
                        element={
                            <LazyPage>
                                <InvoiceAnalytics />
                            </LazyPage>
                        }
                    />
                    <Route
                        path="invoices"
                        element={
                            <LazyPage>
                                <InvoiceList />
                            </LazyPage>
                        }
                    />
                    <Route
                        path="invoices/:id"
                        element={
                            <LazyPage>
                                <InvoiceDetail />
                            </LazyPage>
                        }
                    />

                    <Route
                        path="queues"
                        element={
                            <LazyPage>
                                <QueuesPage />
                            </LazyPage>
                        }
                    />
                    <Route
                        path="monitoring/queues"
                        element={
                            <LazyPage>
                                <QueueMonitor />
                            </LazyPage>
                        }
                    />
                    <Route
                        path="monitoring"
                        element={
                            <LazyPage>
                                <MonitoringPage />
                            </LazyPage>
                        }
                    />
                    <Route
                        path="alerts"
                        element={
                            <LazyPage>
                                <AlertsCenter />
                            </LazyPage>
                        }
                    />
                    <Route
                        path="certificates"
                        element={
                            <LazyPage>
                                <CertificateList />
                            </LazyPage>
                        }
                    />
                    <Route
                        path="certificates/:merchantId/upload"
                        element={
                            <LazyPage>
                                <CertificateUpload />
                            </LazyPage>
                        }
                    />
                    <Route
                        path="certificates/:id"
                        element={
                            <LazyPage>
                                <CertificateViewer />
                            </LazyPage>
                        }
                    />
                    <Route
                        path="webhooks"
                        element={
                            <LazyPage>
                                <WebhooksPage />
                            </LazyPage>
                        }
                    />
                    <Route
                        path="billing"
                        element={
                            <LazyPage>
                                <BillingPage />
                            </LazyPage>
                        }
                    />
                    <Route
                        path="logs"
                        element={
                            <LazyPage>
                                <LogsPage />
                            </LazyPage>
                        }
                    />
                    <Route
                        path="settings"
                        element={
                            <LazyPage>
                                <SettingsPage />
                            </LazyPage>
                        }
                    />
                </Route>
            </Route>

            <Route path="*" element={<Navigate to="/dashboard" replace />} />
        </Routes>
    );
}
