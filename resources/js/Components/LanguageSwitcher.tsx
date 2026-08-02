import { useLanguage } from '@/lib/i18n';
import type { Language } from '@/lib/i18n';

export default function LanguageSwitcher() {
    const { language, setLanguage, t } = useLanguage();

    return (
        <label className="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <span className="sr-only">{t('语言')}</span>
            <select
                value={language}
                onChange={(event) => setLanguage(event.target.value as Language)}
                aria-label={t('语言')}
                className="rounded-md border-gray-300 bg-white py-1.5 pl-2 pr-8 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
            >
                <option value="zh-CN">{t('中文')}</option>
                <option value="en">{t('英文')}</option>
            </select>
        </label>
    );
}
