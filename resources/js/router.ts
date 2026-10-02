import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
    { path: '/', name: 'dashboard', component: () => import('@/pages/DashboardPage.vue') },
    { path: '/ingredients', name: 'ingredients', component: () => import('@/pages/IngredientsPage.vue') },
    { path: '/suppliers', name: 'suppliers', component: () => import('@/pages/SuppliersPage.vue') },
    { path: '/menu-items', name: 'menu-items', component: () => import('@/pages/MenuItemsPage.vue') },
    {
        path: '/purchase-orders',
        name: 'purchase-orders',
        component: () => import('@/pages/PurchaseOrdersPage.vue'),
    },
    {
        path: '/purchase-orders/:id',
        name: 'purchase-order-detail',
        component: () => import('@/pages/PurchaseOrderDetailPage.vue'),
        props: (route) => ({ id: Number(route.params.id) }),
    },
    { path: '/pos', name: 'pos', component: () => import('@/pages/PosSimulatorPage.vue') },
];

export const router = createRouter({
    history: createWebHistory(),
    routes,
});
