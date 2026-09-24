import { Link, usePage } from '@inertiajs/react';
import { DatabaseBackup, Users } from 'lucide-react';

export default function SettingsTabs() {
    const { url } = usePage();
    return <nav className="settings-tabs" aria-label="Settings sections">
        <Link href="/settings/accounts" className={url.startsWith('/settings/accounts') ? 'active' : ''}><Users size={17} />Accounts</Link>
        <Link href="/settings/backup" className={url.startsWith('/settings/backup') ? 'active' : ''}><DatabaseBackup size={17} />Backup &amp; Recovery</Link>
    </nav>;
}
