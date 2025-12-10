import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import type { Permission } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface Props {
    permissions: Record<string, Permission[]>;
}

export default function RolesCreate({ permissions }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        permissions: [] as string[],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/settings/roles');
    };

    const handlePermissionToggle = (permissionName: string, checked: boolean) => {
        if (checked) {
            setData('permissions', [...data.permissions, permissionName]);
        } else {
            setData(
                'permissions',
                data.permissions.filter((p) => p !== permissionName),
            );
        }
    };

    const handleGroupToggle = (groupPermissions: Permission[], checked: boolean) => {
        if (checked) {
            const newPermissions = [...data.permissions];
            groupPermissions.forEach((perm) => {
                if (!newPermissions.includes(perm.name)) {
                    newPermissions.push(perm.name);
                }
            });
            setData('permissions', newPermissions);
        } else {
            const permissionNames = groupPermissions.map((p) => p.name);
            setData(
                'permissions',
                data.permissions.filter((p) => !permissionNames.includes(p)),
            );
        }
    };

    const isGroupChecked = (groupPermissions: Permission[]) => {
        return groupPermissions.every((perm) => data.permissions.includes(perm.name));
    };

    return (
        <>
            <Head title="Create Role" />
            <AppSidebarLayout
                breadcrumbs={[
                    { title: 'Settings', href: '/settings/users' },
                    { title: 'Roles', href: '/settings/roles' },
                    { title: 'Create', href: '/settings/roles/create' },
                ]}
            >
                <div className="container mx-auto px-4 py-8">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-4">
                                <Button variant="ghost" size="sm" onClick={() => window.history.back()}>
                                    <ArrowLeft className="size-4" />
                                </Button>
                                <div>
                                    <CardTitle>Create New Role</CardTitle>
                                    <CardDescription>Add a new role with specific permissions</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div className="space-y-2">
                                    <Label htmlFor="name">Role Name</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="manager"
                                        required
                                    />
                                    {errors.name && <p className="text-destructive text-sm">{errors.name}</p>}
                                </div>

                                <div className="space-y-4">
                                    <Label>Permissions</Label>
                                    {errors.permissions && <p className="text-destructive text-sm">{errors.permissions}</p>}

                                    <div className="space-y-4 rounded-lg border p-4">
                                        {Object.entries(permissions).map(([group, groupPermissions]) => (
                                            <div key={group} className="space-y-3">
                                                <div className="flex items-center space-x-2 border-b pb-2">
                                                    <Checkbox
                                                        id={`group-${group}`}
                                                        checked={isGroupChecked(groupPermissions)}
                                                        onCheckedChange={(checked) => handleGroupToggle(groupPermissions, checked as boolean)}
                                                    />
                                                    <Label htmlFor={`group-${group}`} className="cursor-pointer font-semibold capitalize">
                                                        {group}
                                                    </Label>
                                                </div>
                                                <div className="ml-6 grid grid-cols-2 gap-3 md:grid-cols-3">
                                                    {groupPermissions.map((permission) => (
                                                        <div key={permission.id} className="flex items-center space-x-2">
                                                            <Checkbox
                                                                id={permission.name}
                                                                checked={data.permissions.includes(permission.name)}
                                                                onCheckedChange={(checked) =>
                                                                    handlePermissionToggle(permission.name, checked as boolean)
                                                                }
                                                            />
                                                            <Label htmlFor={permission.name} className="cursor-pointer text-sm">
                                                                {permission.name.split('.').slice(1).join('.')}
                                                            </Label>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                <div className="flex gap-4">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Creating...' : 'Create Role'}
                                    </Button>
                                    <Button type="button" variant="outline" onClick={() => window.history.back()}>
                                        Cancel
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </AppSidebarLayout>
        </>
    );
}
