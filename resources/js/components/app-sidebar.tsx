import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { type Auth, type User } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Shield, Users } from 'lucide-react';
import AppLogo from './app-logo';
import ChatList from './chat-list';

interface AppSidebarProps {
    currentChatId?: number;
}

export function AppSidebar({ currentChatId }: AppSidebarProps) {
    const { auth } = usePage<{ auth: Auth & { user?: User } }>().props;
    const hasAdminAccess = auth.permissions?.includes('admin.access');

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>

                <div className="px-3 py-2">
                    <ChatList currentChatId={currentChatId} isAuthenticated={!!auth.user} />
                </div>
                
                {hasAdminAccess && (
                    <SidebarGroup>
                        <SidebarGroupLabel>Administration</SidebarGroupLabel>
                        <SidebarGroupContent>
                            <SidebarMenu>
                                <SidebarMenuItem>
                                    <SidebarMenuButton asChild>
                                        <Link href="/admin/users">
                                            <Users className="size-4" />
                                            <span>Users</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                                <SidebarMenuItem>
                                    <SidebarMenuButton asChild>
                                        <Link href="/admin/roles">
                                            <Shield className="size-4" />
                                            <span>Roles</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </SidebarGroup>
                )}
            </SidebarContent>

            <SidebarFooter>{auth.user && <NavUser />}</SidebarFooter>
        </Sidebar>
    );
}
