import { create } from 'zustand';

export const useUiStore = create((set) => ({
    sidebarCollapsed: false,

    toggleSidebar: () => set((state) => ({ sidebarCollapsed: !state.sidebarCollapsed })),
    setSidebarCollapsed: (sidebarCollapsed) => set({ sidebarCollapsed }),
}));
