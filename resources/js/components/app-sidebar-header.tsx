import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType, type User } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';

interface AppSidebarHeaderProps {
    breadcrumbs?: BreadcrumbItemType[];
}

export function AppSidebarHeader({ breadcrumbs = [] }: AppSidebarHeaderProps) {
    const { auth } = usePage<{ auth: { user?: User } }>().props;

    const handleNewChat = () => {
        if (!auth.user) {
            router.visit('/login');
        } else {
            // Get selected model from localStorage
            const selectedModel = localStorage.getItem('selectedModel');

            // Use router.post directly with form data
            router.post('/chat', {
                model: selectedModel,
            });
        }
    };

    return (
        <header className="border-sidebar-border/50 bg-background sticky top-0 z-10 flex h-16 shrink-0 items-center justify-between gap-2 border-b px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="flex items-center gap-2">
                {!auth.user ? (
                    <>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/login">Sign in</Link>
                        </Button>
                        <Button size="sm" asChild>
                            <Link href="/register">Sign up</Link>
                        </Button>
                    </>
                ) : (
                    <Button variant="ghost" size="icon" onClick={handleNewChat}>
                        <Plus className="h-4 w-4" />
                    </Button>
                )}
            </div>
        </header>
    );
}
