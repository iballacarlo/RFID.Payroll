import { Link, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

function emailUsername(firstName, lastName) {
    const clean = (value) => value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]/g, '');

    const first = clean(firstName);
    const last = clean(lastName);

    return first && last ? `${first}.${last}` : '';
}

export default function EmployeeForm({ employee, ranks, nextEmployeeNumber }) {
    const form = useForm({
        employee_no: employee.employee_no || nextEmployeeNumber || '', first_name: employee.first_name || '', middle_name: employee.middle_name || '', last_name: employee.last_name || '', suffix: employee.suffix || '', email: emailUsername(employee.first_name || '', employee.last_name || ''), contact_no: employee.contact_no || '', highest_educational_attainment: employee.highest_educational_attainment || '', years_of_service: employee.years_of_service ?? '', faculty_rank_id: employee.faculty_rank_id || '', status: employee.status || 'active', contract_start: employee.contract_start || '', contract_end: employee.contract_end || '', rfid_uid: employee.rfid_cards?.[0]?.rfid_uid || '', fingerprint_code: employee.fingerprint_templates?.[0]?.fingerprint_code || '', finger_label: employee.fingerprint_templates?.[0]?.finger_label || '',
    });
    const updateName = (field, value) => {
        const updated = { ...form.data, [field]: value };
        form.setData({ ...updated, email: emailUsername(updated.first_name, updated.last_name) });
    };
    const submit = (event) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, email: data.email ? `${data.email}@cvsu.edu.ph` : null }));
        employee.id ? form.put(`/employees/${employee.id}`) : form.post('/employees');
    };
    return <AppLayout title={employee.id ? 'Edit Faculty' : 'Add Faculty'} subtitle="Register faculty information and device identifiers.">
        <form className="panel form-grid" onSubmit={submit}>
            <div className="form-section-title"><span>01</span><div><strong>Personal information</strong><small>Faculty identity and contact details</small></div></div>
            <label>Employee Number<input value={form.data.employee_no} readOnly aria-readonly="true" /></label>
            <label>First Name<input value={form.data.first_name} onChange={(e) => updateName('first_name', e.target.value)} required /></label>
            <label>Middle Name<input value={form.data.middle_name} onChange={(e) => form.setData('middle_name', e.target.value)} /></label>
            <label>Last Name<input value={form.data.last_name} onChange={(e) => updateName('last_name', e.target.value)} required /></label>
            <label>Suffix (optional)<input value={form.data.suffix} maxLength="20" placeholder="Jr., III" onChange={(e) => form.setData('suffix', e.target.value)} /></label>
            <label>Email<div className="email-input"><input type="text" value={form.data.email} readOnly aria-readonly="true" /><span>@cvsu.edu.ph</span></div></label>
            <label>Contact Number<input value={form.data.contact_no} onChange={(e) => form.setData('contact_no', e.target.value)} /></label>
            <div className="form-section-title"><span>02</span><div><strong>Employment details</strong><small>Academic profile, rank, and contract</small></div></div>
            <label>Highest Educational Attainment<input value={form.data.highest_educational_attainment} onChange={(e) => form.setData('highest_educational_attainment', e.target.value)} /></label>
            <label>Years of Service<input type="number" min="0" max="99.99" step="0.01" value={form.data.years_of_service} onChange={(e) => form.setData('years_of_service', e.target.value)} /></label>
            <label>Faculty Rank<select value={form.data.faculty_rank_id} onChange={(e) => form.setData('faculty_rank_id', e.target.value)} required><option value="">Select rank</option>{ranks.map((rank) => <option key={rank.id} value={rank.id}>{rank.name} - PHP {Number(rank.rate_amount).toFixed(2)}/hour</option>)}</select></label>
            <label>Status<select value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}><option value="active">Active</option><option value="inactive">Inactive</option></select></label>
            <label>Contract Start<input type="date" value={form.data.contract_start || ''} onChange={(e) => form.setData('contract_start', e.target.value)} /></label>
            <label>Contract End<input type="date" value={form.data.contract_end || ''} onChange={(e) => form.setData('contract_end', e.target.value)} /></label>
            <div className="form-section-title"><span>03</span><div><strong>Attendance identifiers</strong><small>Hardware credentials used for time records</small></div></div>
            <label>RFID UID<input value={form.data.rfid_uid} onChange={(e) => form.setData('rfid_uid', e.target.value.toUpperCase())} /></label>
            <label>Fingerprint ID<input placeholder="Example: FP-1" value={form.data.fingerprint_code} onChange={(e) => form.setData('fingerprint_code', e.target.value.toUpperCase())} /></label>
            <label>Finger Label<input value={form.data.finger_label} onChange={(e) => form.setData('finger_label', e.target.value)} /></label>
            <div className="form-actions"><Link href="/employees">Cancel</Link><button type="submit" disabled={form.processing}>{form.processing ? 'Saving...' : 'Save Faculty'}</button></div>
        </form>
    </AppLayout>;
}
