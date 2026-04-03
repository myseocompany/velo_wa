// ─── Enums ───────────────────────────────────────────────────────────────────

export type WaStatus = 'disconnected' | 'qr_pending' | 'connected' | 'banned';
export type UserRole = 'owner' | 'admin' | 'agent';
export type TaskPriority = 'low' | 'medium' | 'high';
export type ConversationStatus = 'open' | 'pending' | 'closed';
export type MessageDirection = 'in' | 'out';
export type MessageStatus = 'pending' | 'sent' | 'delivered' | 'read' | 'failed';
export type DealStage = 'lead' | 'qualified' | 'proposal' | 'negotiation' | 'closed_won' | 'closed_lost';
export type AssignmentRuleType = 'round_robin' | 'least_busy' | 'tag_based' | 'manual';
export type AutomationTriggerType = 'new_conversation' | 'keyword' | 'outside_hours' | 'no_response_timeout';
export type AutomationActionType = 'send_message' | 'assign_agent' | 'add_tag' | 'move_stage';
export type Channel = 'whatsapp' | 'manual';
export type ContactSource = 'whatsapp' | 'manual' | 'import';

// ─── Models ──────────────────────────────────────────────────────────────────

export interface BusinessHourDay {
    enabled: boolean;
    start: string;
    end: string;
}

export interface Tenant {
    id: string;
    name: string;
    slug: string;
    wa_instance_id: string | null;
    wa_status: WaStatus;
    wa_phone: string | null;
    wa_connected_at: string | null;
    max_agents: number | null;
    max_contacts: number | null;
    timezone: string;
    business_hours: Record<string, BusinessHourDay> | null;
    auto_close_hours: number | null;
    created_at: string;
    updated_at: string;
}

export interface NotificationPreferences {
    new_message?: boolean;
    new_conversation?: boolean;
    assignment?: boolean;
    deal_stage_change?: boolean;
    sound_enabled?: boolean;
}

export interface User {
    id: string;
    tenant_id: string | null;
    name: string;
    email: string;
    email_verified_at: string | null;
    role: UserRole;
    is_active: boolean;
    is_online: boolean;
    last_seen_at: string | null;
    max_concurrent_conversations: number;
    specialties: string[] | null;
    avatar_url: string | null;
    notification_preferences: NotificationPreferences | null;
    created_at?: string;
    tenant?: Tenant;
}

export interface Contact {
    id: string;
    tenant_id: string;
    wa_id: string | null;
    phone: string | null;
    name: string | null;
    push_name: string | null;
    profile_pic_url: string | null;
    email: string | null;
    company: string | null;
    notes: string | null;
    tags: string[];
    custom_fields: Record<string, unknown>;
    assigned_to: string | null;
    source: ContactSource;
    first_contact_at: string | null;
    last_contact_at: string | null;
    created_at: string;
    updated_at: string;
    assignee?: User;
    display_name?: string;
}

export interface Conversation {
    id: string;
    tenant_id: string;
    contact_id: string;
    status: ConversationStatus;
    channel: Channel;
    assigned_to: string | null;
    assigned_at: string | null;
    first_message_at: string | null;
    first_response_at: string | null;
    last_message_at: string | null;
    message_count: number;
    closed_at: string | null;
    reopen_count: number;
    created_at: string;
    updated_at: string;
    contact?: Contact;
    assignee?: User;
    last_message?: {
        body: string | null;
        direction: MessageDirection;
        created_at: string;
        media_type: string | null;
    } | null;
}

export interface Message {
    id: string;
    conversation_id: string;
    tenant_id: string;
    direction: MessageDirection;
    body: string | null;
    media_url: string | null;
    media_type: string | null;
    media_mime_type: string | null;
    media_filename: string | null;
    status: MessageStatus;
    wa_message_id: string | null;
    error_message: string | null;
    sent_by: string | null;
    is_automated: boolean;
    created_at: string;
    updated_at: string;
    sender?: User;
}

export interface PipelineDeal {
    id: string;
    tenant_id: string;
    contact_id: string;
    conversation_id: string | null;
    title: string;
    stage: DealStage;
    value: string | null;
    currency: string;
    lead_at: string | null;
    qualified_at: string | null;
    proposal_at: string | null;
    negotiation_at: string | null;
    closed_at: string | null;
    lost_reason: string | null;
    won_product: string | null;
    assigned_to: string | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
    contact?: Contact;
    assignee?: User;
}

export interface Task {
    id: string;
    tenant_id: string;
    user_id: string;
    assigned_to: string | null;
    contact_id: string | null;
    conversation_id: string | null;
    deal_id: string | null;
    title: string;
    description: string | null;
    priority: TaskPriority;
    due_at: string | null;
    completed_at: string | null;
    is_overdue: boolean;
    created_at: string;
    updated_at: string;
    assignee?: { id: string; name: string; avatar_url: string | null } | null;
    contact?: { id: string; display_name: string; phone: string | null } | null;
}

export interface QuickReply {
    id: string;
    tenant_id: string;
    shortcut: string;
    title: string;
    body: string;
    has_variables: boolean;
    category: string;
    usage_count: number;
    created_at: string;
    updated_at: string;
}

// ─── Inertia ─────────────────────────────────────────────────────────────────

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        tenant: Tenant;
    };
    flash?: {
        success?: string;
        error?: string;
    };
};

// ─── Pagination ───────────────────────────────────────────────────────────────

export interface PaginatedData<T> {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
    };
}
