import { Head, useForm, usePage } from '@inertiajs/react';
import { CalendarClock, ScanLine, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

export default function Login() {
    const { errors } = usePage().props;
    const [showPassword, setShowPassword] = useState(false);
    const form = useForm({ email: '', password: '' });

    const submit = (event) => {
        event.preventDefault();
        form.post('/login');
    };

    return <>
        <Head title="Sign in | Payroll System" />
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

                <section className="login-auth" aria-labelledby="login-title">
                    <span className="login-form-index">SECURE ACCESS</span>
                    <h2 id="login-title">Sign in</h2>
                    <p>Use your assigned system account.</p>
                    {errors?.email && <div className="alert error">{errors.email}</div>}
                    <form onSubmit={submit}>
                        <label>Email address<input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required autoFocus autoComplete="email" /></label>
                        <label>Password
                            <span className="password-wrap">
                                <input type={showPassword ? 'text' : 'password'} value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} required autoComplete="current-password" />
                                <button className="password-toggle" type="button" aria-label={showPassword ? 'Hide password' : 'Show password'} aria-pressed={showPassword} onClick={() => setShowPassword(!showPassword)}><span className="eye-icon" /></button>
                            </span>
                        </label>
                        <button className="full" type="submit" disabled={form.processing}>{form.processing ? 'Signing in...' : 'Sign in to system'}</button>
                    </form>
                </section>
            </section>

            <section className="login-proof" aria-label="System capabilities">
                <div><ScanLine size={22} /><span><strong>RFID &amp; Biometrics</strong><small>Reliable attendance capture</small></span></div>
                <div><CalendarClock size={22} /><span><strong>Schedule Based</strong><small>Payable hours from assigned classes</small></span></div>
                <div><ShieldCheck size={22} /><span><strong>Role Secured</strong><small>Access for authorized personnel</small></span></div>
            </section>
        </main>
    </>;
}
