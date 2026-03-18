import AppLayout from '@/Layouts/AppLayout';
import { Head, usePage } from '@inertiajs/react';
import { useState, useRef } from 'react';
import { PageProps, NotificationPreferences } from '@/types';
import { Camera, Save, Bell, Lock, Trash2, Loader2 } from 'lucide-react';
import axios from 'axios';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import DeleteUserForm from './Partials/DeleteUserForm';

const NOTIF_OPTIONS: { key: keyof NotificationPreferences; label: string; desc: string }[] = [
    { key: 'sound_enabled',      label: 'Sonido',                   desc: 'Reproducir sonido al recibir mensajes nuevos.' },
    { key: 'new_message',        label: 'Mensajes nuevos',          desc: 'Notificar cuando llegue un mensaje en tus conversaciones.' },
    { key: 'new_conversation',   label: 'Conversaciones nuevas',    desc: 'Notificar cuando se cree una nueva conversación.' },
    { key: 'assignment',         label: 'Asignaciones',             desc: 'Notificar cuando te asignen una conversación.' },
    { key: 'deal_stage_change',  label: 'Cambios de etapa en deals', desc: 'Notificar cuando un negocio que gestionas cambie de etapa.' },
];

export default function ProfileEdit({
    mustVerifyEmail,
    status,
}: PageProps<{ mustVerifyEmail: boolean; status?: string }>) {
    const { auth } = usePage<PageProps>().props;
    const user = auth.user;

    const [name, setName] = useState(user.name);
    const [savingProfile, setSavingProfile] = useState(false);
    const [profileSaved, setProfileSaved] = useState(false);

    const [avatarPreview, setAvatarPreview] = useState<string | null>(user.avatar_url);
    const [uploadingAvatar, setUploadingAvatar] = useState(false);
    const avatarInputRef = useRef<HTMLInputElement>(null);

    const defaultNotifs: NotificationPreferences = {
        sound_enabled:      true,
        new_message:        true,
        new_conversation:   true,
        assignment:         true,
        deal_stage_change:  false,
        ...(user.notification_preferences ?? {}),
    };
    const [notifs, setNotifs] = useState<NotificationPreferences>(defaultNotifs);
    const [savingNotifs, setSavingNotifs] = useState(false);
    const [notifsSaved, setNotifsSaved] = useState(false);

    const handleAvatarChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setAvatarPreview(URL.createObjectURL(file));
        setUploadingAvatar(true);
        const form = new FormData();
        form.append('avatar', file);
        try {
            const res = await axios.post('/profile/avatar', form, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            setAvatarPreview(res.data.avatar_url);
        } catch {
            setAvatarPreview(user.avatar_url);
        } finally {
            setUploadingAvatar(false);
        }
    };

    const handleSaveProfile = async () => {
        setSavingProfile(true);
        try {
            await axios.patch('/profile', { name });
            setProfileSaved(true);
            setTimeout(() => setProfileSaved(false), 3000);
        } finally {
            setSavingProfile(false);
        }
    };

    const handleSaveNotifs = async () => {
        setSavingNotifs(true);
        try {
            await axios.patch('/profile/notifications', { notification_preferences: notifs });
            setNotifsSaved(true);
            setTimeout(() => setNotifsSaved(false), 3000);
        } finally {
            setSavingNotifs(false);
        }
    };

    return (
        <AppLayout title="Mi perfil">
            <Head title="Mi perfil" />

            <div className="max-w-2xl space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-gray-900">Mi perfil</h1>
                    <p className="mt-1 text-sm text-gray-500">Gestiona tu información personal y preferencias.</p>
                </div>

                {/* Avatar + info */}
                <section className="rounded-xl border border-gray-200 bg-white p-5">
                    <h2 className="mb-4 text-base font-semibold text-gray-900">Información personal</h2>

                    {/* Avatar */}
                    <div className="mb-5 flex items-center gap-4">
                        <div className="relative">
                            <div className="h-16 w-16 overflow-hidden rounded-full bg-brand-100">
                                {avatarPreview ? (
                                    <img src={avatarPreview} alt="Avatar" className="h-full w-full object-cover" />
                                ) : (
                                    <div className="flex h-full w-full items-center justify-center text-2xl font-semibold text-brand-700">
                                        {user.name.charAt(0).toUpperCase()}
                                    </div>
                                )}
                            </div>
                            <button
                                onClick={() => avatarInputRef.current?.click()}
                                disabled={uploadingAvatar}
                                className="absolute -bottom-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full border border-white bg-brand-600 text-white hover:bg-brand-700"
                                title="Cambiar foto"
                            >
                                {uploadingAvatar
                                    ? <Loader2 className="h-3 w-3 animate-spin" />
                                    : <Camera className="h-3 w-3" />
                                }
                            </button>
                            <input
                                ref={avatarInputRef}
                                type="file"
                                accept="image/jpg,image/jpeg,image/png,image/webp"
                                className="hidden"
                                onChange={handleAvatarChange}
                            />
                        </div>
                        <div>
                            <p className="text-sm font-medium text-gray-900">{user.name}</p>
                            <p className="text-xs text-gray-500">{user.email}</p>
                            <p className="mt-0.5 text-xs text-gray-400">
                                {user.role === 'owner' ? 'Propietario' : user.role === 'admin' ? 'Administrador' : 'Agente'}
                            </p>
                        </div>
                    </div>

                    {/* Name */}
                    <div className="mb-4">
                        <label className="mb-1 block text-sm font-medium text-gray-700">Nombre</label>
                        <input
                            type="text"
                            value={name}
                            onChange={e => setName(e.target.value)}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                        />
                    </div>

                    {/* Email (read-only for now) */}
                    <div className="mb-4">
                        <label className="mb-1 block text-sm font-medium text-gray-700">Correo electrónico</label>
                        <input
                            type="email"
                            value={user.email}
                            readOnly
                            className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500"
                        />
                    </div>

                    <div className="flex items-center gap-3">
                        <button
                            onClick={handleSaveProfile}
                            disabled={savingProfile}
                            className="flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60"
                        >
                            {savingProfile ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                            Guardar
                        </button>
                        {profileSaved && <span className="text-sm text-green-600">¡Guardado!</span>}
                    </div>
                </section>

                {/* Notifications */}
                <section className="rounded-xl border border-gray-200 bg-white p-5">
                    <div className="mb-4 flex items-center gap-2">
                        <Bell className="h-5 w-5 text-brand-600" />
                        <h2 className="text-base font-semibold text-gray-900">Notificaciones</h2>
                    </div>
                    <div className="space-y-3">
                        {NOTIF_OPTIONS.map(({ key, label, desc }) => (
                            <label key={key} className="flex cursor-pointer items-start gap-3">
                                <input
                                    type="checkbox"
                                    checked={notifs[key] ?? false}
                                    onChange={e => setNotifs(p => ({ ...p, [key]: e.target.checked }))}
                                    className="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                                />
                                <div>
                                    <p className="text-sm font-medium text-gray-900">{label}</p>
                                    <p className="text-xs text-gray-500">{desc}</p>
                                </div>
                            </label>
                        ))}
                    </div>
                    <div className="mt-4 flex items-center gap-3">
                        <button
                            onClick={handleSaveNotifs}
                            disabled={savingNotifs}
                            className="flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60"
                        >
                            {savingNotifs ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                            Guardar preferencias
                        </button>
                        {notifsSaved && <span className="text-sm text-green-600">¡Guardado!</span>}
                    </div>
                </section>

                {/* Password */}
                <section className="rounded-xl border border-gray-200 bg-white p-5">
                    <div className="mb-4 flex items-center gap-2">
                        <Lock className="h-5 w-5 text-brand-600" />
                        <h2 className="text-base font-semibold text-gray-900">Cambiar contraseña</h2>
                    </div>
                    <UpdatePasswordForm className="" />
                </section>

                {/* Delete account */}
                <section className="rounded-xl border border-red-200 bg-white p-5">
                    <div className="mb-4 flex items-center gap-2">
                        <Trash2 className="h-5 w-5 text-red-500" />
                        <h2 className="text-base font-semibold text-red-700">Zona peligrosa</h2>
                    </div>
                    <DeleteUserForm className="" />
                </section>
            </div>
        </AppLayout>
    );
}
