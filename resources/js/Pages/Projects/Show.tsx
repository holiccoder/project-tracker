import Badge from '@/Components/Badge';
import FlashMessage from '@/Components/FlashMessage';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useLanguage } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import {
    ContractItem,
    DevLogItem,
    IssueItem,
    PaymentItem,
    ProjectDetail,
    TaskItem,
    TaskStatus,
} from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { DragDropContext, Draggable, Droppable } from '@hello-pangea/dnd';
import { FormEvent, useState } from 'react';

type TabKey = 'tasks' | 'logs' | 'issues' | 'contracts' | 'info';

const tabs: { key: TabKey; label: string }[] = [
    { key: 'tasks', label: '任务' },
    { key: 'logs', label: '开发记录' },
    { key: 'issues', label: '问题' },
    { key: 'contracts', label: '合同' },
    { key: 'info', label: '项目信息' },
];

const taskGroupOrder: TaskStatus[] = [
    'pending',
    'confirmed',
    'in_progress',
    'changes_requested',
    'done',
    'accepted',
    'rejected',
];

function groupTasks(tasks: TaskItem[]): Record<string, TaskItem[]> {
    return taskGroupOrder.reduce<Record<string, TaskItem[]>>((acc, status) => {
        acc[status] = tasks.filter((task) => task.status === status);
        return acc;
    }, {});
}

function TaskCard({
    task,
    project,
}: {
    task: TaskItem;
    project: ProjectDetail;
}) {
    const { t } = useLanguage();

    return (
        <Link
            href={route('projects.tasks.show', {
                project: project.slug,
                task: task.id,
            })}
            className="block rounded-lg border border-gray-200 bg-white p-4 transition hover:border-indigo-300 hover:shadow-sm dark:border-gray-700 dark:bg-gray-800"
        >
            <div className="flex items-center justify-between gap-2">
                <div className="font-medium text-gray-900 dark:text-gray-100">
                    {task.title}
                </div>
                <div className="flex shrink-0 items-center gap-1.5">
                    <Badge color={task.priority}>{t(task.priority_label)}</Badge>
                    <Badge color={task.status}>{t(task.status_label)}</Badge>
                </div>
            </div>
            <div className="mt-1 flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                {task.due_date && <span>{t('期望完成')} {task.due_date}</span>}
                {task.created_by && <span>{t('委派人')}:{task.created_by.name}</span>}
            </div>
        </Link>
    );
}

function NewTaskForm({ project }: { project: ProjectDetail }) {
    const { t } = useLanguage();
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        title: '',
        description: '',
        priority: 'medium',
        attachments: [] as File[],
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('projects.tasks.store', project.slug), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    if (!open) {
        return (
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500"
            >
                {t('新建任务')}
            </button>
        );
    }

    const inputClass =
        'mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm';

    return (
        <form
            onSubmit={submit}
            className="w-full rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900"
        >
            <div className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {t('标题')}
                    </label>
                    <input
                        type="text"
                        value={data.title}
                        onChange={(e) => setData('title', e.target.value)}
                        className={inputClass}
                        required
                    />
                    {errors.title && (
                        <div className="mt-1 text-sm text-red-600">
                            {errors.title}
                        </div>
                    )}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {t('描述')}
                    </label>
                    <textarea
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        rows={5}
                        className={inputClass}
                    />
                    {errors.description && (
                        <div className="mt-1 text-sm text-red-600">
                            {errors.description}
                        </div>
                    )}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {t('优先级')}
                    </label>
                    <select
                        value={data.priority}
                        onChange={(e) => setData('priority', e.target.value)}
                        className={inputClass}
                    >
                        <option value="low">{t('低')}</option>
                        <option value="medium">{t('中')}</option>
                        <option value="high">{t('高')}</option>
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {t('附件（可多选）')}
                    </label>
                    <input
                        type="file"
                        multiple
                        onChange={(e) =>
                            setData(
                                'attachments',
                                e.target.files ? Array.from(e.target.files) : [],
                            )
                        }
                        className={`${inputClass} py-2`}
                    />
                    {data.attachments.length > 0 && (
                        <ul className="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                            {data.attachments.map((file, index) => (
                                <li key={index}>• {file.name}</li>
                            ))}
                        </ul>
                    )}
                    {(errors.attachments || (errors as Record<string, string>)['attachments.*']) && (
                        <div className="mt-1 text-sm text-red-600">
                            {errors.attachments || (errors as Record<string, string>)['attachments.*']}
                        </div>
                    )}
                </div>
            </div>
            <div className="mt-4 flex gap-2">
                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:opacity-50"
                >
                    {t('提交任务')}
                </button>
                <button
                    type="button"
                    onClick={() => setOpen(false)}
                    className="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                >
                    {t('取消')}
                </button>
            </div>
        </form>
    );
}

function TasksTab({
    project,
    tasks,
}: {
    project: ProjectDetail;
    tasks: TaskItem[];
}) {
    const { t } = useLanguage();
    const groups = groupTasks(tasks);

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-end gap-4">
                <NewTaskForm project={project} />
            </div>

            {tasks.length === 0 && (
                <div className="rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                    {t('暂无任务,点击右上角“新建任务”委派开发工作。')}
                </div>
            )}

            {tasks.length > 0 && (
                <div className="space-y-6">
                    {taskGroupOrder.map((status) => {
                        const group = groups[status];
                        if (group.length === 0) {
                            return null;
                        }

                        return (
                            <div key={status}>
                                <h3 className="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    <Badge color={status}>
                                        {t(group[0].status_label)}
                                    </Badge>
                                    <span className="text-gray-400">
                                        ({group.length})
                                    </span>
                                </h3>
                                <div className="space-y-2">
                                    {group.map((task) => (
                                        <TaskCard
                                            key={task.id}
                                            task={task}
                                            project={project}
                                        />
                                    ))}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

function TasksBoard({
    project,
    tasks,
}: {
    project: ProjectDetail;
    tasks: TaskItem[];
}) {
    const { t } = useLanguage();
    const groups = groupTasks(tasks);

    const handleDragEnd = (result: any) => {
        if (!result.destination) return;

        const taskId = parseInt(result.draggableId);
        const targetStatus = result.destination.droppableId as TaskStatus;
        const sourceStatus = result.source.droppableId as TaskStatus;

        if (sourceStatus === targetStatus) return;

        if (sourceStatus !== 'done') {
            alert(t('在客户端您仅有权限对【已完成】的任务进行【验收通过】或【要求返工】的拖拽流转。'));
            return;
        }

        if (targetStatus === 'accepted') {
            router.patch(route('tasks.status.update', taskId), { action: 'accept' }, { preserveScroll: true });
        } else if (targetStatus === 'changes_requested') {
            router.patch(route('tasks.status.update', taskId), { action: 'request_changes' }, { preserveScroll: true });
        } else {
            alert(t('不合法的流转状态目标'));
        }
    };

    return (
        <DragDropContext onDragEnd={handleDragEnd}>
            <div className="flex gap-4 overflow-x-auto pb-4">
                {taskGroupOrder.map((status) => {
                    const group = groups[status];
                    const label = t(matchStatusLabel(status));

                    return (
                        <Droppable key={status} droppableId={status}>
                            {(provided, snapshot) => (
                                <div
                                    ref={provided.innerRef}
                                    {...provided.droppableProps}
                                    className={`flex h-[550px] w-72 shrink-0 flex-col rounded-lg bg-gray-50 p-3 dark:bg-gray-900/50 border transition ${
                                        snapshot.isDraggingOver
                                            ? 'border-indigo-300 bg-indigo-50/20 dark:border-indigo-900/30'
                                            : 'border-gray-200 dark:border-gray-800'
                                    }`}
                                >
                                    <div className="mb-3 flex items-center justify-between">
                                        <Badge color={status}>{label}</Badge>
                                        <span className="text-xs text-gray-400 font-semibold">
                                            ({group.length})
                                        </span>
                                    </div>

                                    <div className="flex-1 space-y-2 overflow-y-auto pr-1">
                                        {group.map((task, index) => {
                                            const isDragDisabled = task.status !== 'done';

                                            return (
                                                <Draggable
                                                    key={task.id.toString()}
                                                    draggableId={task.id.toString()}
                                                    index={index}
                                                    isDragDisabled={isDragDisabled}
                                                >
                                                    {(provided, snapshot) => (
                                                        <div
                                                            ref={provided.innerRef}
                                                            {...provided.draggableProps}
                                                            {...provided.dragHandleProps}
                                                            className={`block rounded-lg border border-gray-200 bg-white p-4 transition hover:border-indigo-300 dark:border-gray-700 dark:bg-gray-800 ${
                                                                snapshot.isDragging ? 'shadow-lg border-indigo-400 ring-2 ring-indigo-200 dark:ring-indigo-900/30' : ''
                                                            }`}
                                                        >
                                                            <div>
                                                                <Link
                                                                    href={route('projects.tasks.show', {
                                                                        project: project.slug,
                                                                        task: task.id,
                                                                    })}
                                                                    className="font-medium text-gray-900 hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400 block text-sm"
                                                                >
                                                                    {task.title}
                                                                </Link>
                                                            </div>
                                                            <div className="mt-2 flex items-center justify-between gap-2 text-[10px] text-gray-500 dark:text-gray-400">
                                                                {task.due_date ? <span>{t('期望')} {task.due_date}</span> : <span>—</span>}
                                                                <Badge color={task.priority}>{t(task.priority_label)}</Badge>
                                                            </div>
                                                        </div>
                                                    )}
                                                </Draggable>
                                            );
                                        })}
                                        {provided.placeholder}
                                    </div>
                                </div>
                            )}
                        </Droppable>
                    );
                })}
            </div>
        </DragDropContext>
    );
}

function matchStatusLabel(status: TaskStatus): string {
    switch (status) {
        case 'pending': return '待确认';
        case 'confirmed': return '已确认';
        case 'in_progress': return '进行中';
        case 'done': return '已完成';
        case 'accepted': return '已验收';
        case 'rejected': return '已拒绝';
        case 'changes_requested': return '要求返工';
        default: return status;
    }
}

function LogsTab({ devLogs }: { devLogs: DevLogItem[] }) {
    const { t } = useLanguage();

    if (devLogs.length === 0) {
        return (
            <div className="rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                {t('暂无开发记录。')}
            </div>
        );
    }

    return (
        <ol className="relative space-y-6 border-l border-gray-200 ps-6 dark:border-gray-700">
            {devLogs.map((log) => (
                <li key={log.id} className="relative">
                    <span className="absolute -start-[1.85rem] top-1 h-3 w-3 rounded-full border-2 border-white bg-indigo-500 dark:border-gray-900" />
                    <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {log.date}
                            </span>
                            <div className="flex items-center gap-2">
                                <Badge color={log.status}>
                                    {t(log.status_label)}
                                </Badge>
                                <Badge color="info">
                                    {t(log.category_label)}
                                </Badge>
                            </div>
                        </div>
                        <p className="mt-1 whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-300">
                            {log.content}
                        </p>
                    </div>
                </li>
            ))}
        </ol>
    );
}

function IssuesTab({
    project,
    issues,
}: {
    project: ProjectDetail;
    issues: IssueItem[];
}) {
    const { t } = useLanguage();

    if (issues.length === 0) {
        return (
            <div className="rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                {t('暂无问题,一切顺利。')}
            </div>
        );
    }

    return (
        <div className="space-y-3">
            {issues.map((issue) => (
                <div
                    key={issue.id}
                    className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div className="flex items-center justify-between gap-2">
                        <div className="font-medium text-gray-900 dark:text-gray-100">
                            {issue.title}
                        </div>
                        <div className="flex shrink-0 items-center gap-1.5">
                            <Badge color={issue.severity}>
                                {t(issue.severity_label)}
                            </Badge>
                            <Badge color={issue.status}>
                                {t(issue.status_label)}
                            </Badge>
                        </div>
                    </div>
                    {issue.description && (
                        <p className="mt-1 whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-300">
                            {issue.description}
                        </p>
                    )}
                    {issue.has_attachment && (
                        <div className="mt-3 flex justify-end">
                            <a
                                href={route('projects.issues.attachment.download', {
                                    project: project.slug,
                                    issue: issue.id,
                                })}
                                className="rounded bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-600 transition hover:bg-indigo-100 dark:bg-indigo-950/30 dark:text-indigo-400 dark:hover:bg-indigo-950/50"
                            >
                                {t('下载附件/截图')}
                            </a>
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}

function ContractsTab({
    project,
    contracts,
}: {
    project: ProjectDetail;
    contracts: ContractItem[];
}) {
    const { t } = useLanguage();

    if (contracts.length === 0) {
        return (
            <div className="rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                {t('暂无合同文件。')}
            </div>
        );
    }

    return (
        <div className="space-y-2">
            {contracts.map((contract) => (
                <div
                    key={contract.id}
                    className="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div>
                        <div className="font-medium text-gray-900 dark:text-gray-100">
                            {contract.name}
                        </div>
                        {contract.created_at && (
                            <div className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {t('上传于')}{' '}
                                {new Date(
                                    contract.created_at,
                                ).toLocaleDateString('zh-CN')}
                            </div>
                        )}
                    </div>
                    <a
                        href={route('projects.contracts.download', {
                            project: project.slug,
                            contract: contract.id,
                        })}
                        className="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-indigo-500"
                    >
                        {t('下载')}
                    </a>
                </div>
            ))}
        </div>
    );
}

function InfoTab({ project, payments }: { project: ProjectDetail; payments: PaymentItem[] }) {
    const { auth } = usePage().props;
    const { t } = useLanguage();

    const rows: { label: string; value: React.ReactNode }[] = [
        {
            label: t('项目状态'),
            value: <Badge color={project.status}>{t(project.status_label)}</Badge>,
        },
        { label: t('项目总额'), value: formatMoney(project.amount) },
        { label: t('已付金额'), value: formatMoney(project.paid_amount) },
        {
            label: t('未付金额'),
            value: (
                <span className="font-medium text-green-700 dark:text-green-300">
                    {formatMoney(project.unpaid_amount)}
                </span>
            ),
        },
        { label: t('截止日期'), value: project.deadline ?? '—' },
        {
            label: t('仓库地址'),
            value: project.repo_url ? (
                <a
                    href={project.repo_url}
                    target="_blank"
                    rel="noreferrer"
                    className="text-indigo-600 underline dark:text-indigo-400"
                >
                    {project.repo_url}
                </a>
            ) : (
                '—'
            ),
        },
        {
            label: t('项目成员'),
            value: (
                <div className="space-y-1">
                    {project.members.map((member) => (
                        <div key={member.id}>
                            {member.name}
                            {member.id === auth.user.id && (
                                <span className="ms-1 text-xs text-gray-400">
                                    ({t('你')})
                                </span>
                            )}
                        </div>
                    ))}
                </div>
            ),
        },
    ];
    const visibleRows = project.can_view_price
        ? rows
        : [rows[0], ...rows.slice(4)];

    return (
        <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            {project.description && (
                <div className="mb-6">
                    <div className="text-sm font-semibold text-gray-700 dark:text-gray-300">
                        {t('项目描述')}
                    </div>
                    <p className="mt-1 whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-300">
                        {project.description}
                    </p>
                </div>
            )}

            <dl className="divide-y divide-gray-100 dark:divide-gray-700">
                {visibleRows.map((row) => (
                    <div
                        key={row.label}
                        className="flex items-center justify-between py-3"
                    >
                        <dt className="text-sm text-gray-500 dark:text-gray-400">
                            {row.label}
                        </dt>
                        <dd className="text-sm text-gray-900 dark:text-gray-100">
                            {row.value}
                        </dd>
                    </div>
                ))}
            </dl>

            {/* Payments List */}
            {payments && payments.length > 0 && (
                <div className="mt-8 border-t border-gray-100 pt-6 dark:border-gray-700">
                    <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300">
                        {t('收款明细记录')}
                    </h3>
                    <div className="mt-3 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead className="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        {t('付款日期')}
                                    </th>
                                    <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        {t('金额')}
                                    </th>
                                    <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        {t('备注说明')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                {payments.map((payment) => (
                                    <tr key={payment.id} className="text-sm text-gray-900 dark:text-gray-100">
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            {payment.date}
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap font-medium text-indigo-600 dark:text-indigo-400">
                                            {formatMoney(payment.amount)}
                                        </td>
                                        <td className="px-4 py-3 whitespace-normal">
                                            {payment.remark ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </div>
    );
}

export default function Show({
    project,
    tasks,
    dev_logs,
    issues,
    contracts,
    payments,
}: {
    project: ProjectDetail;
    tasks: TaskItem[];
    dev_logs: DevLogItem[];
    issues: IssueItem[];
    contracts: ContractItem[];
    payments: PaymentItem[];
}) {
    const { t } = useLanguage();
    const [activeTab, setActiveTab] = useState<TabKey>('tasks');

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {project.name}
                    </h2>
                    <Badge color={project.status}>{t(project.status_label)}</Badge>
                </div>
            }
        >
            <Head title={`${t('项目')} - ${project.name}`} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <FlashMessage />

                    <div className="mb-6 border-b border-gray-200 dark:border-gray-700">
                        <nav className="-mb-px flex space-x-6 overflow-x-auto">
                            {tabs.map((tab) => (
                                <button
                                    key={tab.key}
                                    type="button"
                                    onClick={() => setActiveTab(tab.key)}
                                    className={`whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition ${
                                        activeTab === tab.key
                                            ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-300'
                                    }`}
                                >
                                    {t(tab.label)}
                                </button>
                            ))}
                        </nav>
                    </div>

                    {activeTab === 'tasks' && (
                        <TasksTab project={project} tasks={tasks} />
                    )}
                    {activeTab === 'logs' && <LogsTab devLogs={dev_logs} />}
                    {activeTab === 'issues' && <IssuesTab project={project} issues={issues} />}
                    {activeTab === 'contracts' && (
                        <ContractsTab project={project} contracts={contracts} />
                    )}
                    {activeTab === 'info' && <InfoTab project={project} payments={payments} />}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
