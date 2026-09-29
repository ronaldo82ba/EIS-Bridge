import { PHASE1_MOCK } from '../config/phase1';
import { branchService } from '../services/branchService';
import { certificateService } from '../services/certificateService';
import { deviceService } from '../services/deviceService';
import { merchantService } from '../services/merchantService';
import { pttService } from '../services/pttService';
import { vendorService } from '../services/vendorService';
import { extractPaginated } from '../utils/pagination';

let mockMerchantSeq = 100;
let mockBranchSeq = 200;
let mockDeviceSeq = 300;

const mockVendors = [
    { id: 1, name: 'Demo POS Vendor' },
    { id: 2, name: 'Retail Solutions Inc.' },
];

const mockStore = {
    branches: {},
    devices: {},
    certificates: {},
    ptt: {},
};

// Phase-1 mock bridge for onboarding screens while admin endpoints are bootstrapped.

function unwrap(response) {
    return response?.data?.data ?? response?.data ?? response;
}

async function mockResolve(payload, delay = 250) {
    await new Promise((resolve) => setTimeout(resolve, delay));
    return payload;
}

export function getMockReadiness(merchantId) {
    const branches = mockStore.branches[merchantId] ?? [];
    const devices = mockStore.devices[merchantId] ?? [];
    const hasCert = Boolean(mockStore.certificates[merchantId]);
    const hasPtt = Boolean(mockStore.ptt[merchantId]);

    const checks = {
        merchant_info: true,
        branches: branches.length > 0,
        devices: devices.length > 0,
        certificate: hasCert,
        ptt: hasPtt,
        signing_test: hasCert,
        mapping_test: branches.length > 0 && devices.length > 0,
    };

    const ready = Object.values(checks).every(Boolean);

    return {
        ready,
        merchant: `Mock Merchant ${merchantId}`,
        merchant_id: Number(merchantId),
        checks,
    };
}

export const onboardingApi = {
    async listVendors(params = { per_page: 100 }) {
        if (PHASE1_MOCK) {
            return mockResolve({ data: mockVendors, pagination: { total: mockVendors.length } });
        }

        return extractPaginated(await vendorService.list(params));
    },

    async createMerchant(data) {
        if (PHASE1_MOCK) {
            const id = ++mockMerchantSeq;
            mockStore.branches[id] = [];
            mockStore.devices[id] = [];
            return mockResolve({ id, ...data });
        }

        const response = await merchantService.create(data);
        return unwrap(response);
    },

    async getMerchant(id) {
        if (PHASE1_MOCK) {
            return mockResolve({
                id: Number(id),
                name: `Mock Merchant ${id}`,
                status: 'active',
            });
        }

        const response = await merchantService.get(id);
        return unwrap(response);
    },

    async listBranches(merchantId) {
        if (PHASE1_MOCK) {
            return mockResolve(mockStore.branches[merchantId] ?? []);
        }

        const response = await branchService.list({ merchant_id: merchantId, per_page: 100 });
        const paginated = extractPaginated(response);
        return paginated.data ?? [];
    },

    async createBranch(data) {
        if (PHASE1_MOCK) {
            const branch = {
                id: ++mockBranchSeq,
                ...data,
                status: data.status ?? 'active',
            };
            const merchantId = data.merchant_id;
            if (!mockStore.branches[merchantId]) {
                mockStore.branches[merchantId] = [];
            }
            mockStore.branches[merchantId].push(branch);
            return mockResolve(branch);
        }

        const response = await branchService.create(data);
        return unwrap(response);
    },

    async listDevices(merchantId) {
        if (PHASE1_MOCK) {
            return mockResolve(mockStore.devices[merchantId] ?? []);
        }

        const branches = await this.listBranches(merchantId);
        const results = await Promise.all(
            branches.map((branch) =>
                extractPaginated(deviceService.list({ branch_id: branch.id, per_page: 50 })),
            ),
        );

        return results.flatMap((result) => result.data ?? []);
    },

    async createDevice(branchId, data) {
        if (PHASE1_MOCK) {
            const device = {
                id: ++mockDeviceSeq,
                branch_id: branchId,
                ...data,
                status: data.status ?? 'active',
            };
            const merchantId = data.merchant_id;
            if (merchantId) {
                if (!mockStore.devices[merchantId]) {
                    mockStore.devices[merchantId] = [];
                }
                mockStore.devices[merchantId].push(device);
            }
            return mockResolve(device);
        }

        const response = await deviceService.create({
            ...data,
            branch_id: branchId,
        });
        return unwrap(response);
    },

    async uploadCertificate(merchantId, formData) {
        if (PHASE1_MOCK) {
            mockStore.certificates[merchantId] = { uploaded: true, id: 1 };
            return mockResolve({ id: 1, merchant_id: Number(merchantId) });
        }

        if (!formData.has('merchant_id')) {
            formData.append('merchant_id', merchantId);
        }

        const response = await certificateService.create(formData);
        return unwrap(response);
    },

    async listCertificates(merchantId) {
        if (PHASE1_MOCK) {
            const stored = mockStore.certificates[merchantId];
            return mockResolve(stored ? [{ id: stored.id ?? 1, merchant_id: Number(merchantId) }] : []);
        }

        const response = await certificateService.list({ merchant_id: merchantId, per_page: 1 });
        const paginated = extractPaginated(response);
        return paginated.data ?? [];
    },

    async upsertPtt(merchantId, data) {
        if (PHASE1_MOCK) {
            mockStore.ptt[merchantId] = data;
            return mockResolve({ merchant_id: Number(merchantId), ...data });
        }

        const response = await pttService.create({
            merchant_id: Number(merchantId),
            ...data,
        });
        return unwrap(response);
    },

    async getReadiness(merchantId) {
        if (PHASE1_MOCK) {
            return mockResolve(getMockReadiness(merchantId));
        }

        const response = await merchantService.getReadiness(merchantId);
        return unwrap(response);
    },
};
