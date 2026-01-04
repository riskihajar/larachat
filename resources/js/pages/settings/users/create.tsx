import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import type { Role } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface Props {
    roles: Role[];
}

export default function UsersCreate({ roles }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/settings/users');
    };

    return (
        <>
            <Head title="Create User" />
            <AppSidebarLayout
                breadcrumbs={[
                    { title: 'Settings', href: '/settings/users' },
                    { title: 'Users', href: '/settings/users' },
                    { title: 'Create', href: '/settings/users/create' },
                ]}
            >
                <div className="container mx-auto max-w-2xl px-4 py-8">
                    <div className="border-border/70 rounded-xl border p-1">
                        <Card className="bg-muted/20 rounded-lg">
                            <CardHeader>
                                <div className="flex items-center gap-4">
                                    <Link href="/settings/users">
                                        <Button variant="ghost" size="icon" className="size-8">
                                            <ArrowLeft className="size-4" />
                                        </Button>
                                    </Link>
                                    <div>
                                        <CardTitle>Create New User</CardTitle>
                                        <CardDescription>Add a new user to the system</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleSubmit} className="space-y-6">
                                    {/* Basic Information Section */}
                                    <fieldset className="rounded-lg border p-4">
                                        <legend className="text-muted-foreground px-2 text-sm font-medium">Basic Information</legend>
                                        <div className="space-y-4">
                                            <div className="grid grid-cols-[120px_1fr] items-center gap-4">
                                                <Label htmlFor="name" className="text-right">
                                                    Name
                                                </Label>
                                                <div className="space-y-1">
                                                    <Input
                                                        id="name"
                                                        value={data.name}
                                                        onChange={(e) => setData('name', e.target.value)}
                                                        placeholder="John Doe"
                                                        required
                                                    />
                                                    {errors.name && <p className="text-destructive text-sm">{errors.name}</p>}
                                                </div>
                                            </div>
                                            <div className="grid grid-cols-[120px_1fr] items-center gap-4">
                                                <Label htmlFor="email" className="text-right">
                                                    Email
                                                </Label>
                                                <div className="space-y-1">
                                                    <Input
                                                        id="email"
                                                        type="email"
                                                        value={data.email}
                                                        onChange={(e) => setData('email', e.target.value)}
                                                        placeholder="john@example.com"
                                                        required
                                                    />
                                                    {errors.email && <p className="text-destructive text-sm">{errors.email}</p>}
                                                </div>
                                            </div>
                                        </div>
                                    </fieldset>

                                    {/* Security Section */}
                                    <fieldset className="rounded-lg border p-4">
                                        <legend className="text-muted-foreground px-2 text-sm font-medium">Security</legend>
                                        <div className="space-y-4">
                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <div className="space-y-2">
                                                    <Label htmlFor="password">Password</Label>
                                                    <Input
                                                        id="password"
                                                        type="password"
                                                        value={data.password}
                                                        onChange={(e) => setData('password', e.target.value)}
                                                        required
                                                    />
                                                    {errors.password && <p className="text-destructive text-sm">{errors.password}</p>}
                                                </div>
                                                <div className="space-y-2">
                                                    <Label htmlFor="password_confirmation">Confirm Password</Label>
                                                    <Input
                                                        id="password_confirmation"
                                                        type="password"
                                                        value={data.password_confirmation}
                                                        onChange={(e) => setData('password_confirmation', e.target.value)}
                                                        required
                                                    />
                                                    {errors.password_confirmation && (
                                                        <p className="text-destructive text-sm">{errors.password_confirmation}</p>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </fieldset>

                                    {/* Role Assignment Section */}
                                    <fieldset className="rounded-lg border p-4">
                                        <legend className="text-muted-foreground px-2 text-sm font-medium">Role Assignment</legend>
                                        <div className="grid grid-cols-[120px_1fr] items-center gap-4">
                                            <Label htmlFor="role" className="text-right">
                                                Role
                                            </Label>
                                            <div className="space-y-1">
                                                <Select value={data.role} onValueChange={(value) => setData('role', value)} required>
                                                    <SelectTrigger id="role">
                                                        <SelectValue placeholder="Select a role" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {roles.map((role) => (
                                                            <SelectItem key={role.id} value={role.name}>
                                                                {role.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                {errors.role && <p className="text-destructive text-sm">{errors.role}</p>}
                                            </div>
                                        </div>
                                    </fieldset>

                                    {/* Actions */}
                                    <div className="flex gap-3 pt-2">
                                        <Button type="submit" disabled={processing}>
                                            {processing ? 'Creating...' : 'Create User'}
                                        </Button>
                                        <Link href="/settings/users">
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
