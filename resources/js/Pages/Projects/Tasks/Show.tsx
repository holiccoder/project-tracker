import Badge from '@/Components/Badge';
import FlashMessage from '@/Components/FlashMessage';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useLanguage } from '@/lib/i18n';
import { CommentItem, TaskItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';

export default function Show({
    project,
    task,
}: {
    project: { id: number; name: string; slug: string };
    task: TaskItem;
}) {
    const { t } = useLanguage();

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
                                {t(task.status_label)}
                            </Badge>
                            <Badge color={task.priority}>
                                {t(task.priority_label)}
                            </Badge>
                        </div>

                        <dl className="mt-6 space-y-3 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">
                                    {t('所属项目')}
                                </dt>
                                <dd className="text-gray-900 dark:text-gray-100">
                                    {project.name}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">
                                    {t('委派人')}
                                </dt>
                                <dd className="text-gray-900 dark:text-gray-100">
                                    {task.created_by?.name ?? t('开发者')}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">
                                    {t('期望完成')}
                                </dt>
                                <dd className="text-gray-900 dark:text-gray-100">
                                    {task.due_date ?? '—'}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">
                                    {t('委派时间')}
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
                                    {t('完成时间')}
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
                                    {t('验收时间')}
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
                                    {t('任务描述')}
                                </h3>
                                <p className="mt-2 whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-300">
                                    {task.description}
                                </p>
                            </div>
                        )}

                        {task.reject_reason && (
                            <div className="mt-6 rounded-md bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900 dark:text-red-200">
                                <div className="font-semibold">{t('拒绝原因')}</div>
                                <p className="mt-1 whitespace-pre-wrap">
                                    {task.reject_reason}
                                </p>
                            </div>
                        )}

                        {task.attachments && task.attachments.length > 0 && (
                            <div className="mt-6">
                                <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    {t('附件')}
                                </h3>
                                <ul className="mt-2 space-y-2">
                                    {task.attachments.map((attachment, index) => (
                                        <li key={index}>
                                            <a
                                                href={attachment.url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="inline-flex items-center rounded-md bg-indigo-50 px-3 py-1.5 text-sm font-semibold text-indigo-600 transition hover:bg-indigo-100 dark:bg-indigo-950/30 dark:text-indigo-400 dark:hover:bg-indigo-950/50"
                                            >
                                                {attachment.name}
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <CommentsSection task={task} />
                    </div>

                    {task.status === 'done' && (
                        <div className="mt-6 flex justify-end gap-3">
                            <button
                                type="button"
                                onClick={() => updateStatus('request_changes')}
                                className="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-orange-500"
                            >
                                {t('要求返工')}
                            </button>
                            <button
                                type="button"
                                onClick={() => updateStatus('accept')}
                                className="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-green-500"
                            >
                                {t('验收通过')}
                            </button>
                        </div>
                    )}

                    {task.status === 'changes_requested' && (
                        <div className="mt-6 rounded-md bg-orange-50 p-4 text-sm text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                            {t('该任务已要求返工,等待开发者重新处理。')}
                        </div>
                    )}

                    {task.status === 'accepted' && (
                        <div className="mt-6 rounded-md bg-green-50 p-4 text-sm text-green-800 dark:bg-green-900 dark:text-green-200">
                            {t('该任务已验收通过,感谢确认!')}
                        </div>
                    )}

                    {task.status === 'rejected' && (
                        <div className="mt-6 rounded-md bg-gray-100 p-4 text-sm text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                            {t('该任务已被开发者拒绝,如需调整可重新委派任务。')}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function CommentsSection({ task }: { task: TaskItem }) {
    const { t } = useLanguage();
    const { data, setData, post, processing, reset, errors } = useForm({
        body: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('tasks.comments.store', task.id), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <div className="mt-8 border-t border-gray-200 pt-6 dark:border-gray-700">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                {t('讨论评论')} ({task.comments?.length ?? 0})
            </h3>

            {/* Comments List */}
            <div className="mt-4 space-y-4 max-h-96 overflow-y-auto pr-2">
                {(!task.comments || task.comments.length === 0) && (
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {t('暂无讨论评论,写下你的第一条反馈吧。')}
                    </p>
                )}

                {task.comments?.map((comment: CommentItem) => (
                    <div
                        key={comment.id}
                        className={`rounded-lg p-4 text-sm ${
                            comment.author?.is_admin
                                ? 'bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/30'
                                : 'bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-800/50'
                        }`}
                    >
                        <div className="flex items-center justify-between">
                            <span className="font-semibold text-gray-900 dark:text-gray-100">
                                {comment.author ? (
                                    <>
                                        {comment.author.name}
                                        {comment.author.is_admin && (
                                            <span className="ml-1.5 rounded bg-indigo-100 px-1.5 py-0.5 text-xs font-normal text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300">
                                                {t('开发者')}
                                            </span>
                                        )}
                                    </>
                                ) : (
                                    <span className="text-gray-400">{t('已注销用户')}</span>
                                )}
                            </span>
                            <span className="text-xs text-gray-500 dark:text-gray-400">
                                {new Date(comment.created_at).toLocaleString('zh-CN')}
                            </span>
                        </div>
                        <p className="mt-2 whitespace-pre-wrap text-gray-700 dark:text-gray-300 font-sans">
                            {comment.body}
                        </p>
                    </div>
                ))}
            </div>

            {/* Comment Form */}
            <form onSubmit={submit} className="mt-6">
                <div>
                    <label htmlFor="body" className="sr-only">
                        {t('发表评论')}
                    </label>
                    <textarea
                        id="body"
                        rows={3}
                        value={data.body}
                        onChange={(e) => setData('body', e.target.value)}
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm"
                        placeholder={t('发表你的看法...')}
                        required
                    />
                    {errors.body && (
                        <p className="mt-1 text-xs text-red-600">
                            {errors.body}
                        </p>
                    )}
                </div>
                <div className="mt-2 flex justify-end">
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:opacity-50"
                    >
                        {t('提交评论')}
                    </button>
                </div>
            </form>
        </div>
    );
}
