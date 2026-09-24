import { Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { fullName } from '../../lib/format';

const days = [[1, 'Monday'], [2, 'Tuesday'], [3, 'Wednesday'], [4, 'Thursday'], [5, 'Friday'], [6, 'Saturday']];
const dayGroups = [days.slice(0, 3), days.slice(3, 6)];
const timeSlots = Array.from({ length: 28 }, (_, index) => {
    const totalMinutes = (7 * 60) + (index * 30);
    const hour = Math.floor(totalMinutes / 60);
    const minute = totalMinutes % 60;

    return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
});

function timeLabel(time) {
    const [hour, minute] = time.split(':').map(Number);
    const suffix = hour >= 12 ? 'PM' : 'AM';

    return `${hour % 12 || 12}:${String(minute).padStart(2, '0')} ${suffix}`;
}

function slotEndTime(slot) {
    const [hour, minute] = slot.split(':').map(Number);
    const next = (hour * 60) + minute + 30;

    return `${String(Math.floor(next / 60)).padStart(2, '0')}:${String(next % 60).padStart(2, '0')}`;
}

export default function EmployeeSchedule({ employee }) {
    const slotsForDay = (day) => (employee.schedules || [])
        .filter((item) => item.schedule_type !== 'consultation')
        .filter((item) => item.day_of_week === day)
        .flatMap((item) => timeSlots.filter((slot) => slot >= item.start_time.slice(0, 5) && slot < item.end_time.slice(0, 5)));
    const consultationSlotsForDay = (day) => (employee.schedules || [])
        .filter((item) => item.schedule_type === 'consultation')
        .filter((item) => item.day_of_week === day)
        .flatMap((item) => timeSlots.filter((slot) => slot >= item.start_time.slice(0, 5) && slot < item.end_time.slice(0, 5)));
    const breakSlotsForDay = (day) => (employee.schedule_breaks || [])
        .filter((item) => item.day_of_week === day)
        .flatMap((item) => timeSlots.filter((slot) => slot >= item.start_time.slice(0, 5) && slot < item.end_time.slice(0, 5)));
    const form = useForm({
        schedule: Object.fromEntries(days.map(([day]) => [day, { slots: slotsForDay(day), break_slots: breakSlotsForDay(day), consultation_slots: consultationSlotsForDay(day) }])),
    });
    const [selectionMode, setSelectionMode] = useState('class');
    const [rangeStart, setRangeStart] = useState(null);
    const [activeGroupIndex, setActiveGroupIndex] = useState(() => {
        const firstScheduledDay = days.findIndex(([day]) => slotsForDay(day).length || breakSlotsForDay(day).length);
        return firstScheduledDay >= 3 ? 1 : 0;
    });
    const activeDays = dayGroups[activeGroupIndex];

    useEffect(() => {
        const toggleBreakMode = (event) => {
            const tag = event.target.tagName;
            if (event.key.toLowerCase() === 'b' && !['INPUT', 'SELECT', 'TEXTAREA'].includes(tag)) {
                event.preventDefault();
                setSelectionMode((mode) => mode === 'lunch_break' ? 'class' : 'lunch_break');
                setRangeStart(null);
            }

            if (event.key.toLowerCase() === 'c' && !['INPUT', 'SELECT', 'TEXTAREA'].includes(tag)) {
                event.preventDefault();
                setSelectionMode((mode) => mode === 'consultation' ? 'class' : 'consultation');
                setRangeStart(null);
            }
        };

        window.addEventListener('keydown', toggleBreakMode);
        return () => window.removeEventListener('keydown', toggleBreakMode);
    }, []);

    const applySlots = (day, slots, mode) => {
        const current = form.data.schedule[day];
        const addTo = (key) => key === mode ? [...new Set([...current[key], ...slots])].sort() : current[key].filter((slot) => !slots.includes(slot));

        form.setData('schedule', { ...form.data.schedule, [day]: {
            slots: addTo('slots'),
            break_slots: addTo('break_slots'),
            consultation_slots: addTo('consultation_slots'),
        } });
    };

    const toggleSlot = (day, slot, event) => {
        const mode = selectionMode === 'lunch_break' ? 'break_slots' : selectionMode === 'consultation' ? 'consultation_slots' : 'slots';
        const current = form.data.schedule[day];
        const activeSlots = current[mode];

        if (event.ctrlKey) {
            if (!rangeStart || rangeStart.day !== day || rangeStart.mode !== mode) {
                setRangeStart({ day, slot, mode });
                return;
            }

            const startIndex = timeSlots.indexOf(rangeStart.slot);
            const endIndex = timeSlots.indexOf(slot);
            const from = Math.min(startIndex, endIndex);
            const to = Math.max(startIndex, endIndex);
            const range = timeSlots.slice(from, to);

            if (range.length) {
                applySlots(day, range, mode);
            }

            setRangeStart(null);
            return;
        }

        const updatedSlots = activeSlots.includes(slot)
            ? activeSlots.filter((item) => item !== slot)
            : [...activeSlots, slot].sort();
        form.setData('schedule', {
            ...form.data.schedule,
            [day]: {
                slots: mode === 'slots' ? updatedSlots : current.slots.filter((item) => item !== slot),
                break_slots: mode === 'break_slots' ? updatedSlots : current.break_slots.filter((item) => item !== slot),
                consultation_slots: mode === 'consultation_slots' ? updatedSlots : current.consultation_slots.filter((item) => item !== slot),
            },
        });
        setRangeStart(null);
    };
    const clearSchedule = () => {
        form.setData('schedule', Object.fromEntries(days.map(([day]) => [day, { slots: [], break_slots: [], consultation_slots: [] }])));
        setRangeStart(null);
    };
    const submit = (event) => {
        event.preventDefault();
        form.put(`/employees/${employee.id}/schedule`);
    };

    const moveDayGroup = (direction) => {
        setActiveGroupIndex((index) => Math.min(dayGroups.length - 1, Math.max(0, index + direction)));
        setRangeStart(null);
    };

    return <AppLayout title="Manage Schedule" subtitle={`${fullName(employee)} - ${employee.employee_no}`}>
        <form className="panel schedule-page" onSubmit={submit}>
            <div className="schedule-heading"><div><h2>Weekly Class Schedule</h2><p>Click to mark class time. Press <strong>B</strong> for lunch break or <strong>C</strong> for consultation. Hold <strong>Ctrl</strong>, click start then end time to fill a range.</p></div><div className="schedule-controls"><button type="button" className={`break-mode-button${selectionMode === 'lunch_break' ? ' is-active' : ''}`} onClick={() => { setSelectionMode((mode) => mode === 'lunch_break' ? 'class' : 'lunch_break'); setRangeStart(null); }}>B Lunch Break</button><button type="button" className={`consultation-mode-button${selectionMode === 'consultation' ? ' is-active' : ''}`} onClick={() => { setSelectionMode((mode) => mode === 'consultation' ? 'class' : 'consultation'); setRangeStart(null); }}>C Consultation</button><button type="button" className="link-danger" onClick={clearSchedule}>Clear schedule</button></div></div>
            <div className="schedule-legend"><span><i className="legend-work" />Class time</span><span><i className="legend-lunch" />Lunch break</span><span><i className="legend-consultation" />Consultation hours</span>{rangeStart && <span className="range-status">Range start: {timeLabel(rangeStart.slot)}. Hold Ctrl and select the end time.</span>}</div>
            <div className="day-navigator"><button type="button" className="day-arrow" onClick={() => moveDayGroup(-1)} disabled={activeGroupIndex === 0} aria-label="Previous days" title="Previous days">&larr;</button><div><strong>{activeDays[0][1]} - {activeDays[2][1]}</strong><span>Schedule set {activeGroupIndex + 1} of {dayGroups.length}</span></div><button type="button" className="day-arrow" onClick={() => moveDayGroup(1)} disabled={activeGroupIndex === dayGroups.length - 1} aria-label="Next days" title="Next days">&rarr;</button></div>
            <div className="timetable-wrap"><div className="timetable three-day-timetable"><div className="timetable-row timetable-header"><div className="time-group">Time</div>{activeDays.map(([, name]) => <div key={name}>{name}</div>)}</div>{timeSlots.map((slot) => <div className="timetable-row" key={slot}><div className="time-label">{timeLabel(slot)}</div><div className="time-label">{timeLabel(slotEndTime(slot))}</div>{activeDays.map(([day, name]) => { const selected = form.data.schedule[day].slots.includes(slot); const isLunch = form.data.schedule[day].break_slots.includes(slot); const isConsultation = form.data.schedule[day].consultation_slots.includes(slot); const isAnchor = rangeStart?.day === day && rangeStart?.slot === slot && rangeStart?.mode === (selectionMode === 'lunch_break' ? 'break_slots' : selectionMode === 'consultation' ? 'consultation_slots' : 'slots'); const state = isLunch ? ' is-lunch' : isConsultation ? ' is-consultation' : selected ? ' is-selected' : ''; return <button key={day} type="button" className={`schedule-slot${state}${isAnchor ? ' is-range-anchor' : ''}`} aria-pressed={selected || isLunch || isConsultation} aria-label={`${name} ${timeLabel(slot)} to ${timeLabel(slotEndTime(slot))}`} onClick={(event) => toggleSlot(day, slot, event)} />; })}</div>)}</div></div>
            <div className="form-actions"><Link href="/employees">Back to Faculty</Link><button type="submit" disabled={form.processing}>{form.processing ? 'Saving...' : 'Save Schedule'}</button></div>
        </form>
    </AppLayout>;
}
