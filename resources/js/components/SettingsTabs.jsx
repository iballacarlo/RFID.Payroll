import { Link, usePage } from '@inertiajs/react';
import { CalendarRange, DatabaseBackup, Users } from 'lucide-react';

export default function SettingsTabs() {
    const { url } = usePage();
    return <nav className="settings-tabs" aria-label="Settings sections">
        <Link href="/settings/general" className={url.startsWith('/settings/general') ? 'active' : ''}><CalendarRange size={17} />General</Link>
        <Link href="/settings/accounts" className={url.startsWith('/settings/accounts') ? 'active' : ''}><Users size={17} />Accounts</Link>
        <Link href="/settings/backup" className={url.startsWith('/settings/backup') ? 'active' : ''}><DatabaseBackup size={17} />Backup &amp; Recovery</Link>
    </nav>;
}
