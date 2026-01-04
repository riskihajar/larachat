import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import type { Role } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ColumnDef, flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table';
import { Edit, Plus, Trash2 } from 'lucide-react';
import { useCallback, useMemo } from 'react';

interface Props {
    roles: Role[];
}

export default function RolesIndex({ roles }: Props) {
    const handleDelete = useCallback((roleId: number) => {
        if (confirm('Are you sure you want to delete this role?')) {
            router.delete(`/settings/roles/${roleId}`);
        }
    }, []);

    const columns = useMemo<ColumnDef<Role>[]>(
        () => [
            {
                accessorKey: 'name',
                header: 'Name',
                cell: ({ row }) => <span className="font-medium">{row.getValue('name')}</span>,
            },
            {
                id: 'permissions',
                header: 'Permissions',
                cell: ({ row }) => {
                    const role = row.original;
                    return <span className="text-muted-foreground">{role.permissions ? role.permissions.length : 0} permissions</span>;
                },
            },
            {
                id: 'users',
                header: 'Users',
                cell: ({ row }) => {
                    const role = row.original;
                    return <span className="text-muted-foreground">{role.users_count || 0} users</span>;
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
                    const role = row.original;
                    return (
                        <div className="flex justify-end gap-2">
                            <Link href={`/settings/roles/${role.id}/edit`}>
                                <Button variant="ghost" size="icon" className="size-8">
                                    <Edit className="size-4" />
                                </Button>
                            </Link>
                            <Button variant="ghost" size="icon" className="size-8" onClick={() => handleDelete(role.id)}>
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
        data: roles,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    return (
        <>
            <Head title="Manage Roles" />
            <AppSidebarLayout
                breadcrumbs={[
                    { title: 'Settings', href: '/settings/users' },
                    { title: 'Roles', href: '/settings/roles' },
                ]}
            >
                <div className="container mx-auto px-4 py-8">
                    <div className="border-border/70 rounded-xl border p-1">
                        <Card className="bg-muted/20 rounded-lg">
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle>Roles Management</CardTitle>
                                        <CardDescription>Manage roles and their permissions</CardDescription>
                                    </div>
                                    <Link href="/settings/roles/create">
                                        <Button>
                                            <Plus className="mr-2 size-4" />
                                            Add Role
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
                                                        No roles found.
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </AppSidebarLayout>
        </>
    );
}
