import { useForm, usePage } from '@inertiajs/react';
import { Download, FilePenLine, Printer, Save } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { fullName } from '../../lib/format';

const earningFields = [
    ['overtime_pay', 'Overtime Pay'],
    ['other_earnings', 'Others'],
    ['increase_amount', 'Increase'],
];

const deductionFields = [
    ['withholding_tax', 'Withholding Tax'],
    ['gsis_deduction', 'GSIS'],
    ['philhealth_deduction', 'PhilHealth'],
    ['pag_ibig_deduction', 'Pag-IBIG'],
    ['multi_purpose_loan', 'Multi-Purpose Loan'],
    ['gsis_loan', 'GSIS Loan'],
    ['gsis_eplus_loan', 'GSIS ePlus Loan'],
    ['fea_dues', 'FEA Dues'],
    ['oba_deduction', 'OBA'],
    ['cra_deduction', 'CRA'],
];

function dateLabel(value) {
    if (!value) return '-';
    return new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric',
    });
}

function cutoffLabel(startValue, endValue) {
    if (!startValue || !endValue) return '-';
    const start = new Date(`${startValue.slice(0, 10)}T00:00:00`);
    const end = new Date(`${endValue.slice(0, 10)}T00:00:00`);
    const month = start.toLocaleDateString('en-PH', { month: 'long' }).toUpperCase();
    if (start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()) {
        return `${month} ${start.getDate()}-${end.getDate()}, ${end.getFullYear()}`;
    }
    return `${dateLabel(startValue)} - ${dateLabel(endValue)}`.toUpperCase();
}

function amount(value, zeroAsDash = true) {
    const number = Number(value || 0);
    if (zeroAsDash && number === 0) return '-';
    return number.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function PayslipRow({ label, value, emphasis = false, subrow = false }) {
    return <div className={`official-pay-row${emphasis ? ' is-total' : ''}${subrow ? ' is-subrow' : ''}`}>
        <span>{label}</span><strong>{value}</strong>
    </div>;
}

function imageDataUrl(source) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = image.naturalWidth;
            canvas.height = image.naturalHeight;
            canvas.getContext('2d').drawImage(image, 0, 0);
            resolve(canvas.toDataURL('image/png'));
        };
        image.onerror = reject;
        image.src = source;
    });
}

export default function PayrollShow({ record }) {
    const { auth } = usePage().props;
    const period = record.payroll_period;
    const employee = record.employee;
    const canManage = ['admin', 'payroll_staff'].includes(auth.user.role);
    const [editing, setEditing] = useState(false);
    const [downloading, setDownloading] = useState(false);
    const cutoff = cutoffLabel(period?.start_date, period?.end_date);
    const employeeName = fullName(employee).toUpperCase();
    const totalEarnings = Number(record.total_earnings || record.gross_pay || 0);
    const form = useForm(Object.fromEntries(
        [...earningFields, ...deductionFields].map(([field]) => [field, Number(record[field] || 0).toFixed(2)]),
    ));

    const submit = (event) => {
        event.preventDefault();
        form.put(`/payroll/records/${record.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    const download = async () => {
        setDownloading(true);
        try {
            const { jsPDF } = await import('jspdf');
            const pdf = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });
            const logo = await imageDataUrl('/images/cvsu-logo.png');
            const x = 15;
            const width = 180;
            const center = 105;
            const divider = 105;
            const right = x + width;
            const text = (value, tx, ty, options = {}) => pdf.text(String(value ?? '-'), tx, ty, options);
            const pair = (label, value, y, left = x + 3, valueX = divider - 3) => {
                pdf.setFont('helvetica', 'normal');
                text(label, left, y);
                pdf.setFont('helvetica', 'bold');
                text(value, valueX, y, { align: 'right' });
            };

            pdf.setDrawColor(20, 46, 37);
            pdf.setLineWidth(0.45);
            pdf.rect(x, 12, width, 166);
            pdf.addImage(logo, 'PNG', x + 7, 18, 18, 18);
            pdf.setTextColor(20, 46, 37);
            pdf.setFont('helvetica', 'bold');
            pdf.setFontSize(12);
            text('CAVITE STATE UNIVERSITY', center, 18, { align: 'center' });
            pdf.setFontSize(10);
            text('Imus Campus', center, 23, { align: 'center' });
            pdf.setFont('helvetica', 'normal');
            pdf.setFontSize(8.5);
            text('Cavite Civic Center, Palico IV, Imus, Cavite', center, 28, { align: 'center' });
            text('(046) 471-66-07 / (046) 686-2349', center, 33, { align: 'center' });
            text('www.cvsu.edu.ph', center, 38, { align: 'center' });

            pdf.setTextColor(20, 20, 20);
            pdf.setFontSize(8.5);
            [['Employee Name:', employeeName], ['Employee ID No.:', employee?.employee_no || '-'], ['Department:', employee?.department || '-'], ['Cut-off Date:', cutoff]].forEach(([label, value], index) => {
                const y = 46 + (index * 5);
                pdf.setFont('helvetica', 'normal'); text(label, x + 3, y);
                pdf.setFont('helvetica', 'bold'); text(value, x + 46, y);
            });
            pdf.line(x, 64, right, 64);
            pdf.line(divider, 64, divider, 147);
            pdf.setFontSize(7.8);
            [['RATE PER HOUR', amount(employee?.rate_amount, false)], ['TOTAL NO. OF HOURS', amount(record.total_hours_worked, false)], ['Overtime Pay', amount(record.overtime_pay)], ['Late/Undertime (mins.)', record.late_undertime_minutes || '-'], ['Absent (days)', Number(record.absent_days || 0) || '-'], ['Others', amount(record.other_earnings)], ['Increase', amount(record.increase_amount)]].forEach(([label, value], index) => pair(label, value, 70 + (index * 5)));
            [['Withholding Tax', record.withholding_tax], ['GSIS', record.gsis_deduction], ['PhilHealth', record.philhealth_deduction], ['Pag-IBIG', record.pag_ibig_deduction], ['LOANS:', null], ['Multi-Purpose Loan', record.multi_purpose_loan], ['GSIS Loan', record.gsis_loan], ['GSIS ePlus Loan', record.gsis_eplus_loan], ['FEA Dues', record.fea_dues], ['OBA', record.oba_deduction], ['CRA', record.cra_deduction]].forEach(([label, value], index) => {
                pdf.setFont('helvetica', label === 'LOANS:' ? 'bold' : 'normal');
                text(label, divider + (index > 4 && index < 8 ? 6 : 3), 70 + (index * 5));
                if (value !== null) {
                    pdf.setFont('helvetica', 'bold');
                    text(amount(value), right - 3, 70 + (index * 5), { align: 'right' });
                }
            });
            pdf.line(x, 126, right, 126);
            pair('Total Earnings:', amount(totalEarnings, false), 133);
            pair('Total Deductions:', amount(record.total_deductions), 133, divider + 3, right - 3);
            pdf.line(x, 137, right, 137);
            pdf.setFontSize(10);
            pair('Net Income:', amount(record.net_pay, false), 144, x + 3, right - 3);
            pdf.line(x, 147, right, 147);
            pdf.setFontSize(8.5);
            [['TIN #', employee?.tin_no], ['GSIS #', employee?.gsis_no], ['Pag-IBIG #', employee?.pag_ibig_no], ['PhilHealth #', employee?.philhealth_no]].forEach(([label, value], index) => {
                pdf.setFont('helvetica', 'normal'); text(label, x + 3, 153 + (index * 5));
                pdf.setFont('helvetica', 'bold'); text(value || '-', x + 35, 153 + (index * 5));
            });
            pdf.setFontSize(8);
            pdf.setFont('helvetica', 'bold');
            text('PREPARED BY:', 61, 195, { align: 'center' });
            text('NOTED BY:', 154, 195, { align: 'center' });
            pdf.line(35, 207, 87, 207);
            pdf.line(128, 207, 180, 207);
            text('CELINE JANE S. MAGSUMBOL', 61, 212, { align: 'center' });
            text('ANALIN I. VASQUEZ', 154, 212, { align: 'center' });
            pdf.setFont('helvetica', 'normal');
            text('Admin Aide VI', 61, 217, { align: 'center' });
            text('Admin Officer I', 154, 217, { align: 'center' });
            pdf.save(`payslip-${employee?.employee_no || record.id}-${period?.start_date || 'period'}.pdf`);
        } finally {
            setDownloading(false);
        }
    };

    return <AppLayout title="Payslip" subtitle={`${period?.period_name || 'Payroll statement'} | Pay date: ${dateLabel(period?.pay_date)}`}>
        <div className="payslip-toolbar print-hidden">
            {canManage && <button type="button" className="payslip-edit-button" onClick={() => setEditing((value) => !value)}><FilePenLine size={17} />{editing ? 'Close editor' : 'Edit details'}</button>}
            <button type="button" onClick={() => window.print()}><Printer size={17} />Print</button>
            <button type="button" onClick={download} disabled={downloading}><Download size={17} />{downloading ? 'Preparing...' : 'Download PDF'}</button>
        </div>

        {canManage && editing && <form className="payroll-adjustment-editor print-hidden" onSubmit={submit}>
            <div className="payroll-editor-heading"><div><span>Payroll details</span><h2>Earnings and deductions</h2></div><p>Hours and regular pay come from attendance. Enter only applicable additions and deductions.</p></div>
            <fieldset><legend>Additional earnings</legend><div className="payroll-editor-grid">{earningFields.map(([field, label]) => <label key={field}>{label}<div className="currency-input"><span>PHP</span><input type="number" min="0" max="99999999.99" step="0.01" value={form.data[field]} onChange={(event) => form.setData(field, event.target.value)} required /></div></label>)}</div></fieldset>
            <fieldset><legend>Deductions</legend><div className="payroll-editor-grid">{deductionFields.map(([field, label]) => <label key={field}>{label}<div className="currency-input"><span>PHP</span><input type="number" min="0" max="99999999.99" step="0.01" value={form.data[field]} onChange={(event) => form.setData(field, event.target.value)} required /></div></label>)}</div></fieldset>
            <div className="form-actions"><button type="submit" disabled={form.processing}><Save size={17} />{form.processing ? 'Saving...' : 'Save Payslip Details'}</button></div>
        </form>}

        <article className="official-payslip">
            <header className="official-payslip-header">
                <img src="/images/cvsu-logo.png" alt="Cavite State University logo" />
                <div><h2>Cavite State University</h2><strong>Imus Campus</strong><p>Cavite Civic Center, Palico IV, Imus, Cavite</p><p>(046) 471-66-07 / (046) 686-2349</p><a href="https://www.cvsu.edu.ph">www.cvsu.edu.ph</a></div>
                <span className="official-copy-label">OFFICIAL PAYSLIP</span>
            </header>
            <section className="official-employee-details" aria-label="Employee and payroll period details">
                <div><span>Employee Name:</span><strong>{employeeName}</strong></div>
                <div><span>Employee ID No.:</span><strong>{employee?.employee_no || '-'}</strong></div>
                <div><span>Department:</span><strong>{employee?.department || '-'}</strong></div>
                <div><span>Cut-off Date:</span><strong>{cutoff}</strong></div>
            </section>
            <section className="official-pay-columns">
                <div className="official-pay-column" aria-label="Earnings">
                    <div className="official-column-heading"><span>Earnings</span><small>Attendance and additions</small></div>
                    <PayslipRow label="RATE PER HOUR" value={amount(employee?.rate_amount, false)} />
                    <PayslipRow label="TOTAL NO. OF HOURS" value={amount(record.total_hours_worked, false)} />
                    <PayslipRow label="Overtime Pay" value={amount(record.overtime_pay)} />
                    <PayslipRow label="Late/Undertime (mins.)" value={record.late_undertime_minutes || '-'} />
                    <PayslipRow label="Absent (days)" value={Number(record.absent_days || 0) || '-'} />
                    <PayslipRow label="Others" value={amount(record.other_earnings)} />
                    <PayslipRow label="Increase" value={amount(record.increase_amount)} />
                    <PayslipRow label="Total Earnings" value={amount(totalEarnings, false)} emphasis />
                </div>
                <div className="official-pay-column" aria-label="Deductions">
                    <div className="official-column-heading"><span>Deductions</span><small>Contributions, taxes and loans</small></div>
                    <PayslipRow label="Withholding Tax" value={amount(record.withholding_tax)} />
                    <PayslipRow label="GSIS" value={amount(record.gsis_deduction)} />
                    <PayslipRow label="PhilHealth" value={amount(record.philhealth_deduction)} />
                    <PayslipRow label="Pag-IBIG" value={amount(record.pag_ibig_deduction)} />
                    <div className="official-loan-label">LOANS:</div>
                    <PayslipRow label="Multi-Purpose Loan" value={amount(record.multi_purpose_loan)} subrow />
                    <PayslipRow label="GSIS Loan" value={amount(record.gsis_loan)} subrow />
                    <PayslipRow label="GSIS ePlus Loan" value={amount(record.gsis_eplus_loan)} subrow />
                    <PayslipRow label="FEA Dues" value={amount(record.fea_dues)} />
                    <PayslipRow label="OBA" value={amount(record.oba_deduction)} />
                    <PayslipRow label="CRA" value={amount(record.cra_deduction)} />
                    <PayslipRow label="Total Deductions" value={amount(record.total_deductions)} emphasis />
                </div>
            </section>
            <div className="official-net-income"><span>Net Income</span><strong>PHP {amount(record.net_pay, false)}</strong></div>
            <section className="official-government-ids" aria-label="Government identification numbers">
                <div><span>TIN #</span><strong>{employee?.tin_no || '-'}</strong></div>
                <div><span>GSIS #</span><strong>{employee?.gsis_no || '-'}</strong></div>
                <div><span>Pag-IBIG #</span><strong>{employee?.pag_ibig_no || '-'}</strong></div>
                <div><span>PhilHealth #</span><strong>{employee?.philhealth_no || '-'}</strong></div>
            </section>
            <footer className="official-signatories">
                <div><span>Prepared by:</span><i></i><strong>Celine Jane S. Magsumbol</strong><small>Admin Aide VI</small></div>
                <div><span>Noted by:</span><i></i><strong>Analin I. Vasquez</strong><small>Admin Officer I</small></div>
            </footer>
        </article>
    </AppLayout>;
}
