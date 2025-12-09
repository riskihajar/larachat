import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { Toaster } from '@/components/ui/sonner';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { type PropsWithChildren, useEffect } from 'react';
import { toast } from 'sonner';

interface AppSidebarLayoutProps {
    breadcrumbs?: BreadcrumbItem[];
    currentChatId?: number;
    className?: string;
}

export default function AppSidebarLayout({ children, breadcrumbs = [], currentChatId, className }: PropsWithChildren<AppSidebarLayoutProps>) {
    const { flash } = usePage<SharedData>().props;

    // Show flash messages as toasts
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
        if (flash?.info) {
            toast.info(flash.info);
        }
    }, [flash]);

    return (
        <AppShell variant="sidebar">
            <AppSidebar currentChatId={currentChatId} />
            <AppContent variant="sidebar" className={className}>
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
            <Toaster />
        </AppShell>
    );
}
