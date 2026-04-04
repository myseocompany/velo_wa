import { Bot, BotOff } from 'lucide-react';

interface AiAgentToggleProps {
    globalEnabled: boolean;
    conversationOverride: boolean | null;
    loading?: boolean;
    onToggle: () => void;
}

export default function AiAgentToggle({
    globalEnabled,
    conversationOverride,
    loading = false,
    onToggle,
}: AiAgentToggleProps) {
    const effectiveEnabled = conversationOverride !== null ? conversationOverride : globalEnabled;

    let label = 'Bot activo';
    let icon = <Bot className="h-3.5 w-3.5" />;
    let tone = 'border-emerald-200 bg-emerald-50 text-emerald-700';

    if (!globalEnabled && conversationOverride !== true) {
        label = 'Bot global apagado';
        icon = <BotOff className="h-3.5 w-3.5" />;
        tone = 'border-gray-200 bg-gray-50 text-gray-600';
    } else if (conversationOverride === false) {
        label = 'Bot desactivado aquí';
        icon = <BotOff className="h-3.5 w-3.5" />;
        tone = 'border-amber-200 bg-amber-50 text-amber-700';
    } else if (!effectiveEnabled) {
        label = 'Bot inactivo';
        icon = <BotOff className="h-3.5 w-3.5" />;
        tone = 'border-gray-200 bg-gray-50 text-gray-600';
    }

    return (
        <button
            onClick={onToggle}
            disabled={loading}
            className={`hidden items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition hover:opacity-90 disabled:opacity-50 md:flex ${tone}`}
            title={label}
        >
            {icon}
            {label}
        </button>
    );
}
