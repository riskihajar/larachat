import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import type { Permission } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
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

    const isGroupIndeterminate = (groupPermissions: Permission[]) => {
        const checkedCount = groupPermissions.filter((perm) => data.permissions.includes(perm.name)).length;
        return checkedCount > 0 && checkedCount < groupPermissions.length;
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
                <div className="container mx-auto max-w-2xl px-4 py-8">
                    <div className="border-border/70 rounded-xl border p-1">
                        <Card className="bg-muted/20 rounded-lg">
                            <CardHeader>
                                <div className="flex items-center gap-4">
                                    <Link href="/settings/roles">
                                        <Button variant="ghost" size="icon" className="size-8">
                                            <ArrowLeft className="size-4" />
                                        </Button>
                                    </Link>
                                    <div>
                                        <CardTitle>Create New Role</CardTitle>
                                        <CardDescription>Add a new role with specific permissions</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleSubmit} className="space-y-6">
                                    {/* Role Name Section */}
                                    <fieldset className="rounded-lg border p-4">
                                        <legend className="text-muted-foreground px-2 text-sm font-medium">Role Details</legend>
                                        <div className="grid grid-cols-[120px_1fr] items-center gap-4">
                                            <Label htmlFor="name" className="text-right">
                                                Role Name
                                            </Label>
                                            <div className="space-y-1">
                                                <Input
                                                    id="name"
                                                    value={data.name}
                                                    onChange={(e) => setData('name', e.target.value)}
                                                    placeholder="e.g. manager, editor"
                                                    required
                                                />
                                                {errors.name && <p className="text-destructive text-sm">{errors.name}</p>}
                                            </div>
                                        </div>
                                    </fieldset>

                                    {/* Permissions Section */}
                                    <fieldset className="rounded-lg border p-4">
                                        <legend className="text-muted-foreground px-2 text-sm font-medium">Permissions</legend>
                                        {errors.permissions && <p className="text-destructive mb-3 text-sm">{errors.permissions}</p>}

                                        <div className="space-y-4">
                                            {Object.entries(permissions).map(([group, groupPermissions]) => (
                                                <div key={group} className="rounded-md border">
                                                    <div className="bg-muted/50 flex items-center gap-3 border-b px-4 py-2">
                                                        <Checkbox
                                                            id={`group-${group}`}
                                                            checked={isGroupChecked(groupPermissions)}
                                                            ref={(el) => {
                                                                if (el) {
                                                                    (el as unknown as HTMLInputElement).indeterminate =
                                                                        isGroupIndeterminate(groupPermissions);
                                                                }
                                                            }}
                                                            onCheckedChange={(checked) => handleGroupToggle(groupPermissions, checked as boolean)}
                                                        />
                                                        <Label htmlFor={`group-${group}`} className="cursor-pointer font-semibold capitalize">
                                                            {group}
                                                        </Label>
                                                        <span className="text-muted-foreground text-xs">
                                                            ({groupPermissions.filter((p) => data.permissions.includes(p.name)).length}/
                                                            {groupPermissions.length})
                                                        </span>
                                                    </div>
                                                    <div className="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3">
                                                        {groupPermissions.map((permission) => (
                                                            <div key={permission.id} className="flex items-center gap-2">
                                                                <Checkbox
                                                                    id={permission.name}
                                                                    checked={data.permissions.includes(permission.name)}
                                                                    onCheckedChange={(checked) =>
                                                                        handlePermissionToggle(permission.name, checked as boolean)
                                                                    }
                                                                />
                                                                <Label htmlFor={permission.name} className="cursor-pointer text-sm">
                                                                    {permission.name.split('.').pop()}
                                                                </Label>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </fieldset>

                                    {/* Actions */}
                                    <div className="flex gap-3 pt-2">
                                        <Button type="submit" disabled={processing}>
                                            {processing ? 'Creating...' : 'Create Role'}
                                        </Button>
                                        <Link href="/settings/roles">
                                            <Button type="button" variant="outline">
                                                Cancel
                                            </Button>
                                        </Link>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </AppSidebarLayout>
        </>
    );
}
