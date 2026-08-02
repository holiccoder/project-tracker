import { SVGAttributes } from 'react';

export default function ApplicationLogo(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 128 128"
            xmlns="http://www.w3.org/2000/svg"
        >
            <defs>
                <linearGradient id="application-logo-gradient" x1="16" y1="12" x2="112" y2="116" gradientUnits="userSpaceOnUse">
                    <stop stopColor="#4F46E5" />
                    <stop offset="1" stopColor="#2563EB" />
                </linearGradient>
            </defs>
            <rect x="12" y="12" width="104" height="104" rx="28" fill="url(#application-logo-gradient)" />
            <path d="M38 39.5C38 35.91 40.91 33 44.5 33H83.5C87.09 33 90 35.91 90 39.5V88.5C90 92.09 87.09 95 83.5 95H44.5C40.91 95 38 92.09 38 88.5V39.5Z" fill="white" fillOpacity="0.97" />
            <path d="M38 48H90" stroke="#C7D2FE" strokeWidth="4" />
            <path d="M48 41H61" stroke="#6366F1" strokeWidth="4" strokeLinecap="round" />
            <circle cx="48" cy="61" r="4" fill="#4F46E5" />
            <path d="M60 61H79" stroke="#A5B4FC" strokeWidth="4" strokeLinecap="round" />
            <circle cx="48" cy="75" r="4" fill="#4F46E5" />
            <path d="M60 75H76" stroke="#A5B4FC" strokeWidth="4" strokeLinecap="round" />
            <circle cx="48" cy="87" r="4" fill="#22C55E" />
            <path d="M46.5 87L48 88.5L51 85.5" stroke="white" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M96 25L98 29L102 31L98 33L96 37L94 33L90 31L94 29L96 25Z" fill="#FDE68A" />
        </svg>
    );
}
