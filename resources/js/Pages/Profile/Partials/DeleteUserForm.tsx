import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';

export default function DeleteUserForm({
    className = '',
}: {
    className?: string;
}) {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        password: '',
    });

    const confirmUserDeletion = () => {
        setConfirmingUserDeletion(true);
    };

    const deleteUser: FormEventHandler = (e) => {
        e.preventDefault();

        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);

        clearErrors();
        reset();
    };

    return (
        <section className={`space-y-6 ${className}`}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    Eliminar cuenta
                </h2>

                <p className="mt-1 text-sm text-gray-600">
                    Una vez elimines tu cuenta, todos sus recursos y datos se
                    borrarán de forma permanente. Antes de continuar, asegúrate
                    de conservar cualquier información que quieras mantener.
                </p>
            </header>

            <DangerButton onClick={confirmUserDeletion}>
                Eliminar cuenta
            </DangerButton>

            <Modal show={confirmingUserDeletion} onClose={closeModal}>
                <form onSubmit={deleteUser} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900">
                        ¿Seguro que quieres eliminar tu cuenta?
                    </h2>

                    <p className="mt-1 text-sm text-gray-600">
                        Esta acción eliminará permanentemente tu cuenta y todos
                        sus datos. Ingresa tu contraseña para confirmar que
                        deseas continuar.
                    </p>

                    <div className="mt-6">
                        <InputLabel
                            htmlFor="password"
                            value="Contraseña"
                            className="sr-only"
                        />

                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            ref={passwordInput}
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                            className="mt-1 block min-w-0 w-full"
                            isFocused
                            placeholder="Contraseña"
                        />

                        <InputError
                            message={errors.password}
                            className="mt-2"
                        />
                    </div>

                    <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <SecondaryButton
                            onClick={closeModal}
                            className="min-h-11 w-full justify-center sm:w-auto"
                        >
                            Cancelar
                        </SecondaryButton>

                        <DangerButton
                            className="min-h-11 w-full justify-center sm:ms-3 sm:w-auto"
                            disabled={processing}
                        >
                            Eliminar cuenta
                        </DangerButton>
                    </div>
                </form>
            </Modal>
        </section>
    );
}
