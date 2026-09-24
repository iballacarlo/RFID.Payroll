import AppLayout from '../../Layouts/AppLayout';
import { fullName, money } from '../../lib/format';

function dateLabel(value) {
    if (!value) return '-';
    return new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric',
    });
}

export default function PayrollShow({ record }) {
    const period = record.payroll_period;
    const coverage = `${dateLabel(period?.start_date)} - ${dateLabel(period?.end_date)}`;
    const download = async () => {
        const { jsPDF } = await import('jspdf');
        const pdf = new jsPDF();
        const left = 20;
        const right = 190;
        const row = (label, value, y) => {
            pdf.setFont('helvetica', 'normal');
            pdf.setTextColor(90, 105, 112);
            pdf.text(label, left, y);
            pdf.setFont('helvetica', 'bold');
            pdf.setTextColor(31, 41, 51);
            pdf.text(String(value ?? '-'), right, y, { align: 'right' });
        };

        pdf.setFont('helvetica', 'bold');
        pdf.setTextColor(25, 95, 69);
        pdf.setFontSize(16);
        pdf.text('Cavite State University - Imus Campus', left, 25);
        pdf.setFontSize(10);
        pdf.setTextColor(90, 105, 112);
        pdf.text('Department of Computer Studies', left, 33);
        pdf.setDrawColor(215, 226, 221);
        pdf.line(left, 40, right, 40);

        pdf.setFontSize(10);
        pdf.setTextColor(25, 95, 69);
        pdf.text('PAYSLIP', left, 51);
        pdf.setFontSize(16);
        pdf.setTextColor(31, 41, 51);
        pdf.text(coverage, left, 61);
        row('Pay date', dateLabel(period?.pay_date), 73);
        pdf.line(left, 80, right, 80);

        pdf.setFontSize(10);
        row('Faculty member', fullName(record.employee), 92);
        row('Employee number', record.employee?.employee_no, 102);
        row('Days worked', record.total_days_worked, 112);
        row('Payable hours', record.total_hours_worked, 122);
        pdf.line(left, 131, right, 131);

        pdf.setFont('helvetica', 'bold');
        pdf.text('PAY BREAKDOWN', left, 142);
        row('Gross pay', money(record.gross_pay), 154);
        row('Deductions', `- ${money(record.total_deductions)}`, 164);
        row('Adjustments', money(record.total_adjustments), 174);
        pdf.setFillColor(238, 245, 242);
        pdf.rect(left, 184, right - left, 20, 'F');
        pdf.setFont('helvetica', 'bold');
        pdf.setFontSize(12);
        pdf.setTextColor(25, 95, 69);
        pdf.text('NET PAY', left + 5, 197);
        pdf.text(money(record.net_pay), right - 5, 197, { align: 'right' });

        pdf.save(`payslip-${record.employee?.employee_no || record.id}-${period?.start_date || 'period'}.pdf`);
    };

    return <AppLayout title="Payslip" subtitle="Payroll statement">
        <article className="payslip">
            <header className="payslip-header">
                <div className="payslip-title">
                    <div className="payslip-logos"><img src="/images/cvsu-logo.png" alt="CvSU logo" /><img src="/images/dcs-logo.png" alt="DCS logo" /></div>
                    <div><h2>Cavite State University - Imus Campus</h2><p>Department of Computer Studies</p></div>
                </div>
                <button type="button" onClick={download}>Download PDF</button>
            </header>
            <div className="payslip-heading">
                <div><small>PAYSLIP</small><h3>{coverage}</h3></div>
                <div className="payslip-pay-date"><small>PAY DATE</small><strong>{dateLabel(period?.pay_date)}</strong></div>
            </div>
            <section className="payslip-details" aria-label="Faculty details">
                <div><span>Faculty member</span><strong>{fullName(record.employee)}</strong></div>
                <div><span>Employee number</span><strong>{record.employee?.employee_no}</strong></div>
                <div><span>Days worked</span><strong>{record.total_days_worked}</strong></div>
                <div><span>Payable hours</span><strong>{record.total_hours_worked}</strong></div>
            </section>
            <section className="payslip-breakdown" aria-label="Pay breakdown">
                <h4>Pay breakdown</h4>
                <div><span>Gross pay</span><strong>{money(record.gross_pay)}</strong></div>
                <div><span>Deductions</span><strong>- {money(record.total_deductions)}</strong></div>
                <div><span>Adjustments</span><strong>{money(record.total_adjustments)}</strong></div>
            </section>
            <footer className="payslip-total"><span>NET PAY</span><strong>{money(record.net_pay)}</strong></footer>
        </article>
    </AppLayout>;
}
