import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import type { User } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ColumnDef, flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table';
import { Edit, Plus, Trash2 } from 'lucide-react';
import { useCallback, useMemo } from 'react';

interface PaginatedUsers {
    data: User[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    next_page_url: string | null;
    prev_page_url: string | null;
}

interface Props {
    users: PaginatedUsers;
}

export default function UsersIndex({ users }: Props) {
    const handleDelete = useCallback((userId: number) => {
        if (confirm('Are you sure you want to delete this user?')) {
            router.delete(`/settings/users/${userId}`);
        }
    }, []);

    const columns = useMemo<ColumnDef<User>[]>(
        () => [
            {
                accessorKey: 'name',
                header: 'Name',
                cell: ({ row }) => <span className="font-medium">{row.getValue('name')}</span>,
            },
            {
                accessorKey: 'email',
                header: 'Email',
            },
            {
                id: 'role',
                header: 'Role',
                cell: ({ row }) => {
                    const user = row.original;
                    return user.roles && user.roles.length > 0 ? (
                        <span className="bg-primary/10 text-primary inline-flex items-center rounded-md px-2 py-1 text-xs font-medium">
                            {user.roles[0].name}
                        </span>
                    ) : (
                        <span className="text-muted-foreground">No role</span>
                    );
                },
            },
            {
                accessorKey: 'created_at',
                header: 'Created',
                cell: ({ row }) => new Date(row.getValue('created_at')).toLocaleDateString(),
            },
            {
                id: 'actions',
                header: () => <div className="text-right">Actions</div>,
                cell: ({ row }) => {
                    const user = row.original;
                    return (
                        <div className="flex justify-end gap-2">
                            <Link href={`/settings/users/${user.id}/edit`}>
                                <Button variant="ghost" size="icon" className="size-8">
                                    <Edit className="size-4" />
                                </Button>
                            </Link>
                            <Button variant="ghost" size="icon" className="size-8" onClick={() => handleDelete(user.id)}>
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                    );
                },
            },
        ],
        [handleDelete],
    );

    const table = useReactTable({
        data: users.data,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    return (
        <>
            <Head title="Manage Users" />
            <AppSidebarLayout
                breadcrumbs={[
                    { title: 'Settings', href: '/settings/users' },
                    { title: 'Users', href: '/settings/users' },
                ]}
            >
                <div className="container mx-auto px-4 py-8">
                    <div className="border-border/70 rounded-xl border p-1">
                        <Card className="bg-muted/20 rounded-lg">
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle>Users Management</CardTitle>
                                        <CardDescription>Manage user accounts and their roles</CardDescription>
                                    </div>
                                    <Link href="/settings/users/create">
                                        <Button>
                                            <Plus className="mr-2 size-4" />
                                            Add User
                                        </Button>
                                    </Link>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-hidden rounded-md border">
                                    <Table>
                                        <TableHeader>
                                            {table.getHeaderGroups().map((headerGroup) => (
                                                <TableRow key={headerGroup.id}>
                                                    {headerGroup.headers.map((header) => (
                                                        <TableHead key={header.id} className="h-9 px-3">
                                                            {header.isPlaceholder
                                                                ? null
                                                                : flexRender(header.column.columnDef.header, header.getContext())}
                                                        </TableHead>
                                                    ))}
                                                </TableRow>
                                            ))}
                                        </TableHeader>
                                        <TableBody>
                                            {table.getRowModel().rows?.length ? (
                                                table.getRowModel().rows.map((row) => (
                                                    <TableRow key={row.id} data-state={row.getIsSelected() && 'selected'}>
                                                        {row.getVisibleCells().map((cell) => (
                                                            <TableCell key={cell.id} className="px-3 py-2">
                                                                {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                            </TableCell>
                                                        ))}
                                                    </TableRow>
                                                ))
                                            ) : (
                                                <TableRow>
                                                    <TableCell colSpan={columns.length} className="h-24 text-center">
                                                        No users found.
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>

                                {users.last_page > 1 && (
                                    <div className="flex items-center justify-between py-4">
                                        <p className="text-muted-foreground text-sm">
                                            Showing {users.from} to {users.to} of {users.total} users
                                        </p>
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => router.get(users.prev_page_url || '')}
                                                disabled={!users.prev_page_url}
                                            >
                                                Previous
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => router.get(users.next_page_url || '')}
                                                disabled={!users.next_page_url}
                                            >
                                                Next
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </AppSidebarLayout>
        </>
    );
}
