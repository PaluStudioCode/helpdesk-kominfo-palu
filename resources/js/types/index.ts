export type UserRole = 'admin' | 'technician' | 'opd_user';
export type TicketStatus = 'pending_admin' | 'in_progress' | 'on_hold' | 'pending_approval' | 'closed' | 'cancelled';
export type TicketPriority = 'low' | 'medium' | 'high' | 'emergency';
export type InfrastructureType = 'Fiber optic' | 'Perangkat/Akses' | 'Power/poe' | 'Converter' | 'Layanan/jaringan' | 'fiber_optic' | 'lan' | 'wifi';
export type NetworkType = InfrastructureType; // backward compatibility alias

export interface User {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    phone_number?: string | null;
    department_id?: number | null;
    status: 'active' | 'inactive';
    department?: Department | null;
}

export interface Department {
    id: number;
    code: string;
    name: string;
    address?: string | null;
    status?: 'active' | 'inactive';
    operator?: User | null;
}

export interface TicketCategory {
    id: number;
    name: string;
    infrastructure_type: InfrastructureType;
    network_type?: InfrastructureType;
    status?: 'active' | 'inactive';
}

export interface TicketResolution {
    id: number;
    ticket_id: number;
    category_id: number;
    affected_device?: string | null;
    actual_repair_location?: string | null;
    inspection_result?: string | null;
    root_cause?: string | null;
    action_taken?: string | null;
    materials_used?: string | null;
    test_result?: string | null;
    test_parameters?: string | null;
    resolution_note?: string | null;
    resolved_by?: number | null;
    resolved_at?: string | null;
    created_at?: string;
    updated_at?: string;
    category?: TicketCategory | null;
    resolver?: User | null;
}

export interface TicketFeedback {
    id: number;
    ticket_id: number;
    rating: number;
    feedback_comment?: string | null;
    rated_by: number;
    rated_at: string;
    created_at?: string;
    rater?: User | null;
}

export interface TicketHold {
    id: number;
    ticket_id: number;
    user_id: number;
    reason_category: string;
    reason_note?: string | null;
    started_at: string;
    ended_at?: string | null;
    duration_minutes: number;
    created_at?: string;
    user?: User | null;
}

export interface TicketAttachment {
    id: number;
    ticket_id: number;
    reply_id?: number | null;
    uploaded_by: number;
    attachment_type: 'issue_proof' | 'resolution_proof' | 'reply_attachment';
    file_path: string;
    file_name: string;
    file_size?: number | null;
    created_at?: string;
}

export interface TicketReply {
    id: number;
    ticket_id: number;
    user_id: number;
    message: string;
    is_internal: boolean;
    created_at: string;
    user: {
        id: number;
        name: string;
        role: UserRole;
    };
    attachments?: TicketAttachment[];
}

export interface TicketStatusHistory {
    id: number;
    ticket_id: number;
    changed_by: number;
    previous_status?: TicketStatus | null;
    new_status: TicketStatus;
    comment?: string | null;
    created_at: string;
    changer?: {
        id: number;
        name: string;
        role: UserRole;
    };
}

export interface Ticket {
    id: number;
    ticket_number: string;
    department_id: number;
    reporter_id: number;
    assigned_to?: number | null;
    title: string;
    location_details?: string | null;
    description?: string | null;
    priority?: TicketPriority | null;
    status: TicketStatus;
    due_at?: string | null;
    created_at?: string;
    updated_at?: string;
    department?: Department | null;
    reporter?: User | null;
    assignee?: User | null;
    technicians?: User[];
    resolution?: TicketResolution | null;
    feedback?: TicketFeedback | null;
    holds?: TicketHold[];
    latest_hold?: TicketHold | null;
    attachments?: TicketAttachment[];
    replies?: TicketReply[];
    status_histories?: TicketStatusHistory[];
    
    // Accessor / Backward-compatibility fields
    category_id?: number | null;
    infrastructure_type?: InfrastructureType | null;
    network_type?: InfrastructureType | null;
    category?: TicketCategory | null;
    affected_device?: string | null;
    actual_repair_location?: string | null;
    resolution_note?: string | null;
    inspection_result?: string | null;
    root_cause?: string | null;
    action_taken?: string | null;
    materials_used?: string | null;
    test_result?: string | null;
    test_parameters?: string | null;
    assigned_at?: string | null;
    cancelled_at?: string | null;
    resolved_at?: string | null;
    closed_at?: string | null;
    rating?: number | null;
    feedback_comment?: string | null;
    rated_at?: string | null;
    hold_reason_category?: string | null;
    hold_reason_note?: string | null;
    hold_started_at?: string | null;
    total_hold_duration_minutes?: number;
}
