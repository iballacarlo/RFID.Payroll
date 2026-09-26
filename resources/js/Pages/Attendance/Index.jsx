import { useForm, usePage } from '@inertiajs/react';
import { CalendarDays, CalendarRange, CheckCircle2, Clock3, LogIn, LogOut, Radio, RefreshCw, TimerReset } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import Pagination from '../../components/Pagination';
import useRealtimeReload from '../../hooks/useRealtimeReload';
import { dateToday, fullName, time12 } from '../../lib/format';

function readableDate(value, options = {}) {
    if (!value) return '-';
    return new Date(`${String(value).slice(0, 10)}T00:00:00`).toLocaleDateString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric', ...options,
    });
}

function methodLabel(value) {
    if (!value) return '-';
    return value === 'rfid' ? 'RFID' : value === 'fingerprint' ? 'Fingerprint' : 'Manual';
}

function statusLabel(log) {
    if (!log?.time_in) return 'Not timed in';
    if (!log?.time_out) return 'Currently timed in';
    return log.status === 'present' ? 'Complete' : log.status === 'late' ? 'Late' : log.status === 'undertime' ? 'Undertime' : 'Incomplete';
}

function statusClass(log) {
    if (!log?.time_in) return 'is-pending';
    if (!log?.time_out) return 'is-active';
    return ['late', 'undertime', 'incomplete'].includes(log.status) ? 'is-warning' : 'is-complete';
}

function FacultyDtr({ dtr, logs, refreshing }) {
    const { auth } = usePage().props;
    const [clock, setClock] = useState(new Date());
    const today = dtr?.today_log;
    const summary = dtr?.summary || {};

    useEffect(() => {
        const clockTimer = window.setInterval(() => setClock(new Date()), 1000);
        return () => window.clearInterval(clockTimer);
    }, []);

    const clockTime = new Intl.DateTimeFormat('en-PH', {
        timeZone: 'Asia/Manila', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true,
    }).format(clock);
    const clockDate = new Intl.DateTimeFormat('en-PH', {
        timeZone: 'Asia/Manila', weekday: 'long', month: 'long', day: 'numeric', year: 'numeric',
    }).format(clock);

    return <div className="faculty-dtr">
        <section className="dtr-live-band">
            <div className="dtr-live-copy">
                <div className="dtr-eyebrow"><span><Radio size={13} />Live</span>Online Daily Time Record</div>
                <h2>{fullName(auth.user.employee)}</h2>
                <p>{auth.user.employee?.employee_no} <i></i> {auth.user.employee?.department || 'Cavite State University - Imus Campus'}</p>
            </div>
            <div className="dtr-clock" aria-label="Current Philippine time"><strong>{clockTime}</strong><span>{clockDate}</span><small className={refreshing ? 'is-refreshing' : ''}><RefreshCw size={12} />{refreshing ? 'Syncing records' : 'Updates every 5 seconds'}</small></div>
        </section>

        <section className="dtr-today" aria-label="Today attendance status">
            <div className="dtr-today-heading">
                <div><span>Today</span><h3>{readableDate(dtr?.date, { weekday: 'long' })}</h3></div>
                <strong className={`dtr-state ${statusClass(today)}`}>{today?.time_out ? <CheckCircle2 size={17} /> : <Radio size={17} />}{statusLabel(today)}</strong>
            </div>
            <div className="dtr-time-grid">
                <div><span className="dtr-time-icon is-in"><LogIn size={20} /></span><p>Time In</p><strong>{time12(today?.time_in)}</strong><small>{methodLabel(today?.method_in)}</small></div>
                <div><span className="dtr-time-icon is-out"><LogOut size={20} /></span><p>Time Out</p><strong>{time12(today?.time_out)}</strong><small>{methodLabel(today?.method_out)}</small></div>
                <div><span className="dtr-time-icon is-hours"><Clock3 size={20} /></span><p>Payable Hours</p><strong>{Number(today?.total_hours || 0).toFixed(2)}</strong><small>Computed from schedule</small></div>
                <div><span className="dtr-time-icon is-variance"><TimerReset size={20} /></span><p>Late / Undertime</p><strong>{today?.late_minutes || 0} / {today?.undertime_minutes || 0}</strong><small>Minutes</small></div>
            </div>
            <div className="dtr-schedule-strip">
                <span><CalendarDays size={16} />Today's assigned schedule</span>
                <div>{dtr?.today_schedules?.length ? dtr.today_schedules.map((schedule) => <strong key={schedule.id}>{time12(schedule.start_time)} - {time12(schedule.end_time)}<small>{schedule.type}</small></strong>) : <em>No assigned schedule today</em>}</div>
            </div>
        </section>

        <section className="dtr-cutoff-section">
            <div className="dtr-section-heading"><div><span>Current cutoff</span><h3>{readableDate(dtr?.cutoff?.start_date)} - {readableDate(dtr?.cutoff?.end_date)}</h3></div><CalendarRange size={23} /></div>
            <div className="dtr-summary-grid">
                <div><span>Payable hours</span><strong>{Number(summary.total_hours || 0).toFixed(2)}</strong></div>
                <div><span>Days present</span><strong>{summary.days_present || 0}<small> / {summary.scheduled_days || 0}</small></strong></div>
                <div><span>Absent days</span><strong>{summary.absent_days || 0}</strong></div>
                <div><span>Late minutes</span><strong>{summary.late_minutes || 0}</strong></div>
                <div><span>Undertime minutes</span><strong>{summary.undertime_minutes || 0}</strong></div>
            </div>
        </section>

        <section className="dtr-history">
            <div className="dtr-section-heading"><div><span>Attendance history</span><h3>My Daily Time Record</h3></div><small>Newest records appear first</small></div>
            <div className="table-wrap"><table className="dtr-table"><thead><tr><th>Date</th><th>Time In</th><th>Time Out</th><th>Method</th><th>Hours</th><th>Late</th><th>Undertime</th><th>Status</th></tr></thead><tbody>{logs.data.length ? logs.data.map((log) => <tr key={log.id}><td><strong>{readableDate(log.attendance_date, { weekday: 'short' })}</strong></td><td>{time12(log.time_in)}</td><td>{time12(log.time_out)}</td><td>{methodLabel(log.method_in)} / {methodLabel(log.method_out)}</td><td>{Number(log.total_hours || 0).toFixed(2)}</td><td>{log.late_minutes} min</td><td>{log.undertime_minutes} min</td><td><span className={`dtr-table-status ${statusClass(log)}`}>{statusLabel(log)}</span></td></tr>) : <tr><td colSpan="8" className="empty-row">No attendance records yet.</td></tr>}</tbody></table></div>
            <Pagination links={logs.links} />
        </section>
    </div>;
}

function StaffAttendance({ employees, logs }) {
    const firstEmployee = employees[0]?.id || '';
    const tap = useForm({ identifier: '', method: 'rfid' });
    const manual = useForm({ employee_id: firstEmployee, attendance_date: dateToday(), time_in: '', time_out: '', remarks: '' });
    const testLogs = useForm({ employee_id: firstEmployee, start_date: dateToday(), end_date: dateToday() });
    return <>
        <section className="content-grid">
            <form className="panel compact-form" onSubmit={(e) => { e.preventDefault(); tap.post('/attendance/tap'); }}><h2>RFID / Fingerprint Tap</h2><label>Identifier<input placeholder="Scan RFID UID or fingerprint code" value={tap.data.identifier} onChange={(e) => tap.setData('identifier', e.target.value)} required autoFocus /></label><label>Method<select value={tap.data.method} onChange={(e) => tap.setData('method', e.target.value)}><option value="rfid">RFID</option><option value="fingerprint">Fingerprint</option></select></label><button type="submit" disabled={tap.processing}>Record Tap</button></form>
            <form className="panel compact-form" onSubmit={(e) => { e.preventDefault(); manual.post('/attendance/manual'); }}><h2>Manual Attendance</h2><label>Faculty<select value={manual.data.employee_id} onChange={(e) => manual.setData('employee_id', e.target.value)} required>{employees.map((employee) => <option key={employee.id} value={employee.id}>{fullName(employee)}</option>)}</select></label><label>Date<input type="date" value={manual.data.attendance_date} onChange={(e) => manual.setData('attendance_date', e.target.value)} required /></label><label>Time In<input type="time" value={manual.data.time_in} onChange={(e) => manual.setData('time_in', e.target.value)} /></label><label>Time Out<input type="time" value={manual.data.time_out} onChange={(e) => manual.setData('time_out', e.target.value)} /></label><label>Remarks<input value={manual.data.remarks} onChange={(e) => manual.setData('remarks', e.target.value)} /></label><button type="submit" disabled={manual.processing}>Save Manual Log</button></form>
        </section>
        <section className="panel test-attendance-panel"><div><h2>Generate Schedule-Based Test Attendance</h2><p>Creates complete attendance only on the selected faculty member's scheduled days. Existing attendance logs are preserved.</p></div><form className="test-attendance-form" onSubmit={(e) => { e.preventDefault(); testLogs.post('/attendance/generate-test'); }}><label>Faculty<select value={testLogs.data.employee_id} onChange={(e) => testLogs.setData('employee_id', e.target.value)}>{employees.map((employee) => <option key={employee.id} value={employee.id}>{fullName(employee)}</option>)}</select></label><label>Start Date<input type="date" value={testLogs.data.start_date} onChange={(e) => testLogs.setData('start_date', e.target.value)} /></label><label>End Date<input type="date" value={testLogs.data.end_date} onChange={(e) => testLogs.setData('end_date', e.target.value)} /></label><button type="submit" disabled={testLogs.processing}>Generate Test Logs</button></form></section>
        <div className="panel"><div className="panel-heading"><h2>Attendance Logs</h2></div><div className="table-wrap"><table><thead><tr><th>Faculty</th><th>Date</th><th>Time In</th><th>Time Out</th><th>Method</th><th>Hours</th><th>Late</th><th>Undertime</th><th>Status</th></tr></thead><tbody>{logs.data.length ? logs.data.map((log) => <tr key={log.id}><td>{fullName(log.employee)}</td><td>{log.attendance_date}</td><td>{time12(log.time_in)}</td><td>{time12(log.time_out)}</td><td>{methodLabel(log.method_in)} / {methodLabel(log.method_out)}</td><td>{log.total_hours}</td><td>{log.late_minutes} min</td><td>{log.undertime_minutes} min</td><td><span className="badge">{log.status}</span></td></tr>) : <tr><td colSpan="9">No attendance logs yet.</td></tr>}</tbody></table></div><Pagination links={logs.links} /></div>
    </>;
}

export default function AttendanceIndex({ employees, logs, dtr }) {
    const { auth } = usePage().props;
    const isFaculty = auth.user.role === 'faculty';
    const refreshing = useRealtimeReload(isFaculty ? ['logs', 'dtr'] : ['logs'], 5000);
    return <AppLayout title={isFaculty ? 'Online DTR' : 'Attendance'} subtitle={isFaculty ? 'Review your live time records and current cutoff totals.' : 'Record attendance through RFID, fingerprint code, or manual entry.'}>
        {isFaculty ? <FacultyDtr dtr={dtr} logs={logs} refreshing={refreshing} /> : <StaffAttendance employees={employees} logs={logs} />}
    </AppLayout>;
}
