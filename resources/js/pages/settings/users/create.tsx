import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel, FieldLegend, FieldSet } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
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
                <div className="max-w-2xl px-4 py-8">
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
                                    <FieldSet className="rounded-lg border p-4">
                                        <FieldLegend className="text-muted-foreground px-2 text-sm font-medium">Basic Information</FieldLegend>
                                        <FieldGroup className="gap-4">
                                            <Field data-invalid={!!errors.name}>
                                                <FieldLabel htmlFor="name">Name</FieldLabel>
                                                <Input
                                                    id="name"
                                                    value={data.name}
                                                    onChange={(e) => setData('name', e.target.value)}
                                                    placeholder="John Doe"
                                                    aria-invalid={!!errors.name}
                                                    required
                                                />
                                                {errors.name && <FieldError>{errors.name}</FieldError>}
                                            </Field>
                                            <Field data-invalid={!!errors.email}>
                                                <FieldLabel htmlFor="email">Email</FieldLabel>
                                                <Input
                                                    id="email"
                                                    type="email"
                                                    value={data.email}
                                                    onChange={(e) => setData('email', e.target.value)}
                                                    placeholder="john@example.com"
                                                    aria-invalid={!!errors.email}
                                                    required
                                                />
                                                {errors.email && <FieldError>{errors.email}</FieldError>}
                                            </Field>
                                        </FieldGroup>
                                    </FieldSet>

                                    {/* Security Section */}
                                    <FieldSet className="rounded-lg border p-4">
                                        <FieldLegend className="text-muted-foreground px-2 text-sm font-medium">Security</FieldLegend>
                                        <FieldGroup className="gap-4">
                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <Field data-invalid={!!errors.password}>
                                                    <FieldLabel htmlFor="password">Password</FieldLabel>
                                                    <Input
                                                        id="password"
                                                        type="password"
                                                        value={data.password}
                                                        onChange={(e) => setData('password', e.target.value)}
                                                        aria-invalid={!!errors.password}
                                                        required
                                                    />
                                                    {errors.password && <FieldError>{errors.password}</FieldError>}
                                                </Field>
                                                <Field data-invalid={!!errors.password_confirmation}>
                                                    <FieldLabel htmlFor="password_confirmation">Confirm Password</FieldLabel>
                                                    <Input
                                                        id="password_confirmation"
                                                        type="password"
                                                        value={data.password_confirmation}
                                                        onChange={(e) => setData('password_confirmation', e.target.value)}
                                                        aria-invalid={!!errors.password_confirmation}
                                                        required
                                                    />
                                                    {errors.password_confirmation && <FieldError>{errors.password_confirmation}</FieldError>}
                                                </Field>
                                            </div>
                                        </FieldGroup>
                                    </FieldSet>

                                    {/* Role Assignment Section */}
                                    <FieldSet className="rounded-lg border p-4">
                                        <FieldLegend className="text-muted-foreground px-2 text-sm font-medium">Role Assignment</FieldLegend>
                                        <FieldGroup className="gap-4">
                                            <Field data-invalid={!!errors.role}>
                                                <FieldLabel htmlFor="role">Role</FieldLabel>
                                                <Select value={data.role} onValueChange={(value) => setData('role', value)} required>
                                                    <SelectTrigger id="role" aria-invalid={!!errors.role}>
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
                                                <FieldDescription>Assign a role to define user permissions</FieldDescription>
                                                {errors.role && <FieldError>{errors.role}</FieldError>}
                                            </Field>
                                        </FieldGroup>
                                    </FieldSet>

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
