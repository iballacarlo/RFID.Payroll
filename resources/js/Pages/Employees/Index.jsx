import { Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import Pagination from '../../components/Pagination';
import { fullName, money } from '../../lib/format';

const weeklyHours = (schedules = []) => {
    const minutes = schedules.reduce((total, schedule) => {
        const toMinutes = (time) => {
            const [hours, mins] = String(time).slice(0, 5).split(':').map(Number);
            return (hours * 60) + mins;
        };

        return total + Math.max(0, toMinutes(schedule.end_time) - toMinutes(schedule.start_time));
    }, 0);

    const hours = minutes / 60;
    return Number.isInteger(hours) ? String(hours) : hours.toFixed(1);
};

function SortHeader({ label, sort, activeSort, onSort }) {
    const isActive = activeSort === sort;
    return <th><button className={`table-sort${isActive ? ' is-active' : ''}`} type="button" onClick={() => onSort(sort)}>{label}</button></th>;
}

export default function EmployeeIndex({ employees, filters }) {
    const [search, setSearch] = useState(filters.search || '');
    const remove = (employee) => {
        if (confirm(`Delete ${fullName(employee)}?`)) router.delete(`/employees/${employee.id}`);
    };
    const applySearch = (event) => {
        event.preventDefault();
        const params = { sort: filters.sort, direction: filters.direction };
        if (search.trim()) params.search = search.trim();
        router.get('/employees', params, { preserveScroll: true, preserveState: true, replace: true });
    };
    const sortBy = (sort) => {
        const direction = filters.sort === sort && filters.direction === 'asc' ? 'desc' : 'asc';
        const params = { sort, direction };
        if (search.trim()) params.search = search.trim();
        router.get('/employees', params, { preserveScroll: true, preserveState: true, replace: true });
    };
    return <AppLayout title="Faculty" subtitle="Manage faculty profiles, RFID cards, and fingerprint references.">
        <div className="panel"><div className="panel-heading"><h2>Faculty Records</h2><Link className="button" href="/employees/create"><Plus size={16} />Add Faculty</Link></div>
            <form className="faculty-filters" onSubmit={applySearch}><label>Search Faculty<span className="search-control"><Search size={16} /><input placeholder="Name, employee number, or email" value={search} onChange={(event) => setSearch(event.target.value)} /></span></label><button type="submit">Search</button></form>
            <div className="table-wrap"><table><thead><tr><SortHeader label="Employee No." sort="employee_no" activeSort={filters.sort} onSort={sortBy} /><SortHeader label="Name" sort="name" activeSort={filters.sort} onSort={sortBy} /><th>RFID</th><th>Fingerprint ID</th><SortHeader label="Rate" sort="rate" activeSort={filters.sort} onSort={sortBy} /><SortHeader label="Hours / Week" sort="weekly_hours" activeSort={filters.sort} onSort={sortBy} /><SortHeader label="Status" sort="status" activeSort={filters.sort} onSort={sortBy} /><th /></tr></thead><tbody>
                {employees.data.length ? employees.data.map((employee) => { const rank = employee.faculty_rank; return <tr key={employee.id}><td>{employee.employee_no}</td><td>{fullName(employee)}</td><td>{employee.rfid_cards?.[0]?.rfid_uid || '-'}</td><td>{employee.fingerprint_templates?.[0]?.fingerprint_code || '-'}</td><td>{rank ? <>{rank.name}<br /><small>{money(rank.rate_amount)}/hour</small></> : `${money(employee.rate_amount)}/${employee.rate_type}`}</td><td>{weeklyHours(employee.schedules)} hrs/week</td><td><span className="badge">{employee.status}</span></td><td className="actions"><Link href={`/employees/${employee.id}/edit`}>Profile &amp; IDs</Link><Link href={`/employees/${employee.id}/schedule`}>Schedule</Link><button className="link-danger" type="button" onClick={() => remove(employee)}>Delete</button></td></tr>; }) : <tr><td colSpan="8">No faculty records yet.</td></tr>}
            </tbody></table></div>
            <Pagination links={employees.links} />
        </div>
    </AppLayout>;
}
