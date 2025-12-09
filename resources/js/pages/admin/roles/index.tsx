import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import type { Role } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Edit, Plus, Trash2 } from 'lucide-react';

interface Props {
    roles: Role[];
}

export default function RolesIndex({ roles }: Props) {
    const handleDelete = (roleId: number) => {
        if (confirm('Are you sure you want to delete this role?')) {
            router.delete(`/admin/roles/${roleId}`);
        }
    };

    return (
        <>
            <Head title="Manage Roles" />
            <AppSidebarLayout
                breadcrumbs={[
                    { title: 'Admin', href: '/admin/users' },
                    { title: 'Roles', href: '/admin/roles' },
                ]}
            >
                <div className="container mx-auto py-8 px-4">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Roles Management</CardTitle>
                                    <CardDescription>Manage roles and their permissions</CardDescription>
                                </div>
                                <Link href="/admin/roles/create">
                                    <Button>
                                        <Plus className="mr-2 size-4" />
                                        Add Role
                                    </Button>
                                </Link>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Permissions</TableHead>
                                        <TableHead>Users</TableHead>
                                        <TableHead>Created</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {roles.map((role) => (
                                        <TableRow key={role.id}>
                                            <TableCell className="font-medium">{role.name}</TableCell>
                                            <TableCell>
                                                {role.permissions ? (
                                                    <span className="text-muted-foreground">
                                                        {role.permissions.length} permissions
                                                    </span>
                                                ) : (
                                                    <span className="text-muted-foreground">0 permissions</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <span className="text-muted-foreground">{role.users_count || 0} users</span>
                                            </TableCell>
                                            <TableCell>{new Date(role.created_at).toLocaleDateString()}</TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Link href={`/admin/roles/${role.id}/edit`}>
                                                        <Button variant="outline" size="sm">
                                                            <Edit className="size-4" />
                                                        </Button>
                                                    </Link>
                                                    <Button variant="outline" size="sm" onClick={() => handleDelete(role.id)}>
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>

                            {roles.length === 0 && (
                                <div className="py-8 text-center text-muted-foreground">No roles found.</div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </AppSidebarLayout>
        </>
    );
}
