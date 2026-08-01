import Badge from '@/Components/Badge';
import FlashMessage from '@/Components/FlashMessage';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatMoney } from '@/lib/money';
import {
    ContractItem,
    DevLogItem,
    IssueItem,
    ProjectDetail,
    TaskItem,
    TaskStatus,
} from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
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
                    <Badge color={task.priority}>{task.priority_label}</Badge>
                    <Badge color={task.status}>{task.status_label}</Badge>
                </div>
            </div>
            <div className="mt-1 flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                {task.due_date && <span>期望完成 {task.due_date}</span>}
                {task.created_by && <span>委派人:{task.created_by.name}</span>}
            </div>
        </Link>
    );
}

function NewTaskForm({ project }: { project: ProjectDetail }) {
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        title: '',
        description: '',
        priority: 'medium',
        due_date: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('projects.tasks.store', project.slug), {
            preserveScroll: true,
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
                新建任务
            </button>
        );
    }

    const inputClass =
        'mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm';

    return (
        <form
            onSubmit={submit}
            className="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900"
        >
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div className="sm:col-span-2">
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        标题
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
                <div className="sm:col-span-2">
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        描述
                    </label>
                    <textarea
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        rows={3}
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
                        优先级
                    </label>
                    <select
                        value={data.priority}
                        onChange={(e) => setData('priority', e.target.value)}
                        className={inputClass}
                    >
                        <option value="low">低</option>
                        <option value="medium">中</option>
                        <option value="high">高</option>
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        期望完成时间
                    </label>
                    <input
                        type="date"
                        value={data.due_date}
                        onChange={(e) => setData('due_date', e.target.value)}
                        className={inputClass}
                    />
                    {errors.due_date && (
                        <div className="mt-1 text-sm text-red-600">
                            {errors.due_date}
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
                    提交任务
                </button>
                <button
                    type="button"
                    onClick={() => setOpen(false)}
                    className="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                >
                    取消
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
    const groups = groupTasks(tasks);

    return (
        <div className="space-y-6">
            <div className="flex justify-end">
                <NewTaskForm project={project} />
            </div>

            {tasks.length === 0 && (
                <div className="rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                    暂无任务,点击右上角「新建任务」委派开发工作。
                </div>
            )}

            {taskGroupOrder.map((status) => {
                const group = groups[status];
                if (group.length === 0) {
                    return null;
                }

                return (
                    <div key={status}>
                        <h3 className="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                            <Badge color={status}>
                                {group[0].status_label}
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
    );
}

function LogsTab({ devLogs }: { devLogs: DevLogItem[] }) {
    if (devLogs.length === 0) {
        return (
            <div className="rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                暂无开发记录。
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
                            {log.hours_spent !== null && (
                                <Badge color="in_progress">
                                    工时 {log.hours_spent}h
                                </Badge>
                            )}
                        </div>
                        <p className="mt-1 whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-300">
                            {log.content}
                        </p>
                        {log.task && (
                            <div className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                关联任务:{log.task.title}
                            </div>
                        )}
                    </div>
                </li>
            ))}
        </ol>
    );
}

function IssuesTab({ issues }: { issues: IssueItem[] }) {
    if (issues.length === 0) {
        return (
            <div className="rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                暂无问题,一切顺利。
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
                                {issue.severity_label}
                            </Badge>
                            <Badge color={issue.status}>
                                {issue.status_label}
                            </Badge>
                        </div>
                    </div>
                    {issue.description && (
                        <p className="mt-1 whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-300">
                            {issue.description}
                        </p>
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
    if (contracts.length === 0) {
        return (
            <div className="rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                暂无合同文件。
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
                                上传于{' '}
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
                        下载
                    </a>
                </div>
            ))}
        </div>
    );
}

function InfoTab({ project }: { project: ProjectDetail }) {
    const { auth } = usePage().props;

    const rows: { label: string; value: React.ReactNode }[] = [
        {
            label: '项目状态',
            value: <Badge color={project.status}>{project.status_label}</Badge>,
        },
        { label: '项目总额', value: formatMoney(project.amount) },
        { label: '已付金额', value: formatMoney(project.paid_amount) },
        {
            label: '未付金额',
            value: (
                <span className="font-medium text-green-700 dark:text-green-300">
                    {formatMoney(project.unpaid_amount)}
                </span>
            ),
        },
        { label: '截止日期', value: project.deadline ?? '—' },
        {
            label: '仓库地址',
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
            label: '项目成员',
            value: (
                <div className="space-y-1">
                    {project.members.map((member) => (
                        <div key={member.id}>
                            {member.name}
                            {member.id === auth.user.id && (
                                <span className="ms-1 text-xs text-gray-400">
                                    (你)
                                </span>
                            )}
                        </div>
                    ))}
                </div>
            ),
        },
    ];

    return (
        <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            {project.description && (
                <div className="mb-6">
                    <div className="text-sm font-semibold text-gray-700 dark:text-gray-300">
                        项目描述
                    </div>
                    <p className="mt-1 whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-300">
                        {project.description}
                    </p>
                </div>
            )}

            <dl className="divide-y divide-gray-100 dark:divide-gray-700">
                {rows.map((row) => (
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
        </div>
    );
}

export default function Show({
    project,
    tasks,
    dev_logs,
    issues,
    contracts,
}: {
    project: ProjectDetail;
    tasks: TaskItem[];
    dev_logs: DevLogItem[];
    issues: IssueItem[];
    contracts: ContractItem[];
}) {
    const [activeTab, setActiveTab] = useState<TabKey>('tasks');

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {project.name}
                    </h2>
                    <Badge color={project.status}>{project.status_label}</Badge>
                </div>
            }
        >
            <Head title={project.name} />

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
                                    {tab.label}
                                </button>
                            ))}
                        </nav>
                    </div>

                    {activeTab === 'tasks' && (
                        <TasksTab project={project} tasks={tasks} />
                    )}
                    {activeTab === 'logs' && <LogsTab devLogs={dev_logs} />}
                    {activeTab === 'issues' && <IssuesTab issues={issues} />}
                    {activeTab === 'contracts' && (
                        <ContractsTab project={project} contracts={contracts} />
                    )}
                    {activeTab === 'info' && <InfoTab project={project} />}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
