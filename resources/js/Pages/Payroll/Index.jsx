import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Calculator } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import Pagination from '../../components/Pagination';
import useRealtimeReload from '../../hooks/useRealtimeReload';
import { fullName, money } from '../../lib/format';

function expectedPayDate(startDate, endDate) {
    if (!startDate || !endDate) return '';

    const start = new Date(`${startDate}T00:00:00`);
    const end = new Date(`${endDate}T00:00:00`);

    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return '';
    if (start.getFullYear() !== end.getFullYear() || start.getMonth() !== end.getMonth()) return '';

    const lastDay = new Date(start.getFullYear(), start.getMonth() + 1, 0).getDate();

    if (start.getDate() === 1 && end.getDate() === 15) {
        return `${startDate.slice(0, 8)}25`;
    }

    if (start.getDate() === 16 && end.getDate() === lastDay) {
        const payDate = new Date(start.getFullYear(), start.getMonth() + 1, 10);
        const year = payDate.getFullYear();
        const month = String(payDate.getMonth() + 1).padStart(2, '0');

        return `${year}-${month}-10`;
    }

    return '';
}

function PeriodRow({ period }) {
    const generate = () => {
        if (confirm(`Generate payroll for ${period.period_name}?`)) {
            router.post(`/payroll/periods/${period.id}/generate`);
        }
    };

    return (
        <tr>
            <td>{period.period_name}</td>
            <td>{period.start_date} to {period.end_date}</td>
            <td>{period.pay_date || '-'}</td>
            <td>{period.records_count}</td>
            <td><span className="badge">{period.status}</span></td>
            <td><button className="primary-table-action generate-payroll-button" type="button" onClick={generate}><Calculator size={16} />Generate Payroll</button></td>
        </tr>
    );
}

export default function PayrollIndex({ periods, records }) {
    const { auth } = usePage().props;
    const canManage = auth.user.role !== 'faculty';
    const form = useForm({ period_name: '', start_date: '', end_date: '', pay_date: '' });
    const payDate = expectedPayDate(form.data.start_date, form.data.end_date);
    useRealtimeReload(['periods', 'records'], 10000);

    const submit = (event) => {
        event.preventDefault();
        form.post('/payroll/periods');
    };

    return (
        <AppLayout title="Payroll" subtitle={canManage ? 'Create payroll periods and compute pay from attendance logs.' : 'View your generated payslips and payroll summary.'}>
            {canManage && (
                <section className="content-grid payroll-overview">
                    <form className="panel compact-form" onSubmit={submit}>
                        <h2>New Payroll Period</h2>
                        <label>
                            Period Name
                            <input
                                placeholder="September 1-15, 2026"
                                value={form.data.period_name}
                                onChange={(e) => form.setData('period_name', e.target.value)}
                                required
                            />
                        </label>
                        <label>
                            Start Date
                            <input
                                type="date"
                                value={form.data.start_date}
                                onChange={(e) => form.setData('start_date', e.target.value)}
                                required
                            />
                        </label>
                        <label>
                            End Date
                            <input
                                type="date"
                                value={form.data.end_date}
                                onChange={(e) => form.setData('end_date', e.target.value)}
                                required
                            />
                        </label>
                        <label>
                            Pay Date
                            <input type="date" value={payDate} readOnly />
                        </label>
                        {(form.errors.start_date || form.errors.end_date) && (
                            <div className="form-note">{form.errors.start_date || form.errors.end_date}</div>
                        )}
                        <button type="submit" disabled={form.processing}>Create Period</button>
                    </form>

                    <div className="panel">
                        <h2>Payroll Periods</h2>
                        <div className="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th>Coverage</th>
                                        <th>Pay Date</th>
                                        <th>Records</th>
                                        <th>Status</th>
                                        <th />
                                    </tr>
                                </thead>
                                <tbody>
                                    {periods.length ? periods.map((period) => (
                                        <PeriodRow key={period.id} period={period} />
                                    )) : (
                                        <tr><td colSpan="6">No payroll periods yet.</td></tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            )}

            <div className="panel">
                <div className="panel-heading"><h2>{canManage ? 'Payroll Records' : 'My Payroll Records'}</h2></div>
                <div className="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Faculty</th>
                                <th>Period</th>
                                <th>Days</th>
                                <th>Hours</th>
                                <th>Gross</th>
                                <th>Deductions</th>
                                <th>Net Pay</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {records.data.length ? records.data.map((record) => (
                                <tr key={record.id}>
                                    <td>{fullName(record.employee)}</td>
                                    <td>{record.payroll_period?.period_name}</td>
                                    <td>{record.total_days_worked}</td>
                                    <td>{record.total_hours_worked}</td>
                                    <td>{money(record.gross_pay)}</td>
                                    <td>{money(record.total_deductions)}</td>
                                    <td><strong>{money(record.net_pay)}</strong></td>
                                    <td><Link href={`/payroll/records/${record.id}`}>View</Link></td>
                                </tr>
                            )) : (
                                <tr><td colSpan="8">No generated payroll yet.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination links={records.links} />
            </div>
        </AppLayout>
    );
}
