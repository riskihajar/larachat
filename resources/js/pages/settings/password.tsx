import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useRef } from 'react';

import { update as passwordUpdate } from '@/actions/App/Http/Controllers/Settings/PasswordController';
import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Password settings',
        href: '/settings/password',
    },
];

export default function Password() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword: FormEventHandler = (e) => {
        e.preventDefault();

        put(passwordUpdate().url, {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }

                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            className="flex h-[calc(100vh-theme(spacing.4))] flex-col overflow-hidden md:h-[calc(100vh-theme(spacing.8))]"
        >
            <Head title="Profile settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Update password" description="Ensure your account is using a long, random password to stay secure" />

                    <form onSubmit={updatePassword} className="space-y-6">
                        <FieldGroup className="gap-4">
                            <Field data-invalid={!!errors.current_password}>
                                <FieldLabel htmlFor="current_password">Current password</FieldLabel>
                                <Input
                                    id="current_password"
                                    ref={currentPasswordInput}
                                    value={data.current_password}
                                    onChange={(e) => setData('current_password', e.target.value)}
                                    type="password"
                                    autoComplete="current-password"
                                    placeholder="Current password"
                                    aria-invalid={!!errors.current_password}
                                />
                                {errors.current_password && <FieldError>{errors.current_password}</FieldError>}
                            </Field>

                            <Field data-invalid={!!errors.password}>
                                <FieldLabel htmlFor="password">New password</FieldLabel>
                                <Input
                                    id="password"
                                    ref={passwordInput}
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    type="password"
                                    autoComplete="new-password"
                                    placeholder="New password"
                                    aria-invalid={!!errors.password}
                                />
                                {errors.password && <FieldError>{errors.password}</FieldError>}
                            </Field>

                            <Field data-invalid={!!errors.password_confirmation}>
                                <FieldLabel htmlFor="password_confirmation">Confirm password</FieldLabel>
                                <Input
                                    id="password_confirmation"
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                    type="password"
                                    autoComplete="new-password"
                                    placeholder="Confirm password"
                                    aria-invalid={!!errors.password_confirmation}
                                />
                                {errors.password_confirmation && <FieldError>{errors.password_confirmation}</FieldError>}
                            </Field>
                        </FieldGroup>

                        <div className="flex items-center gap-4">
                            <Button disabled={processing}>Save password</Button>

                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-neutral-600">Saved</p>
                            </Transition>
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
