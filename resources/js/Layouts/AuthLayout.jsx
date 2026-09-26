import { Head } from '@inertiajs/react';
import { CalendarClock, ScanLine, ShieldCheck } from 'lucide-react';

export default function AuthLayout({ title, children }) {
    return <>
        <Head title={`${title} | Payroll System`} />
        <main className="login-page">
            <div className="login-backdrop" />
            <div className="login-overlay" />
            <header className="login-nav">
                <div className="login-nav-brand">
                    <div className="brand-logos"><img src="/images/cvsu-logo.png" alt="Cavite State University logo" /><img src="/images/dcs-logo.png" alt="Department of Computer Studies logo" /></div>
                    <div><strong>CvSU Imus</strong><span>Payroll System</span></div>
                </div>
                <span className="login-campus">Department of Computer Studies</span>
            </header>

            <section className="login-hero">
                <div className="login-copy">
                    <span className="login-eyebrow">Payroll and attendance operations</span>
                    <h1>Payroll<br />&amp; Attendance</h1>
                    <p>A secure workspace for faculty records, schedule-based attendance, and accurate payroll processing.</p>
                </div>
                {children}
            </section>

            <section className="login-proof" aria-label="System capabilities">
                <div><ScanLine size={22} /><span><strong>RFID &amp; Biometrics</strong><small>Reliable attendance capture</small></span></div>
                <div><CalendarClock size={22} /><span><strong>Schedule Based</strong><small>Payable hours from assigned classes</small></span></div>
                <div><ShieldCheck size={22} /><span><strong>Role Secured</strong><small>Access for authorized personnel</small></span></div>
            </section>
        </main>
    </>;
}
