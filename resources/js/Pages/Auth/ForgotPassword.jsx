import { Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Mail } from 'lucide-react';
import AuthLayout from '../../Layouts/AuthLayout';

export default function ForgotPassword() {
    const { errors, flash } = usePage().props;
    const form = useForm({ email: '' });

    const submit = (event) => {
        event.preventDefault();
        form.post('/forgot-password');
    };

    return <AuthLayout title="Forgot password">
        <section className="login-auth" aria-labelledby="forgot-password-title">
            <span className="login-form-index">ACCOUNT RECOVERY</span>
            <h2 id="forgot-password-title">Forgot password?</h2>
            <p>Enter your account email and we will send a secure reset link.</p>
            {flash?.success && <div className="alert success">{flash.success}</div>}
            {errors?.email && <div className="alert error">{errors.email}</div>}
            <form onSubmit={submit}>
                <label>Email address<input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required autoFocus autoComplete="email" /></label>
                <button className="full" type="submit" disabled={form.processing}><Mail size={17} />{form.processing ? 'Sending...' : 'Send reset link'}</button>
                <Link className="auth-back-link" href="/login"><ArrowLeft size={16} />Back to sign in</Link>
            </form>
        </section>
    </AuthLayout>;
}
