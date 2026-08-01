export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export interface Member {
    id: number;
    name: string;
}

export type ProjectStatus = 'active' | 'delivered' | 'paused';
export type TaskPriority = 'low' | 'medium' | 'high';
export type TaskStatus =
    | 'pending'
    | 'confirmed'
    | 'in_progress'
    | 'done'
    | 'accepted'
    | 'rejected'
    | 'changes_requested';
export type IssueSeverity = 'normal' | 'serious' | 'blocking';
export type IssueStatus = 'open' | 'in_progress' | 'resolved' | 'closed';

export interface ProjectSummary {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    status: ProjectStatus;
    status_label: string;
    amount: string | null;
    paid_amount: string;
    unpaid_amount: string | null;
    deadline: string | null;
    repo_url: string | null;
    tasks_total: number;
    tasks_done: number;
    role: 'owner' | 'member' | null;
    last_log?: { date: string; content: string } | null;
}

export interface ProjectDetail extends ProjectSummary {
    members: Member[];
}

export interface TaskItem {
    id: number;
    title: string;
    description: string | null;
    priority: TaskPriority;
    priority_label: string;
    status: TaskStatus;
    status_label: string;
    due_date: string | null;
    reject_reason: string | null;
    created_by: Member | null;
    completed_at: string | null;
    accepted_at: string | null;
    created_at: string | null;
}

export interface DevLogItem {
    id: number;
    date: string;
    content: string;
    hours_spent: string | null;
    task: { id: number; title: string } | null;
}

export interface IssueItem {
    id: number;
    title: string;
    description: string | null;
    severity: IssueSeverity;
    severity_label: string;
    status: IssueStatus;
    status_label: string;
    resolved_at: string | null;
    created_at: string | null;
}

export interface ContractItem {
    id: number;
    name: string;
    created_at: string | null;
}

export interface Flash {
    success?: string;
    error?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    flash: Flash;
};
