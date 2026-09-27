import { Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, CalendarCheck2, CalendarClock, CalendarDays, Clock3, GraduationCap, ListChecks, Wallet } from 'lucide-react';
import AppLayout from '../Layouts/AppLayout';
import useRealtimeReload from '../hooks/useRealtimeReload';
import { fullName, money, time12 } from '../lib/format';

function MetricTile({ href, icon: Icon, label, value, tone }) {
    return <Link href={href} className={`bento-metric ${tone}`}>
        <span className="metric-icon"><Icon size={19} strokeWidth={1.8} /></span>
        <span className="metric-copy"><small>{label}</small><strong>{value}</strong></span>
        <ArrowUpRight className="metric-arrow" size={17} />
    </Link>;
}

function AcademicTermCard({ icon: Icon, label, value, tone }) {
    return <article className={`academic-term-card ${tone}`}>
        <span className="academic-term-copy"><small>{label}</small><strong>{value || '-'}</strong></span>
        <span className="academic-term-icon"><Icon size={21} strokeWidth={1.8} /></span>
    </article>;
}

export default function Dashboard({ employeeCount, presentToday, openPeriods, attendanceTrend = [], latestPayrolls, recentAttendance, contractWarnings = [] }) {
    const { academicTerm, auth } = usePage().props;
    const isFaculty = auth.user.role === 'faculty';
    const semester = academicTerm?.semester?.replace(/ Semester$/i, '').replace(/ Term$/i, '').toUpperCase();

    useRealtimeReload(isFaculty
        ? ['latestPayrolls', 'recentAttendance', 'contractWarnings']
        : ['employeeCount', 'presentToday', 'openPeriods', 'attendanceTrend', 'latestPayrolls', 'recentAttendance', 'contractWarnings'], 5000);

    return <AppLayout title="Dashboard" subtitle={isFaculty ? 'Your attendance and payroll summary.' : 'Overview of faculty attendance and payroll activity.'}>
        {contractWarnings.length > 0 && <section className="contract-warning" role="status">
            <span className="contract-warning-icon"><CalendarClock size={20} /></span>
            <div><strong>Contract expiration reminder</strong><span>{contractWarnings.map((employee) => `${fullName(employee)} - ${employee.days_remaining === 0 ? 'ends today' : `${employee.days_remaining} day${employee.days_remaining === 1 ? '' : 's'} left`}`).join(' | ')}</span></div>
            {!isFaculty && <Link href="/employees">Review faculty <ArrowUpRight size={15} /></Link>}
        </section>}
        <section className="academic-term-summary" aria-label="Current academic term">
            <AcademicTermCard icon={CalendarDays} label="Current Academic Year" value={academicTerm?.school_year} tone="academic-year" />
            <AcademicTermCard icon={ListChecks} label="Current Semester" value={semester} tone="academic-semester" />
        </section>
        <section className={`bento-dashboard${isFaculty ? ' faculty-bento' : ''}`}>
            {!isFaculty && <>
                <MetricTile href={auth.user.role === 'admin' ? '/employees' : '/attendance'} icon={GraduationCap} label="Faculty" value={employeeCount} tone="metric-green" />
                <MetricTile href="/attendance" icon={Clock3} label="Present today" value={presentToday} tone="metric-gold" />
                <MetricTile href="/payroll" icon={Wallet} label="Open payroll periods" value={openPeriods} tone="metric-blue" />
            </>}

            <section className="bento-panel attendance-bento">
                <header className="bento-heading">
                    <div><span className="bento-eyebrow">{isFaculty ? 'My attendance' : 'Attendance'}</span><h2>{isFaculty ? 'My recent activity' : 'Recent activity'} <span className="live-indicator">Live</span></h2></div>
                    <Link href="/attendance">View all <ArrowUpRight size={15} /></Link>
                </header>
                <div className="bento-table"><table><thead><tr>{!isFaculty && <th>Faculty</th>}<th>Date</th><th>In</th><th>Out</th><th>Status</th></tr></thead><tbody>
                    {recentAttendance.length ? recentAttendance.map((log) => <tr key={log.id}>{!isFaculty && <td><strong>{fullName(log.employee)}</strong></td>}<td>{log.attendance_date}</td><td>{time12(log.time_in)}</td><td>{time12(log.time_out)}</td><td><span className="badge">{log.status}</span></td></tr>) : <tr><td colSpan={isFaculty ? 4 : 5} className="empty-row">No attendance logs yet.</td></tr>}
                </tbody></table></div>
            </section>

            {!isFaculty && <section className="trend-bento">
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
            </section>}

            <section className="bento-panel payroll-bento">
                <header className="bento-heading">
                    <div><span className="bento-eyebrow">{isFaculty ? 'My payroll' : 'Payroll'}</span><h2>{isFaculty ? 'My latest payslips' : 'Latest records'}</h2></div>
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
