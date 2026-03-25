import AppLayout from '@/Layouts/AppLayout';
import { Head, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { PageProps, User, UserRole } from '@/types';
import {
    UserPlus, MoreVertical, ShieldCheck, UserX, UserCheck,
    Mail, Loader2
} from 'lucide-react';
import axios from 'axios';

const ROLE_LABELS: Record<UserRole, string> = {
    owner: 'Propietario',
    admin: 'Administrador',
    agent: 'Agente',
};

const ROLE_COLORS: Record<UserRole, string> = {
    owner: 'bg-purple-100 text-purple-700',
    admin: 'bg-blue-100 text-blue-700',
    agent: 'bg-gray-100 text-gray-600',
};

interface TeamMember extends User {
    role_label: string;
}

interface InviteForm {
    name: string;
    email: string;
    role: UserRole;
    max_concurrent_conversations: number;
}

export default function SettingsTeam() {
    const { auth } = usePage<PageProps>().props;
    const canManage = auth.user.role === 'owner' || auth.user.role === 'admin';

    const [members, setMembers] = useState<TeamMember[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const [showInvite, setShowInvite] = useState(false);
    const [inviteForm, setInviteForm] = useState<InviteForm>({
        name: '',
        email: '',
        role: 'agent',
        max_concurrent_conversations: 10,
    });
    const [inviting, setInviting] = useState(false);
    const [inviteResult, setInviteResult] = useState<{ password: string; name: string } | null>(null);
    const [inviteError, setInviteError] = useState<string | null>(null);

    const [menuOpen, setMenuOpen] = useState<string | null>(null);
    const [editingId, setEditingId] = useState<string | null>(null);
    const [editRole, setEditRole] = useState<UserRole>('agent');
    const [actionLoading, setActionLoading] = useState<string | null>(null);

    const fetchMembers = () => {
        axios.get('/api/v1/team')
            .then(res => setMembers(res.data.data))
            .catch(() => setError('No se pudo cargar el equipo.'))
            .finally(() => setLoading(false));
    };

    useEffect(() => { fetchMembers(); }, []);

    const handleInvite = async () => {
        setInviting(true);
        setInviteError(null);
        try {
            const res = await axios.post('/api/v1/team/invite', inviteForm);
            setInviteResult({ password: res.data.temporary_password, name: inviteForm.name });
            setInviteForm({ name: '', email: '', role: 'agent', max_concurrent_conversations: 10 });
            fetchMembers();
        } catch (err: any) {
            setInviteError(err.response?.data?.message ?? 'Error al invitar.');
        } finally {
            setInviting(false);
        }
    };

    const handleDeactivate = async (member: TeamMember) => {
        setActionLoading(member.id);
        try {
            await axios.patch(`/api/v1/team/${member.id}/deactivate`);
            fetchMembers();
        } catch (err: any) {
            setError(err.response?.data?.message ?? 'Error al desactivar.');
        } finally {
            setActionLoading(null);
            setMenuOpen(null);
        }
    };

    const handleReactivate = async (member: TeamMember) => {
        setActionLoading(member.id);
        try {
            await axios.patch(`/api/v1/team/${member.id}/reactivate`);
            fetchMembers();
        } catch (err: any) {
            setError(err.response?.data?.message ?? 'Error al reactivar.');
        } finally {
            setActionLoading(null);
            setMenuOpen(null);
        }
    };

    const handleUpdateRole = async (member: TeamMember) => {
        setActionLoading(member.id);
        try {
            await axios.patch(`/api/v1/team/${member.id}`, { role: editRole });
            fetchMembers();
            setEditingId(null);
        } catch (err: any) {
            setError(err.response?.data?.message ?? 'Error al actualizar rol.');
        } finally {
            setActionLoading(null);
        }
    };

    const activeMembers  = members.filter(m => m.is_active);
    const inactiveMembers = members.filter(m => !m.is_active);

    return (
        <AppLayout title="Gestión de equipo">
            <Head title="Gestión de equipo" />

            <div className="max-w-3xl space-y-6 p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">Equipo</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Invita, edita roles y gestiona los accesos de tu equipo.
                        </p>
                    </div>
                    {canManage && (
                        <button
                            onClick={() => { setShowInvite(!showInvite); setInviteResult(null); setInviteError(null); }}
                            className="flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700 sm:w-auto"
                        >
                            <UserPlus className="h-4 w-4" />
                            Invitar miembro
                        </button>
                    )}
                </div>

                {error && (
                    <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{error}</div>
                )}

                {/* Invite panel */}
                {showInvite && canManage && (
                    <div className="rounded-xl border border-ari-200 bg-ari-50 p-5 space-y-4">
                        <h3 className="text-base font-semibold text-gray-900">Invitar nuevo miembro</h3>

                        {inviteResult ? (
                            <div className="space-y-3">
                                <div className="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700">
                                    <p className="font-medium">✓ {inviteResult.name} ha sido invitado.</p>
                                    <p className="mt-2">Contraseña temporal: <code className="rounded bg-green-100 px-2 py-0.5 font-mono font-bold">{inviteResult.password}</code></p>
                                    <p className="mt-1 text-xs text-green-600">Comparte esta contraseña de forma segura. El usuario puede cambiarla en su perfil.</p>
                                </div>
                                <button
                                    onClick={() => { setInviteResult(null); setShowInvite(false); }}
                                    className="inline-flex min-h-11 items-center rounded-lg px-3 text-sm text-ari-600 hover:bg-ari-100 hover:text-ari-700"
                                >
                                    Cerrar
                                </button>
                            </div>
                        ) : (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-700">Nombre</label>
                                    <input
                                        type="text"
                                        value={inviteForm.name}
                                        onChange={e => setInviteForm(p => ({ ...p, name: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                                        placeholder="Juan García"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-700">Correo electrónico</label>
                                    <input
                                        type="email"
                                        value={inviteForm.email}
                                        onChange={e => setInviteForm(p => ({ ...p, email: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                                        placeholder="juan@empresa.com"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-700">Rol</label>
                                    <select
                                        value={inviteForm.role}
                                        onChange={e => setInviteForm(p => ({ ...p, role: e.target.value as UserRole }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                                    >
                                        <option value="agent">Agente</option>
                                        <option value="admin">Administrador</option>
                                        {auth.user.role === 'owner' && <option value="owner">Propietario</option>}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-700">Conversaciones simultáneas</label>
                                    <input
                                        type="number"
                                        min={1}
                                        max={100}
                                        value={inviteForm.max_concurrent_conversations}
                                        onChange={e => setInviteForm(p => ({ ...p, max_concurrent_conversations: parseInt(e.target.value) }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-ari-500 focus:outline-none"
                                    />
                                </div>
                                {inviteError && (
                                    <div className="col-span-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                        {inviteError}
                                    </div>
                                )}
                                <div className="col-span-2 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                    <button
                                        onClick={handleInvite}
                                        disabled={inviting || !inviteForm.name || !inviteForm.email}
                                        className="flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-ari-600 px-4 py-2 text-sm font-medium text-white hover:bg-ari-700 disabled:opacity-60 sm:w-auto"
                                    >
                                        {inviting && <Loader2 className="h-4 w-4 animate-spin" />}
                                        Crear cuenta
                                    </button>
                                    <button
                                        onClick={() => setShowInvite(false)}
                                        className="min-h-11 w-full rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 sm:w-auto"
                                    >
                                        Cancelar
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* Active members */}
                {loading ? (
                    <div className="space-y-3">
                        {[1, 2, 3].map(i => (
                            <div key={i} className="h-16 animate-pulse rounded-xl bg-gray-200" />
                        ))}
                    </div>
                ) : (
                    <>
                        <section>
                            <h2 className="mb-3 text-sm font-medium text-gray-500 uppercase tracking-wide">
                                Activos ({activeMembers.length})
                            </h2>
                            <div className="divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white">
                                {activeMembers.length === 0 && (
                                    <p className="p-4 text-sm text-gray-400">Sin miembros activos.</p>
                                )}
                                {activeMembers.map(member => (
                                    <MemberRow
                                        key={member.id}
                                        member={member}
                                        currentUser={auth.user}
                                        canManage={canManage}
                                        menuOpen={menuOpen}
                                        setMenuOpen={setMenuOpen}
                                        editingId={editingId}
                                        setEditingId={setEditingId}
                                        editRole={editRole}
                                        setEditRole={setEditRole}
                                        actionLoading={actionLoading}
                                        onDeactivate={handleDeactivate}
                                        onReactivate={handleReactivate}
                                        onUpdateRole={handleUpdateRole}
                                    />
                                ))}
                            </div>
                        </section>

                        {inactiveMembers.length > 0 && (
                            <section>
                                <h2 className="mb-3 text-sm font-medium text-gray-500 uppercase tracking-wide">
                                    Inactivos ({inactiveMembers.length})
                                </h2>
                                <div className="divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white opacity-70">
                                    {inactiveMembers.map(member => (
                                        <MemberRow
                                            key={member.id}
                                            member={member}
                                            currentUser={auth.user}
                                            canManage={canManage}
                                            menuOpen={menuOpen}
                                            setMenuOpen={setMenuOpen}
                                            editingId={editingId}
                                            setEditingId={setEditingId}
                                            editRole={editRole}
                                            setEditRole={setEditRole}
                                            actionLoading={actionLoading}
                                            onDeactivate={handleDeactivate}
                                            onReactivate={handleReactivate}
                                            onUpdateRole={handleUpdateRole}
                                        />
                                    ))}
                                </div>
                            </section>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}

interface MemberRowProps {
    member: TeamMember;
    currentUser: User;
    canManage: boolean;
    menuOpen: string | null;
    setMenuOpen: (id: string | null) => void;
    editingId: string | null;
    setEditingId: (id: string | null) => void;
    editRole: UserRole;
    setEditRole: (r: UserRole) => void;
    actionLoading: string | null;
    onDeactivate: (m: TeamMember) => void;
    onReactivate: (m: TeamMember) => void;
    onUpdateRole: (m: TeamMember) => void;
}

function MemberRow({
    member, currentUser, canManage,
    menuOpen, setMenuOpen,
    editingId, setEditingId, editRole, setEditRole,
    actionLoading, onDeactivate, onReactivate, onUpdateRole,
}: MemberRowProps) {
    const isSelf = member.id === currentUser.id;
    const isEditing = editingId === member.id;

    return (
        <div className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center">
            <div className="flex min-w-0 flex-1 items-start gap-4">
                {/* Avatar */}
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-ari-100 text-sm font-semibold text-ari-700">
                    {member.avatar_url
                        ? <img src={member.avatar_url} alt={member.name} className="h-9 w-9 rounded-full object-cover" />
                        : member.name.charAt(0).toUpperCase()
                    }
                </div>

                {/* Info */}
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="truncate text-sm font-medium text-gray-900">{member.name}</span>
                        {isSelf && <span className="text-xs text-gray-400">(tú)</span>}
                        {member.is_online && (
                            <span className="h-2 w-2 rounded-full bg-green-400" title="En línea" />
                        )}
                    </div>
                    <div className="mt-0.5 flex items-center gap-1.5">
                        <Mail className="h-3 w-3 flex-shrink-0 text-gray-400" />
                        <span className="truncate text-xs text-gray-500">{member.email}</span>
                    </div>
                </div>
            </div>

            <div className="flex w-full flex-wrap items-center gap-3 sm:w-auto sm:flex-nowrap sm:justify-end">
                {/* Role */}
                {isEditing ? (
                    <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                        <select
                            value={editRole}
                            onChange={e => setEditRole(e.target.value as UserRole)}
                            className="min-w-0 w-full rounded-md border border-gray-300 px-2 py-2.5 text-xs sm:w-auto"
                            autoFocus
                        >
                            <option value="agent">Agente</option>
                            <option value="admin">Administrador</option>
                            {currentUser.role === 'owner' && <option value="owner">Propietario</option>}
                        </select>
                        <button
                            onClick={() => onUpdateRole(member)}
                            disabled={actionLoading === member.id}
                            className="inline-flex min-h-11 items-center justify-center rounded bg-ari-600 px-3 py-2 text-xs text-white hover:bg-ari-700 disabled:opacity-60"
                        >
                            {actionLoading === member.id ? <Loader2 className="h-3 w-3 animate-spin" /> : 'Guardar'}
                        </button>
                        <button
                            onClick={() => setEditingId(null)}
                            className="inline-flex min-h-11 items-center justify-center text-xs text-gray-500 hover:text-gray-700"
                        >
                            Cancelar
                        </button>
                    </div>
                ) : (
                    <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${ROLE_COLORS[member.role as UserRole]}`}>
                        {ROLE_LABELS[member.role as UserRole]}
                    </span>
                )}

                {/* Actions menu */}
                {canManage && !isSelf && !isEditing && (
                    <div className="relative w-full sm:w-auto">
                        <button
                            onClick={() => setMenuOpen(menuOpen === member.id ? null : member.id)}
                            className="flex min-h-11 w-full items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-100 sm:h-11 sm:w-11"
                        >
                            <MoreVertical className="h-4 w-4 text-gray-400" />
                        </button>
                        {menuOpen === member.id && (
                            <div className="absolute right-0 top-full z-10 mt-1 w-full rounded-md border border-gray-200 bg-white py-1 shadow-lg sm:w-48">
                                <button
                                    onClick={() => {
                                        setEditingId(member.id);
                                        setEditRole(member.role as UserRole);
                                        setMenuOpen(null);
                                    }}
                                    className="flex min-h-11 w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                >
                                    <ShieldCheck className="h-4 w-4" />
                                    Cambiar rol
                                </button>
                                {member.is_active ? (
                                    <button
                                        onClick={() => onDeactivate(member)}
                                        disabled={actionLoading === member.id}
                                        className="flex min-h-11 w-full items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50"
                                    >
                                        <UserX className="h-4 w-4" />
                                        Desactivar
                                    </button>
                                ) : (
                                    <button
                                        onClick={() => onReactivate(member)}
                                        disabled={actionLoading === member.id}
                                        className="flex min-h-11 w-full items-center gap-2 px-4 py-2 text-sm text-green-700 hover:bg-green-50"
                                    >
                                        <UserCheck className="h-4 w-4" />
                                        Reactivar
                                    </button>
                                )}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
