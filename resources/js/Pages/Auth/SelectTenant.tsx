import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface TenantOption {
    user_id: string;
    tenant_name: string;
    tenant_slug: string | null;
    user_name: string;
    role: string;
}

export default function SelectTenant({ tenants }: { tenants: TenantOption[] }) {
    const { data, setData, post, processing, errors } = useForm({
        user_id: tenants[0]?.user_id ?? '',
    });

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(route('login.tenant.store'));
    };

    return (
        <GuestLayout>
            <Head title="Selecciona empresa" />

            <div className="mb-5">
                <h1 className="text-lg font-semibold text-gray-900">
                    Selecciona empresa
                </h1>
                <p className="mt-1 text-sm text-gray-600">
                    Tu correo tiene acceso a más de una cuenta.
                </p>
            </div>

            <form onSubmit={submit}>
                <div className="space-y-3">
                    {tenants.map((tenant) => (
                        <label
                            key={tenant.user_id}
                            className={`flex cursor-pointer items-start gap-3 rounded-md border p-4 transition ${
                                data.user_id === tenant.user_id
                                    ? 'border-ari-500 bg-ari-50'
                                    : 'border-gray-200 bg-white hover:border-ari-200'
                            }`}
                        >
                            <input
                                type="radio"
                                name="user_id"
                                value={tenant.user_id}
                                checked={data.user_id === tenant.user_id}
                                onChange={(e) => setData('user_id', e.target.value)}
                                className="mt-1 border-gray-300 text-ari-600 focus:ring-ari-500"
                            />
                            <span className="min-w-0">
                                <span className="block truncate text-sm font-semibold text-gray-900">
                                    {tenant.tenant_name}
                                </span>
                                <span className="mt-0.5 block text-xs text-gray-500">
                                    {tenant.user_name} · {tenant.role}
                                </span>
                            </span>
                        </label>
                    ))}
                </div>

                <InputError message={errors.user_id} className="mt-2" />

                <div className="mt-6 flex items-center justify-between gap-3">
                    <Link href={route('login')}>
                        <SecondaryButton>Volver</SecondaryButton>
                    </Link>

                    <PrimaryButton disabled={processing || !data.user_id}>
                        Continuar
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
