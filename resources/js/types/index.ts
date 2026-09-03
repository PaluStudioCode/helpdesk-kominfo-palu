export type UserRole = 'admin' | 'technician' | 'opd_user';
export type TicketStatus = 'pending_admin' | 'in_progress' | 'pending_approval' | 'closed' | 'cancelled';
export type TicketPriority = 'low' | 'medium' | 'high' | 'emergency';
export type NetworkType = 'fiber_optic' | 'lan' | 'wifi';

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
    network_type: NetworkType;
    sla_hours: number;
    status?: 'active' | 'inactive';
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
    category_id?: number | null;
    network_type?: NetworkType | null;
    title: string;
    location_details?: string | null;
    description?: string | null;
    priority?: TicketPriority | null;
    status: TicketStatus;
    resolution_note?: string | null;
    assigned_at?: string | null;
    cancelled_at?: string | null;
    due_at?: string | null;
    resolved_at?: string | null;
    closed_at?: string | null;
    rating?: number | null;
    feedback_comment?: string | null;
    rated_at?: string | null;
    created_at?: string;
    updated_at?: string;
    department?: Department | null;
    reporter?: User | null;
    assignee?: User | null;
    technicians?: User[];
    category?: TicketCategory | null;
    attachments?: TicketAttachment[];
    replies?: TicketReply[];
    status_histories?: TicketStatusHistory[];
}
