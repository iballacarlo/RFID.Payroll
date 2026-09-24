import { Link, router, usePage } from '@inertiajs/react';
import { ArrowUpRight, CalendarCheck2, Clock3, GraduationCap, Wallet } from 'lucide-react';
import { useEffect } from 'react';
import AppLayout from '../Layouts/AppLayout';
import { fullName, money, time12 } from '../lib/format';

function MetricTile({ href, icon: Icon, label, value, tone }) {
    return <Link href={href} className={`bento-metric ${tone}`}>
        <span className="metric-icon"><Icon size={19} strokeWidth={1.8} /></span>
        <span className="metric-copy"><small>{label}</small><strong>{value}</strong></span>
        <ArrowUpRight className="metric-arrow" size={17} />
    </Link>;
}

export default function Dashboard({ employeeCount, presentToday, openPeriods, attendanceTrend = [], latestPayrolls, recentAttendance }) {
    const { auth } = usePage().props;
    const isFaculty = auth.user.role === 'faculty';

    useEffect(() => {
        const refreshDashboard = () => {
            if (!document.hidden) {
                router.reload({
                    only: ['employeeCount', 'presentToday', 'openPeriods', 'attendanceTrend', 'latestPayrolls', 'recentAttendance'],
                    preserveScroll: true,
                    preserveState: true,
                });
            }
        };
        const interval = window.setInterval(refreshDashboard, 30000);
        document.addEventListener('visibilitychange', refreshDashboard);
        return () => {
            window.clearInterval(interval);
            document.removeEventListener('visibilitychange', refreshDashboard);
        };
    }, []);

    return <AppLayout title="Dashboard" subtitle={isFaculty ? 'Your attendance and payroll summary.' : 'Overview of faculty attendance and payroll activity.'}>
        <section className={`bento-dashboard${isFaculty ? ' faculty-bento' : ''}`}>
            {!isFaculty && <>
                <MetricTile href={auth.user.role === 'admin' ? '/employees' : '/attendance'} icon={GraduationCap} label="Faculty" value={employeeCount} tone="metric-green" />
                <MetricTile href="/attendance" icon={Clock3} label="Present today" value={presentToday} tone="metric-gold" />
                <MetricTile href="/payroll" icon={Wallet} label="Open payroll periods" value={openPeriods} tone="metric-blue" />
            </>}

            <section className="bento-panel attendance-bento">
                <header className="bento-heading">
                    <div><span className="bento-eyebrow">Attendance</span><h2>Recent activity <span className="live-indicator">Live</span></h2></div>
                    <Link href="/attendance">View all <ArrowUpRight size={15} /></Link>
                </header>
                <div className="bento-table"><table><thead><tr><th>Faculty</th><th>Date</th><th>In</th><th>Out</th><th>Status</th></tr></thead><tbody>
                    {recentAttendance.length ? recentAttendance.map((log) => <tr key={log.id}><td><strong>{fullName(log.employee)}</strong></td><td>{log.attendance_date}</td><td>{time12(log.time_in)}</td><td>{time12(log.time_out)}</td><td><span className="badge">{log.status}</span></td></tr>) : <tr><td colSpan="5" className="empty-row">No attendance logs yet.</td></tr>}
                </tbody></table></div>
            </section>

            <section className="trend-bento">
                <header className="bento-heading">
                    <div><span className="bento-eyebrow">Seven-day view</span><h2>Attendance pulse</h2></div>
                    <span className="trend-icon"><CalendarCheck2 size={19} /></span>
                </header>
                <div className="trend-chart" aria-label="Attendance during the last seven days">
                    {attendanceTrend.map((day) => {
                        const highest = Math.max(1, ...attendanceTrend.map((item) => item.count));
                        const height = day.count === 0 ? 8 : Math.max(18, (day.count / highest) * 100);
                        return <div className="trend-column" key={day.label} title={`${day.label}: ${day.count}`}>
                            <span className="trend-value">{day.count}</span>
                            <i style={{ height: `${height}%` }} />
                            <small>{day.label}</small>
                        </div>;
                    })}
                </div>
            </section>

            <section className="bento-panel payroll-bento">
                <header className="bento-heading">
                    <div><span className="bento-eyebrow">Payroll</span><h2>Latest records</h2></div>
                    <Link href="/payroll">{isFaculty ? 'View all' : 'Manage'} <ArrowUpRight size={15} /></Link>
                </header>
                <div className="payroll-stack">
                    {latestPayrolls.length ? latestPayrolls.map((record) => <Link href={`/payroll/records/${record.id}`} className="payroll-item" key={record.id}>
                        <span><strong>{fullName(record.employee)}</strong><small>{record.payroll_period?.period_name}</small></span>
                        <strong className="payroll-amount">{money(record.net_pay)}</strong>
                    </Link>) : <div className="bento-empty">No payroll records yet.</div>}
                </div>
            </section>
        </section>
    </AppLayout>;
}
