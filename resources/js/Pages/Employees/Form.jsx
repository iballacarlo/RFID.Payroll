import { Link, useForm } from '@inertiajs/react';
import { Fingerprint, Link2Off, Radio, RefreshCw } from 'lucide-react';
import { useRef, useState } from 'react';
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
    const isNew = !employee.id;
    const rfidInput = useRef(null);
    const fingerprintInput = useRef(null);
    const [rfidEditing, setRfidEditing] = useState(isNew);
    const [fingerprintEditing, setFingerprintEditing] = useState(isNew);
    const form = useForm({
        employee_no: employee.employee_no || nextEmployeeNumber || '', first_name: employee.first_name || '', middle_name: employee.middle_name || '', last_name: employee.last_name || '', suffix: employee.suffix || '', email: emailUsername(employee.first_name || '', employee.last_name || ''), contact_no: employee.contact_no || '', highest_educational_attainment: employee.highest_educational_attainment || '', years_of_service: employee.years_of_service ?? '', faculty_rank_id: employee.faculty_rank_id || '', status: employee.status || 'active', contract_start: employee.contract_start || '', contract_end: employee.contract_end || '', rfid_uid: employee.rfid_cards?.[0]?.rfid_uid || '', fingerprint_code: employee.fingerprint_templates?.[0]?.fingerprint_code || '', finger_label: employee.fingerprint_templates?.[0]?.finger_label || '', rfid_reregister: false, fingerprint_reregister: false,
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
    const beginRegistration = (type) => {
        const isRfid = type === 'rfid';
        if (isRfid) {
            setRfidEditing(true);
            form.setData('rfid_reregister', true);
        } else {
            setFingerprintEditing(true);
            form.setData('fingerprint_reregister', true);
        }
        requestAnimationFrame(() => (isRfid ? rfidInput : fingerprintInput).current?.select());
    };
    const unlinkCredential = (type) => {
        if (type === 'rfid') {
            form.setData({ ...form.data, rfid_uid: '', rfid_reregister: true });
            setRfidEditing(true);
        } else {
            form.setData({ ...form.data, fingerprint_code: '', finger_label: '', fingerprint_reregister: true });
            setFingerprintEditing(true);
        }
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
            <div className="form-section-title" id="attendance-identifiers"><span>03</span><div><strong>Attendance identifiers</strong><small>Hardware credentials used for time records</small></div></div>
            <div className="credential-row">
                <div className="credential-heading"><span className="credential-icon"><Radio size={19} /></span><span><strong>RFID card</strong><small>{form.data.rfid_uid ? 'Registered' : 'Not registered'}</small></span></div>
                <label>Card UID<input ref={rfidInput} value={form.data.rfid_uid} readOnly={!rfidEditing} placeholder="Scan or enter card UID" onChange={(e) => form.setData('rfid_uid', e.target.value.trim().toUpperCase())} /></label>
                <div className="credential-actions">
                    <button className="credential-action" type="button" onClick={() => beginRegistration('rfid')}>{form.data.rfid_uid ? <RefreshCw size={16} /> : <Radio size={16} />}{form.data.rfid_uid ? 'Re-register' : 'Register'}</button>
                    {form.data.rfid_uid && <button className="credential-unlink" type="button" onClick={() => unlinkCredential('rfid')} aria-label="Unlink RFID card"><Link2Off size={16} /></button>}
                </div>
            </div>
            <div className="credential-row">
                <div className="credential-heading"><span className="credential-icon"><Fingerprint size={19} /></span><span><strong>Fingerprint</strong><small>{form.data.fingerprint_code ? 'Registered' : 'Not registered'}</small></span></div>
                <label>Template ID<input ref={fingerprintInput} readOnly={!fingerprintEditing} placeholder="Example: FP-1" value={form.data.fingerprint_code} onChange={(e) => form.setData('fingerprint_code', e.target.value.trim().toUpperCase())} /></label>
                <label>Finger<select disabled={!fingerprintEditing} value={form.data.finger_label} onChange={(e) => form.setData('finger_label', e.target.value)}><option value="">Select finger</option><option>Right thumb</option><option>Right index</option><option>Right middle</option><option>Left thumb</option><option>Left index</option><option>Left middle</option></select></label>
                <div className="credential-actions">
                    <button className="credential-action" type="button" onClick={() => beginRegistration('fingerprint')}>{form.data.fingerprint_code ? <RefreshCw size={16} /> : <Fingerprint size={16} />}{form.data.fingerprint_code ? 'Re-register' : 'Register'}</button>
                    {form.data.fingerprint_code && <button className="credential-unlink" type="button" onClick={() => unlinkCredential('fingerprint')} aria-label="Unlink fingerprint"><Link2Off size={16} /></button>}
                </div>
            </div>
            <div className="form-actions"><Link href="/employees">Cancel</Link><button type="submit" disabled={form.processing}>{form.processing ? 'Saving...' : 'Save Faculty'}</button></div>
        </form>
    </AppLayout>;
}
