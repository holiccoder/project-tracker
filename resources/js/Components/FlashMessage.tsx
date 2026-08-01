import { Flash } from '@/types';
import { usePage } from '@inertiajs/react';

export default function FlashMessage() {
    const flash = usePage().props.flash as Flash;

    if (!flash.success && !flash.error) {
        return null;
    }

    return (
        <div className="mb-6">
            {flash.success && (
                <div className="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800 dark:bg-green-900 dark:text-green-200">
                    {flash.success}
                </div>
            )}
            {flash.error && (
                <div className="rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 dark:bg-red-900 dark:text-red-200">
                    {flash.error}
                </div>
            )}
        </div>
    );
}
