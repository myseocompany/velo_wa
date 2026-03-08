import { Contact, Conversation, User } from '@/types';
import { formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';
import {
    Building2,
    ChevronRight,
    Clock,
    Mail,
    Phone,
    Tag,
    User as UserIcon,
    X,
} from 'lucide-react';
import ContactAvatar from './ContactAvatar';

interface Props {
    conversation: Conversation;
    onClose: () => void;
}

function InfoRow({ icon: Icon, label, value }: { icon: React.ElementType; label: string; value: string | null | undefined }) {
    if (!value) return null;
    return (
        <div className="flex items-start gap-3 py-2">
            <Icon className="mt-0.5 h-4 w-4 flex-shrink-0 text-gray-400" />
            <div className="min-w-0">
                <p className="text-[10px] font-medium uppercase tracking-wide text-gray-400">{label}</p>
                <p className="text-sm text-gray-900">{value}</p>
            </div>
        </div>
    );
}

export default function ContactPanel({ conversation, onClose }: Props) {
    const contact  = conversation.contact as Contact | undefined;
    const assignee = conversation.assignee as User | undefined;

    const displayName =
        contact?.name ?? contact?.push_name ?? contact?.phone ?? 'Desconocido';

    const dt1Seconds = conversation.first_message_at && conversation.first_response_at
        ? Math.abs(
              new Date(conversation.first_response_at).getTime() -
              new Date(conversation.first_message_at).getTime(),
          ) / 1000
        : null;

    function formatDt1(seconds: number): string {
        if (seconds < 60) return `${Math.round(seconds)}s`;
        if (seconds < 3600) return `${Math.round(seconds / 60)}m`;
        return `${(seconds / 3600).toFixed(1)}h`;
    }

    const statusLabel: Record<string, string> = {
        open:    'Abierta',
        pending: 'Pendiente',
        closed:  'Cerrada',
    };

    const statusColor: Record<string, string> = {
        open:    'bg-green-100 text-green-700',
        pending: 'bg-yellow-100 text-yellow-700',
        closed:  'bg-gray-100 text-gray-600',
    };

    return (
        <aside className="flex w-72 flex-shrink-0 flex-col overflow-hidden border-l border-gray-200 bg-white">
            {/* Header */}
            <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                <span className="text-sm font-semibold text-gray-800">Información</span>
                <button onClick={onClose} className="rounded-lg p-1 text-gray-400 hover:bg-gray-100">
                    <X className="h-4 w-4" />
                </button>
            </div>

            <div className="flex-1 overflow-y-auto">
                {/* Contact overview */}
                <div className="flex flex-col items-center gap-2 border-b border-gray-100 px-4 py-5">
                    <ContactAvatar
                        name={displayName}
                        imageUrl={contact?.profile_pic_url}
                        sizeClass="h-16 w-16"
                    />
                    <div className="text-center">
                        <p className="font-semibold text-gray-900">{displayName}</p>
                        {contact?.push_name && contact.name && contact.push_name !== contact.name && (
                            <p className="text-xs text-gray-400">{contact.push_name}</p>
                        )}
                    </div>
                </div>

                {/* Contact details */}
                <div className="border-b border-gray-100 px-4 py-2">
                    <p className="mb-1 text-[10px] font-semibold uppercase tracking-widest text-gray-400">Contacto</p>
                    <InfoRow icon={Phone} label="Teléfono" value={contact?.phone} />
                    <InfoRow icon={Mail} label="Email" value={contact?.email} />
                    <InfoRow icon={Building2} label="Empresa" value={contact?.company} />
                    {contact?.tags && contact.tags.length > 0 && (
                        <div className="flex items-start gap-3 py-2">
                            <Tag className="mt-0.5 h-4 w-4 flex-shrink-0 text-gray-400" />
                            <div>
                                <p className="text-[10px] font-medium uppercase tracking-wide text-gray-400">Etiquetas</p>
                                <div className="mt-1 flex flex-wrap gap-1">
                                    {contact.tags.map((tag) => (
                                        <span
                                            key={tag}
                                            className="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700"
                                        >
                                            {tag}
                                        </span>
                                    ))}
                                </div>
                            </div>
                        </div>
                    )}
                    {contact?.notes && (
                        <div className="mt-2 rounded-lg bg-yellow-50 px-3 py-2 text-xs text-gray-700">
                            {contact.notes}
                        </div>
                    )}
                </div>

                {/* Conversation details */}
                <div className="border-b border-gray-100 px-4 py-2">
                    <p className="mb-1 text-[10px] font-semibold uppercase tracking-widest text-gray-400">Conversación</p>

                    <div className="flex items-center gap-2 py-2">
                        <span
                            className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColor[conversation.status] ?? 'bg-gray-100 text-gray-600'}`}
                        >
                            {statusLabel[conversation.status] ?? conversation.status}
                        </span>
                    </div>

                    {assignee && (
                        <div className="flex items-start gap-3 py-2">
                            <UserIcon className="mt-0.5 h-4 w-4 flex-shrink-0 text-gray-400" />
                            <div>
                                <p className="text-[10px] font-medium uppercase tracking-wide text-gray-400">Asignado</p>
                                <p className="text-sm text-gray-900">{assignee.name}</p>
                            </div>
                        </div>
                    )}

                    {dt1Seconds !== null && (
                        <div className="flex items-start gap-3 py-2">
                            <Clock className="mt-0.5 h-4 w-4 flex-shrink-0 text-gray-400" />
                            <div>
                                <p className="text-[10px] font-medium uppercase tracking-wide text-gray-400">Tiempo de respuesta (Dt1)</p>
                                <p className="text-sm font-semibold text-brand-600">{formatDt1(dt1Seconds)}</p>
                            </div>
                        </div>
                    )}

                    {conversation.first_message_at && (
                        <div className="py-1 text-xs text-gray-400">
                            Primera vez:{' '}
                            {formatDistanceToNow(new Date(conversation.first_message_at), {
                                addSuffix: true,
                                locale: es,
                            })}
                        </div>
                    )}
                </div>

                {/* Activity */}
                <div className="px-4 py-2">
                    <p className="mb-1 text-[10px] font-semibold uppercase tracking-widest text-gray-400">Actividad</p>
                    <div className="text-xs text-gray-500">
                        <p>{conversation.message_count} mensajes en esta conversación</p>
                        {contact?.last_contact_at && (
                            <p className="mt-1">
                                Último contacto:{' '}
                                {formatDistanceToNow(new Date(contact.last_contact_at), {
                                    addSuffix: true,
                                    locale: es,
                                })}
                            </p>
                        )}
                    </div>
                </div>
            </div>

            {/* Footer link */}
            <div className="border-t border-gray-100 px-4 py-3">
                <button className="flex w-full items-center justify-between text-xs text-brand-600 hover:text-brand-700">
                    <span>Ver perfil completo</span>
                    <ChevronRight className="h-3.5 w-3.5" />
                </button>
            </div>
        </aside>
    );
}
