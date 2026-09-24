import { useForm } from '@inertiajs/react';
import { DatabaseBackup, Download, RotateCcw } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import SettingsTabs from '../../components/SettingsTabs';

export default function Backup() {
    const form = useForm({ backup_file: null });
    const restore = (event) => {
        event.preventDefault();
        if (!form.data.backup_file || !window.confirm('Restore this backup? Current system data will be replaced.')) return;
        form.post('/settings/backup/restore', { forceFormData: true });
    };

    return <AppLayout title="Settings" subtitle="Manage system accounts, backups, and data recovery.">
        <SettingsTabs />
        <section className="settings-grid">
            <article className="settings-tool">
                <span className="settings-tool-icon"><DatabaseBackup size={22} /></span>
                <div><span className="bento-eyebrow">Backup</span><h2>Download system data</h2><p>Save faculty, attendance, schedules, accounts, and payroll records in one backup file.</p></div>
                <a className="button" href="/settings/backup/download"><Download size={17} />Download backup</a>
            </article>
            <article className="settings-tool recovery-tool">
                <span className="settings-tool-icon"><RotateCcw size={22} /></span>
                <div><span className="bento-eyebrow">Recovery</span><h2>Restore a backup</h2><p>Upload a backup created by this system. Existing records will be replaced.</p></div>
                <form onSubmit={restore}>
                    <label>Backup file<input type="file" accept="application/json,.json" onChange={(event) => form.setData('backup_file', event.target.files[0] || null)} required /></label>
                    {form.errors.backup_file && <div className="form-note">{form.errors.backup_file}</div>}
                    <button type="submit" disabled={form.processing || !form.data.backup_file}><RotateCcw size={17} />{form.processing ? 'Restoring...' : 'Restore backup'}</button>
                </form>
            </article>
        </section>
    </AppLayout>;
}
