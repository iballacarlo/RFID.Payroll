import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarDays, ChartNoAxesCombined, Clock3, GraduationCap, LayoutDashboard, LogOut, Settings2, Wallet } from 'lucide-react';
import { dateLabel, fullName } from '../lib/format';

const navigation = [
    { label: 'Dashboard', href: '/', icon: LayoutDashboard, tone: 'nav-dashboard', active: (url) => url === '/' },
    { label: 'Faculty', href: '/employees', icon: GraduationCap, tone: 'nav-faculty', roles: ['admin'], active: (url) => url.startsWith('/employees') },
    { label: 'Ranks', href: '/faculty-ranks', icon: ChartNoAxesCombined, tone: 'nav-ranks', roles: ['admin'], active: (url) => url.startsWith('/faculty-ranks') },
    { label: 'Attendance', href: '/attendance', icon: Clock3, tone: 'nav-attendance', active: (url) => url.startsWith('/attendance') },
    { label: 'Payroll', href: '/payroll', icon: Wallet, tone: 'nav-payroll', active: (url) => url.startsWith('/payroll') },
    { label: 'Settings', href: '/settings/accounts', icon: Settings2, tone: 'nav-settings', roles: ['admin'], active: (url) => url.startsWith('/settings') },
];

export default function AppLayout({ title, subtitle, children }) {
    const page = usePage();
    const { auth, flash, errors } = page.props;
    const { url } = page;
    const user = auth.user;
    const module = url.startsWith('/employees') ? 'faculty'
        : url.startsWith('/faculty-ranks') ? 'ranks'
            : url.startsWith('/settings') ? 'settings'
                : url.startsWith('/attendance') ? 'attendance'
                    : url.startsWith('/payroll') ? 'payroll'
                        : 'dashboard';
    const moduleIndex = { dashboard: '01', faculty: '02', ranks: '03', settings: '04', attendance: '05', payroll: '06' }[module];

    const logout = () => router.post('/logout');

    return (
        <>
            <Head title={`${title} | Payroll System`} />
            <aside className="sidebar">
                <div className="brand">
                    <div className="brand-logos">
                        <img src="/images/cvsu-logo.png" alt="Cavite State University logo" />
                        <img src="/images/dcs-logo.png" alt="Department of Computer Studies logo" />
                    </div>
                    <div>
                        <strong>Payroll System</strong>
                        <small>DCS Attendance System</small>
                    </div>
                </div>
                <nav aria-label="Main navigation">
                    {navigation.filter((item) => !item.roles || item.roles.includes(user.role)).map((item) => (
                        <Link key={item.href} href={item.href} className={`${item.tone}${item.active(url) ? ' active' : ''}`} aria-current={item.active(url) ? 'page' : undefined}><item.icon size={18} strokeWidth={1.8} />{item.label}</Link>
                    ))}
                </nav>
                <button className="logout-button" type="button" onClick={logout} aria-label="Logout"><LogOut size={17} /><span>Logout</span></button>
            </aside>
            <main className={`main module-${module}`}>
                <header className="topbar">
                    <div className="editorial-heading">
                        <span className="page-index">{moduleIndex}</span>
                        <div>
                        <span className="page-kicker">DCS / {module.replace('-', ' ')}</span>
                        <h1>{title}</h1>
                        <p>{subtitle}</p>
                        </div>
                    </div>
                    <div className="topbar-meta">
                        <span className="date-chip"><CalendarDays size={16} />{dateLabel()}</span>
                        <div className="user-chip"><span className="user-avatar">{(user.employee ? fullName(user.employee) : user.name).charAt(0)}</span><span className="user-copy"><strong>{user.employee ? fullName(user.employee) : user.name}</strong><small>{user.role.replace('_', ' ')}</small></span></div>
                    </div>
                </header>
                {flash?.success && <div className="alert success">{flash.success}</div>}
                {Object.keys(errors || {}).length > 0 && <div className="alert error">{Object.values(errors)[0]}</div>}
                {children}
            </main>
        </>
    );
}
