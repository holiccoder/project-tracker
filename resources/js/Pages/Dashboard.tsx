import Badge from '@/Components/Badge';
import FlashMessage from '@/Components/FlashMessage';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatMoney } from '@/lib/money';
import { ProjectSummary } from '@/types';
import { Head, Link } from '@inertiajs/react';

function ProjectCard({ project }: { project: ProjectSummary }) {
    const progress =
        project.tasks_total > 0
            ? Math.round((project.tasks_done / project.tasks_total) * 100)
            : 0;

    return (
        <Link
            href={route('projects.show', project.slug)}
            className="block overflow-hidden rounded-lg bg-white shadow-sm transition hover:shadow-md dark:bg-gray-800"
        >
            <div className="p-6">
                <div className="flex items-start justify-between">
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {project.name}
                    </h3>
                    <Badge color={project.status}>{project.status_label}</Badge>
                </div>

                {project.description && (
                    <p className="mt-2 line-clamp-2 text-sm text-gray-500 dark:text-gray-400">
                        {project.description}
                    </p>
                )}

                <div className="mt-4 grid grid-cols-3 gap-4 text-sm">
                    <div>
                        <div className="text-xs text-gray-500 dark:text-gray-400">
                            总额
                        </div>
                        <div className="font-medium text-gray-900 dark:text-gray-100">
                            {formatMoney(project.amount)}
                        </div>
                    </div>
                    <div>
                        <div className="text-xs text-gray-500 dark:text-gray-400">
                            已付
                        </div>
                        <div className="font-medium text-gray-900 dark:text-gray-100">
                            {formatMoney(project.paid_amount)}
                        </div>
                    </div>
                    <div>
                        <div className="text-xs text-gray-500 dark:text-gray-400">
                            未付
                        </div>
                        <div className="font-medium text-green-700 dark:text-green-300">
                            {formatMoney(project.unpaid_amount)}
                        </div>
                    </div>
                </div>

                <div className="mt-4">
                    <div className="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span>任务进度</span>
                        <span>
                            {project.tasks_done}/{project.tasks_total}
                        </span>
                    </div>
                    <div className="mt-1 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div
                            className="h-full rounded-full bg-indigo-500"
                            style={{ width: `${progress}%` }}
                        />
                    </div>
                </div>

                <div className="mt-4 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                    <span>
                        {project.deadline
                            ? `截止 ${project.deadline}`
                            : '无截止日期'}
                    </span>
                    {project.last_log && (
                        <span title={project.last_log.content}>
                            最近动态 {project.last_log.date}
                        </span>
                    )}
                </div>
            </div>
        </Link>
    );
}

export default function Dashboard({
    projects,
}: {
    projects: ProjectSummary[];
}) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <FlashMessage />

                    {projects.length === 0 ? (
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                            <div className="p-12 text-center text-gray-900 dark:text-gray-100">
                                <div className="text-4xl">📋</div>
                                <div className="mt-4 text-lg font-medium">
                                    你还没有项目
                                </div>
                                <div className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    等待开发者为你分配项目,项目分配后将显示在这里。
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {projects.map((project) => (
                                <ProjectCard
                                    key={project.id}
                                    project={project}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
