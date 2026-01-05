import { SidebarInset } from '@/components/ui/sidebar';
import * as React from 'react';

interface AppContentProps extends React.ComponentProps<'main'> {
    variant?: 'header' | 'sidebar';
    className?: string;
}

export function AppContent({ variant = 'header', children, className, ...props }: AppContentProps) {
    if (variant === 'sidebar') {
        return (
            <SidebarInset className={className} {...props}>
                {children}
            </SidebarInset>
        );
    }

    return (
        <main className={'mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 overflow-hidden rounded-xl ' + (className || '')} {...props}>
            {children}
        </main>
    );
}
