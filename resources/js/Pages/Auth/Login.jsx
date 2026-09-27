import { Link, useForm, usePage } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';
import AuthLayout from '../../Layouts/AuthLayout';

export default function Login() {
    const { errors, flash } = usePage().props;
    const [showPassword, setShowPassword] = useState(false);
    const form = useForm({ email: '', password: '' });

    const submit = (event) => {
        event.preventDefault();
        form.post('/login');
    };

    return <AuthLayout title="Sign in">
        <section className="login-auth" aria-labelledby="login-title">
            <span className="login-form-index">SECURE ACCESS</span>
            <h2 id="login-title">Sign in</h2>
            <p>Use your assigned system account.</p>
            {flash?.success && <div className="alert success">{flash.success}</div>}
            {errors?.email && <div className="alert error">{errors.email}</div>}
            <form onSubmit={submit}>
                <label>Email address<input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required autoFocus autoComplete="email" /></label>
                <label>Password
                    <span className="password-wrap">
                        <input type={showPassword ? 'text' : 'password'} value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} required autoComplete="current-password" />
                        <button className="password-toggle" type="button" aria-label={showPassword ? 'Hide password' : 'Show password'} aria-pressed={showPassword} title={showPassword ? 'Hide password' : 'Show password'} onClick={() => setShowPassword(!showPassword)}>{showPassword ? <EyeOff size={19} aria-hidden="true" /> : <Eye size={19} aria-hidden="true" />}</button>
                    </span>
                </label>
                <Link className="forgot-password-link" href="/forgot-password">Forgot password?</Link>
                <button className="full" type="submit" disabled={form.processing}>{form.processing ? 'Signing in...' : 'Sign in to system'}</button>
            </form>
        </section>
    </AuthLayout>;
}
