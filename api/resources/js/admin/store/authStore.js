import { create } from 'zustand';
import { persist } from 'zustand/middleware';

export const useAuthStore = create(
    persist(
        (set) => ({
            user: null,
            token: null,
            isAuthenticated: false,
            isLoading: false,

            setAuth: (user, token = null) => {
                if (token) {
                    localStorage.setItem('token', token);
                } else {
                    localStorage.removeItem('token');
                }

                set({ user, token, isAuthenticated: !!user });
            },
            setUser: (user) => set({ user, isAuthenticated: !!user }),
            setLoading: (isLoading) => set({ isLoading }),
            logout: () => {
                localStorage.removeItem('token');
                set({ user: null, token: null, isAuthenticated: false });
            },
        }),
        {
            name: 'eis-admin-auth',
            partialize: (state) => ({
                user: state.user,
                token: state.token,
                isAuthenticated: state.isAuthenticated,
            }),
        },
    ),
);
