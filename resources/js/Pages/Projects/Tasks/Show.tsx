import Badge from '@/Components/Badge';
import FlashMessage from '@/Components/FlashMessage';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { TaskItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

export default function Show({
    project,
    task,
}: {
    project: { id: number; name: string; slug: string };
    task: TaskItem;
}) {
    const updateStatus = (action: 'accept' | 'request_changes') => {
        router.patch(
            route('tasks.status.update', task.id),
            { action },
            { preserveScroll: true },
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <Link
                        href={route('projects.show', project.slug)}
                        className="text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-300"
                    >
                        ←
                    </Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {task.title}
                    </h2>
                </div>
            }
        >
            <Head title={task.title} />

            <div className="py-12">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <FlashMessage />

                    <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge color={task.status}>
                                {task.status_label}
                            </Badge>
                            <Badge color={task.priority}>
                                {task.priority_label}优先级
                            </Badge>
                        </div>

                        <dl className="mt-6 space-y-3 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">
                                    所属项目
                                </dt>
                                <dd className="text-gray-900 dark:text-gray-100">
                                    {project.name}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">
                                    委派人
                                </dt>
                                <dd className="text-gray-900 dark:text-gray-100">
                                    {task.created_by?.name ?? '开发者'}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">
                                    期望完成
                                </dt>
                                <dd className="text-gray-900 dark:text-gray-100">
                                    {task.due_date ?? '—'}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">
                                    委派时间
                                </dt>
                                <dd className="text-gray-900 dark:text-gray-100">
                                    {task.created_at
                                        ? new Date(
                                              task.created_at,
                                          ).toLocaleString('zh-CN')
                                        : '—'}
                                </dd>
                            </div>
                            {task.completed_at && (
                                <div className="flex justify-between">
                                    <dt className="text-gray-500 dark:text-gray-400">
                                        完成时间
                                    </dt>
                                    <dd className="text-gray-900 dark:text-gray-100">
                                        {new Date(
                                            task.completed_at,
                                        ).toLocaleString('zh-CN')}
                                    </dd>
                                </div>
                            )}
                            {task.accepted_at && (
                                <div className="flex justify-between">
                                    <dt className="text-gray-500 dark:text-gray-400">
                                        验收时间
                                    </dt>
                                    <dd className="text-gray-900 dark:text-gray-100">
                                        {new Date(
                                            task.accepted_at,
                                        ).toLocaleString('zh-CN')}
                                    </dd>
                                </div>
                            )}
                        </dl>

                        {task.description && (
                            <div className="mt-6">
                                <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    任务描述
                                </h3>
                                <p className="mt-2 whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-300">
                                    {task.description}
                                </p>
                            </div>
                        )}

                        {task.reject_reason && (
                            <div className="mt-6 rounded-md bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900 dark:text-red-200">
                                <div className="font-semibold">拒绝原因</div>
                                <p className="mt-1 whitespace-pre-wrap">
                                    {task.reject_reason}
                                </p>
                            </div>
                        )}
                    </div>

                    {task.status === 'done' && (
                        <div className="mt-6 flex justify-end gap-3">
                            <button
                                type="button"
                                onClick={() => updateStatus('request_changes')}
                                className="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-orange-500"
                            >
                                要求返工
                            </button>
                            <button
                                type="button"
                                onClick={() => updateStatus('accept')}
                                className="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-green-500"
                            >
                                验收通过
                            </button>
                        </div>
                    )}

                    {task.status === 'changes_requested' && (
                        <div className="mt-6 rounded-md bg-orange-50 p-4 text-sm text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                            该任务已要求返工,等待开发者重新处理。
                        </div>
                    )}

                    {task.status === 'accepted' && (
                        <div className="mt-6 rounded-md bg-green-50 p-4 text-sm text-green-800 dark:bg-green-900 dark:text-green-200">
                            该任务已验收通过,感谢确认!
                        </div>
                    )}

                    {task.status === 'rejected' && (
                        <div className="mt-6 rounded-md bg-gray-100 p-4 text-sm text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                            该任务已被开发者拒绝,如需调整可重新委派任务。
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
