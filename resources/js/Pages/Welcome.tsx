import ApplicationLogo from '@/Components/ApplicationLogo';
import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { PageProps } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { useLanguage } from '@/lib/i18n';

export default function Welcome({
    auth,
    status,
}: PageProps<{ status?: string }>) {
    const { t } = useLanguage();

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <>
            <Head title={t('项目实施与反馈系统')} />
            <div className="relative flex min-h-screen flex-col items-center justify-center bg-gray-50 dark:bg-gray-900 selection:bg-indigo-500 selection:text-white px-6 py-12">
                <div className="absolute right-4 top-4 z-20">
                    <LanguageSwitcher />
                </div>

                {/* Background Pattern */}
                <div className="absolute inset-0 bg-grid-slate-50 [mask-image:linear-gradient(0deg,#fff,rgba(255,255,255,0.6))] dark:bg-grid-slate-900/20 dark:[mask-image:linear-gradient(0deg,rgba(255,255,255,0.1),rgba(255,255,255,0.05))] pointer-events-none" />

                <div className="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700/50 p-8 sm:p-10 z-10">

                    <div className="flex flex-col items-center mb-8">
                        <div className="h-16 w-16 bg-indigo-500/10 dark:bg-indigo-500/20 rounded-2xl flex items-center justify-center mb-4 text-indigo-600 dark:text-indigo-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className="w-8 h-8">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h18" />
                            </svg>
                        </div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white text-center">
                            {t('项目实施与反馈系统')}
                        </h1>
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400 text-center">
                            {t('高效、透明的外包项目交付与协作平台')}
                        </p>
                    </div>

                    {status && (
                        <div className="mb-6 text-sm font-medium text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-500/10 p-4 rounded-lg">
                            {status}
                        </div>
                    )}

                    {auth.user ? (
                        <div className="text-center">
                            <div className="mb-6 bg-indigo-50 dark:bg-indigo-500/10 p-4 rounded-lg border border-indigo-100 dark:border-indigo-500/20">
                                <p className="text-sm text-gray-600 dark:text-gray-300">
                                    {t('欢迎回来，')}<span className="font-semibold text-indigo-600 dark:text-indigo-400">{auth.user.name}</span>
                                </p>
                                <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {t('您目前已登录系统')}
                                </p>
                            </div>

                            <div className="space-y-3">
                                <Link
                                    href={route('dashboard')}
                                    className="block w-full text-center bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2.5 px-4 rounded-lg shadow-sm transition-colors duration-200"
                                >
                                    {t('进入项目控制台')}
                                </Link>
                                <Link
                                    href={route('logout')}
                                    method="post"
                                    as="button"
                                    className="block w-full text-center text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 py-2 hover:underline"
                                >
                                    {t('退出登录')}
                                </Link>
                            </div>
                        </div>
                    ) : (
                        <form onSubmit={submit} className="space-y-6">
                            <div>
                                <InputLabel htmlFor="email" value={t('电子邮箱')} />
                                <TextInput
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    className="mt-1 block w-full"
                                    autoComplete="username"
                                    isFocused={true}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder={t('请输入您的邮箱地址')}
                                />
                                <InputError message={errors.email} className="mt-2" />
                            </div>

                            <div>
                                <InputLabel htmlFor="password" value={t('登录密码')} />
                                <TextInput
                                    id="password"
                                    type="password"
                                    name="password"
                                    value={data.password}
                                    className="mt-1 block w-full"
                                    autoComplete="current-password"
                                    onChange={(e) => setData('password', e.target.value)}
                                    placeholder={t('请输入密码')}
                                />
                                <InputError message={errors.password} className="mt-2" />
                            </div>

                            <div className="flex items-center justify-between">
                                <label className="flex items-center cursor-pointer select-none">
                                    <Checkbox
                                        name="remember"
                                        checked={data.remember}
                                        onChange={(e) =>
                                            setData(
                                                'remember',
                                                (e.target.checked || false) as false,
                                            )
                                        }
                                    />
                                    <span className="ms-2 text-sm text-gray-600 dark:text-gray-400">
                                        {t('记住我')}
                                    </span>
                                </label>

                                <Link
                                    href={route('password.request')}
                                    className="text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                                >
                                    {t('忘记密码？')}
                                </Link>
                            </div>

                            <div>
                                <PrimaryButton className="w-full justify-center py-2.5 shadow-sm bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg" disabled={processing}>
                                    {t('立即登录')}
                                </PrimaryButton>
                            </div>
                        </form>
                    )}
                </div>

                {/* Footer */}
                <div className="mt-8 text-center text-xs text-gray-400 dark:text-gray-500 z-10">
                    &copy; {new Date().getFullYear()} {t('项目实施与反馈系统')}。{t('版权所有。')}
                </div>
            </div>
        </>
    );
}
