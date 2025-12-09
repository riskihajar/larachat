import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import type { PaginatedData, User } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Edit, Plus, Trash2 } from 'lucide-react';

interface Props {
    users: PaginatedData<User>;
}

export default function UsersIndex({ users }: Props) {
    const handleDelete = (userId: number) => {
        if (confirm('Are you sure you want to delete this user?')) {
            router.delete(`/admin/users/${userId}`);
        }
    };

    return (
        <>
            <Head title="Manage Users" />
            <AppSidebarLayout
                breadcrumbs={[
                    { title: 'Admin', href: '/admin/users' },
                    { title: 'Users', href: '/admin/users' },
                ]}
            >
                <div className="container mx-auto py-8 px-4">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Users Management</CardTitle>
                                    <CardDescription>Manage user accounts and their roles</CardDescription>
                                </div>
                                <Link href="/admin/users/create">
                                    <Button>
                                        <Plus className="mr-2 size-4" />
                                        Add User
                                    </Button>
                                </Link>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead>Created</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {users.data.map((user) => (
                                        <TableRow key={user.id}>
                                            <TableCell className="font-medium">{user.name}</TableCell>
                                            <TableCell>{user.email}</TableCell>
                                            <TableCell>
                                                {user.roles && user.roles.length > 0 ? (
                                                    <span className="inline-flex items-center rounded-md bg-primary/10 px-2 py-1 text-xs font-medium text-primary">
                                                        {user.roles[0].name}
                                                    </span>
                                                ) : (
                                                    <span className="text-muted-foreground">No role</span>
                                                )}
                                            </TableCell>
                                            <TableCell>{new Date(user.created_at).toLocaleDateString()}</TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Link href={`/admin/users/${user.id}/edit`}>
                                                        <Button variant="outline" size="sm">
                                                            <Edit className="size-4" />
                                                        </Button>
                                                    </Link>
                                                    <Button variant="outline" size="sm" onClick={() => handleDelete(user.id)}>
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>

                            {users.data.length === 0 && (
                                <div className="py-8 text-center text-muted-foreground">No users found.</div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </AppSidebarLayout>
        </>
    );
}
