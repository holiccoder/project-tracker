const badgeColors: Record<string, string> = {
    pending: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    confirmed: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200',
    in_progress:
        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    completed:
        'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-200',
    done: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200',
    accepted:
        'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-200',
    rejected: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-200',
    changes_requested:
        'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-200',
    open: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-200',
    resolved:
        'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-200',
    closed: 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
    normal: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    serious:
        'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-200',
    blocking: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-200',
    low: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    medium: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200',
    high: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-200',
    active: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-200',
    delivered: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200',
    paused: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    owner: 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-200',
};

export default function Badge({
    color,
    children,
}: {
    color: string;
    children: React.ReactNode;
}) {
    return (
        <span
            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${badgeColors[color] ?? badgeColors.pending}`}
        >
            {children}
        </span>
    );
}
