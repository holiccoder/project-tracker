import { createContext, PropsWithChildren, useContext, useEffect, useMemo, useState } from 'react';

export type Language = 'zh-CN' | 'en';

const translations: Record<string, string> = {
    控制台: 'Dashboard',
    个人主页: 'Profile',
    退出登录: 'Log out',
    语言: 'Language',
    中文: 'Chinese',
    英文: 'English',
    登录: 'Log in',
    注册: 'Register',
    邮箱: 'Email',
    姓名: 'Name',
    密码: 'Password',
    确认密码: 'Confirm password',
    记住我: 'Remember me',
    '忘记密码？': 'Forgot your password?',
    '已有账号？': 'Already registered?',
    忘记密码: 'Forgot password',
    重置密码: 'Reset password',
    发送密码重置链接: 'Email password reset link',
    确认: 'Confirm',
    邮箱验证: 'Email verification',
    重新发送验证邮件: 'Resend verification email',
    个人资料: 'Profile',
    个人资料信息: 'Profile information',
    修改密码: 'Update password',
    删除账户: 'Delete account',
    保存: 'Save',
    已保存: 'Saved.',
    取消: 'Cancel',
    当前密码: 'Current password',
    新密码: 'New password',
    项目实施与反馈系统: 'Project Delivery & Feedback System',
    总额: 'Total',
    已付: 'Paid',
    未付: 'Unpaid',
    任务进度: 'Task progress',
    截止: 'Due',
    无截止日期: 'No deadline',
    最近动态: 'Latest update',
    你还没有项目: 'You do not have any projects yet',
    '等待开发者为你分配项目,项目分配后将显示在这里。': 'Projects assigned to you will appear here.',
    项目状态: 'Project status',
    项目总额: 'Project total',
    已付金额: 'Paid amount',
    未付金额: 'Unpaid amount',
    截止日期: 'Deadline',
    仓库地址: 'Repository',
    项目成员: 'Project members',
    项目描述: 'Project description',
    项目: 'Project',
    任务: 'Tasks',
    开发记录: 'Development logs',
    问题: 'Issues',
    合同: 'Contracts',
    项目信息: 'Project info',
    新建任务: 'New task',
    列表视图: 'List view',
    看板视图: 'Board view',
    标题: 'Title',
    描述: 'Description',
    优先级: 'Priority',
    低: 'Low',
    中: 'Medium',
    高: 'High',
    低优先级: 'Low priority',
    中优先级: 'Medium priority',
    高优先级: 'High priority',
    期望完成时间: 'Due date',
    期望: 'Due',
    '附件（可多选）': 'Attachments (multiple allowed)',
    提交任务: 'Submit task',
    '暂无任务,点击右上角“新建任务”委派开发工作。': 'No tasks yet. Click “New task” to assign work.',
    '暂无开发记录。': 'No development logs yet.',
    工时: 'Hours',
    待完成: 'Pending',
    进行中: 'In progress',
    已完成: 'Completed',
    已交付: 'Delivered',
    已暂停: 'Paused',
    所属项目: 'Project',
    委派人: 'Assigned by',
    期望完成: 'Due date',
    委派时间: 'Assigned at',
    完成时间: 'Completed at',
    验收时间: 'Accepted at',
    任务描述: 'Task description',
    拒绝原因: 'Rejection reason',
    附件: 'Attachments',
    要求返工: 'Request changes',
    验收通过: 'Accept',
    开发者: 'Developer',
    '暂无问题,一切顺利。': 'No issues. Everything is going smoothly.',
    下载附件: 'Download attachment',
    '下载附件/截图': 'Download attachment/screenshot',
    '暂无合同文件。': 'No contract files.',
    下载: 'Download',
    上传于: 'Uploaded',
    收款明细记录: 'Payment details',
    付款日期: 'Payment date',
    金额: 'Amount',
    备注说明: 'Remark',
    你: 'you',
    '在客户端您仅有权限对【已完成】的任务进行【验收通过】或【要求返工】的拖拽流转。': 'You can only drag completed tasks to accept them or request changes.',
    不合法的流转状态目标: 'Invalid workflow target.',
    待确认: 'Pending confirmation',
    已确认: 'Confirmed',
    已验收: 'Accepted',
    已拒绝: 'Rejected',
    一般: 'Normal',
    严重: 'Serious',
    阻塞: 'Blocking',
    待处理: 'Open',
    处理中: 'In progress',
    已解决: 'Resolved',
    已关闭: 'Closed',
    Agent独立完成: 'Completed independently by Agent',
    需要有人和Agent共同完成: 'Requires collaboration between a person and Agent',
    '该任务已要求返工,等待开发者重新处理。': 'Changes were requested for this task. Waiting for the developer to update it.',
    '该任务已验收通过,感谢确认!': 'This task has been accepted. Thank you for confirming!',
    '该任务已被开发者拒绝,如需调整可重新委派任务。': 'This task was rejected by the developer. Reassign it if adjustments are needed.',
    '讨论评论': 'Discussion',
    '暂无讨论评论,写下你的第一条反馈吧。': 'No comments yet. Write the first piece of feedback.',
    发表评论: 'Post a comment',
    '发表你的看法...': 'Share your thoughts...',
    提交评论: 'Post comment',
    已注销用户: 'Deleted user',
    '更新您的账户资料和邮箱地址。': 'Update your account profile and email address.',
    '您的邮箱尚未验证。': 'Your email address is not verified.',
    '点击此处重新发送验证邮件。': 'Click here to resend the verification email.',
    '新的验证链接已发送到您的邮箱。': 'A new verification link has been sent to your email.',
    '修改您的账户密码。': 'Update your account password.',
    '请使用长度足够且随机性较高的密码，以确保账户安全。': 'Use a long, random password to keep your account secure.',
    '删除账户后，您的所有资源和数据都将被永久删除。删除前，请先下载需要保留的数据或信息。': 'Once your account is deleted, all resources and data will be permanently deleted. Download anything you want to keep first.',
    '确定要删除账户吗？': 'Are you sure you want to delete your account?',
    '删除账户后，您的所有资源和数据都将被永久删除。请输入密码确认永久删除账户。': 'Once your account is deleted, all resources and data will be permanently deleted. Enter your password to confirm.',
    '忘记密码了吗？请输入您的邮箱地址，我们会向您发送密码重置链接。': 'Forgot your password? Enter your email address and we will send you a password reset link.',
    '这是应用的安全区域，请先确认密码后继续。': 'This is a secure area of the application. Please confirm your password before continuing.',
    '感谢您的注册！开始使用前，请点击我们发送到您邮箱的链接完成验证。如果没有收到邮件，可以重新发送。': 'Thank you for registering! Before getting started, click the link we sent to your email. You can request another email if you did not receive it.',
    '新的验证链接已发送到您注册时使用的邮箱。': 'A new verification link has been sent to the email address you registered with.',
    '高效、透明的外包项目交付与协作平台': 'An efficient and transparent platform for outsourced project delivery and collaboration',
    '欢迎回来，': 'Welcome back, ',
    您目前已登录系统: 'You are currently signed in',
    进入项目控制台: 'Open project dashboard',
    电子邮箱: 'Email address',
    登录密码: 'Login password',
    请输入您的邮箱地址: 'Enter your email address',
    请输入密码: 'Enter your password',
    立即登录: 'Log in now',
    '版权所有。': 'All rights reserved.',
};

type LanguageContextValue = {
    language: Language;
    setLanguage: (language: Language) => void;
    t: (text: string) => string;
};

const LanguageContext = createContext<LanguageContextValue | null>(null);
const storageKey = 'project-tracker-language';

export function LanguageProvider({ children }: PropsWithChildren) {
    const [language, setLanguageState] = useState<Language>(() => {
        if (typeof window === 'undefined') return 'zh-CN';
        return window.localStorage.getItem(storageKey) === 'en' ? 'en' : 'zh-CN';
    });

    const setLanguage = (nextLanguage: Language) => {
        setLanguageState(nextLanguage);
        window.localStorage.setItem(storageKey, nextLanguage);
    };

    useEffect(() => {
        document.documentElement.lang = language;
    }, [language]);

    const value = useMemo<LanguageContextValue>(() => ({
        language,
        setLanguage,
        t: (text: string) => language === 'en' ? (translations[text] ?? text) : text,
    }), [language]);

    return <LanguageContext.Provider value={value}>{children}</LanguageContext.Provider>;
}

export function useLanguage(): LanguageContextValue {
    const context = useContext(LanguageContext);

    if (!context) {
        throw new Error('useLanguage must be used inside LanguageProvider');
    }

    return context;
}
