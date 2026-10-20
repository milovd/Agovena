/*
 * Calendar popover for date and datetime fields (shadcn Calendar / Date Picker look).
 *
 * Progressive enhancement around <x-ag.date-input>: the native input stays the
 * source of truth (typing keeps working, wire:model reads it). The popover is
 * appended to document.body, outside the Livewire tree, so morphs never remove
 * it. Clicks are handled through document-level delegation, so freshly morphed
 * or navigated markup needs no re-initialisation.
 */

const READY_CLASS = 'ag-calendar-ready';
const SMALL_SCREEN = '(max-width: 640px)';
const GAP = 4;
const VIEWPORT_MARGIN = 8;

const CHEVRON_LEFT = '<path d="m15 18-6-6 6-6"/>';
const CHEVRON_RIGHT = '<path d="m9 18 6-6-6-6"/>';

let state = null;
let idCounter = 0;

function locale() {
    return document.documentElement.lang || undefined;
}

function pad(value) {
    return String(value).padStart(2, '0');
}

/* Dates are plain UTC midnights so day arithmetic never trips over DST. */
function makeDate(year, month, day) {
    return new Date(Date.UTC(year, month, day));
}

function today() {
    const now = new Date();
    return makeDate(now.getFullYear(), now.getMonth(), now.getDate());
}

function toYmd(date) {
    return `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}`;
}

function parseYmd(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value || '');
    if (!match) return null;

    const date = makeDate(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    return toYmd(date) === match[0] ? date : null;
}

function addDays(date, days) {
    return makeDate(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate() + days);
}

function addMonths(date, months) {
    const year = date.getUTCFullYear();
    const month = date.getUTCMonth() + months;
    const lastDay = makeDate(year, month + 1, 0).getUTCDate();

    return makeDate(year, month, Math.min(date.getUTCDate(), lastDay));
}

function startOfMonth(date) {
    return makeDate(date.getUTCFullYear(), date.getUTCMonth(), 1);
}

/* Monday = 0 ... Sunday = 6. */
function weekdayIndex(date) {
    return (date.getUTCDay() + 6) % 7;
}

function sameDay(a, b) {
    return Boolean(a && b) && a.getTime() === b.getTime();
}

function format(date, options) {
    return new Intl.DateTimeFormat(locale(), { timeZone: 'UTC', ...options }).format(date);
}

function svg(paths) {
    return `<svg class="ag-icon" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">${paths}</svg>`;
}

function element(tag, className, attributes = {}) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    Object.entries(attributes).forEach(([name, value]) => node.setAttribute(name, value));

    return node;
}

function inputFor(trigger) {
    return trigger.closest('.ag-date-input')?.querySelector('input') ?? null;
}

function bounds(input) {
    return { min: parseYmd(input.min), max: parseYmd(input.max) };
}

function isOutOfRange(date, range) {
    return Boolean((range.min && date < range.min) || (range.max && date > range.max));
}

function clampToRange(date, range) {
    if (range.min && date < range.min) return range.min;
    if (range.max && date > range.max) return range.max;

    return date;
}

function buildPopover(trigger) {
    idCounter += 1;
    const captionId = `ag-calendar-caption-${idCounter}`;

    const popover = element('div', 'ag-calendar', {
        role: 'dialog',
        'aria-label': trigger.dataset.agCalendarLabel || 'Calendar',
    });

    const header = element('div', 'ag-calendar__header');
    const previous = element('button', 'ag-calendar__nav ag-calendar__nav--previous', {
        type: 'button',
        'aria-label': trigger.dataset.agCalendarPrevious || 'Previous month',
        'data-ag-calendar-step': '-1',
    });
    previous.innerHTML = svg(CHEVRON_LEFT);

    const caption = element('div', 'ag-calendar__caption', { id: captionId, 'aria-live': 'polite' });

    const next = element('button', 'ag-calendar__nav ag-calendar__nav--next', {
        type: 'button',
        'aria-label': trigger.dataset.agCalendarNext || 'Next month',
        'data-ag-calendar-step': '1',
    });
    next.innerHTML = svg(CHEVRON_RIGHT);

    header.append(previous, caption, next);

    const grid = element('table', 'ag-calendar__grid', { role: 'grid', 'aria-labelledby': captionId });
    const head = element('thead');
    const headRow = element('tr');
    const monday = makeDate(2024, 0, 1);
    for (let index = 0; index < 7; index += 1) {
        const day = addDays(monday, index);
        const long = format(day, { weekday: 'long' });
        const cell = element('th', 'ag-calendar__weekday', { scope: 'col', abbr: long });
        const short = element('span', '', { 'aria-hidden': 'true' });
        short.textContent = format(day, { weekday: 'short' }).replace('.', '').slice(0, 2);
        const label = element('span', 'ag-calendar__sr');
        label.textContent = long;
        cell.append(short, label);
        headRow.append(cell);
    }
    head.append(headRow);

    const body = element('tbody');
    grid.append(head, body);
    popover.append(header, grid);

    return { popover, caption, body, previous, next };
}

function renderMonth() {
    const { parts, input, focusDate } = state;
    const range = bounds(input);
    const selected = parseYmd(input.value);
    const now = today();
    const first = startOfMonth(focusDate);
    const month = first.getUTCMonth();

    parts.caption.textContent = format(first, { month: 'long', year: 'numeric' });
    parts.previous.disabled = Boolean(range.min && addDays(first, -1) < range.min);
    parts.next.disabled = Boolean(range.max && makeDate(first.getUTCFullYear(), month + 1, 1) > range.max);

    const rows = [];
    let cursor = addDays(first, -weekdayIndex(first));
    do {
        const row = element('tr');
        for (let index = 0; index < 7; index += 1) {
            const isSelected = sameDay(cursor, selected);
            const cell = element('td', 'ag-calendar__cell', {
                role: 'gridcell',
                'aria-selected': isSelected ? 'true' : 'false',
            });
            const day = element('button', 'ag-calendar__day', {
                type: 'button',
                tabindex: sameDay(cursor, focusDate) ? '0' : '-1',
                'data-ag-calendar-date': toYmd(cursor),
                'aria-label': format(cursor, { dateStyle: 'full' }),
            });
            day.textContent = String(cursor.getUTCDate());
            if (cursor.getUTCMonth() !== month) day.classList.add('is-outside');
            if (isSelected) day.classList.add('is-selected');
            if (sameDay(cursor, now)) {
                day.classList.add('is-today');
                day.setAttribute('aria-current', 'date');
            }
            if (isOutOfRange(cursor, range)) day.setAttribute('aria-disabled', 'true');
            cell.append(day);
            row.append(cell);
            cursor = addDays(cursor, 1);
        }
        rows.push(row);
    } while (cursor.getUTCMonth() === month);

    parts.body.replaceChildren(...rows);
}

function focusDay() {
    state?.parts.body.querySelector('[tabindex="0"]')?.focus({ preventScroll: true });
}

function moveFocus(date, { focus = true } = {}) {
    state.focusDate = clampToRange(date, bounds(state.input));
    renderMonth();
    if (focus) focusDay();
    position();
}

function position() {
    if (!state) return;
    const { popover, input } = state;

    if (!input.isConnected) {
        close({ restoreFocus: false });
        return;
    }

    const small = window.matchMedia(SMALL_SCREEN).matches;
    popover.classList.toggle('ag-calendar--centered', small);
    if (small) {
        popover.style.top = '';
        popover.style.left = '';
        return;
    }

    const anchor = input.getBoundingClientRect();
    const width = popover.offsetWidth;
    const height = popover.offsetHeight;
    const roomBelow = window.innerHeight - anchor.bottom - GAP - VIEWPORT_MARGIN;
    const roomAbove = anchor.top - GAP - VIEWPORT_MARGIN;
    const above = height > roomBelow && roomAbove > roomBelow;

    const top = above ? anchor.top - GAP - height : anchor.bottom + GAP;
    const maxLeft = window.innerWidth - width - VIEWPORT_MARGIN;
    const left = Math.max(VIEWPORT_MARGIN, Math.min(anchor.left, maxLeft));

    popover.classList.toggle('ag-calendar--above', above);
    popover.style.top = `${Math.round(top + window.scrollY)}px`;
    popover.style.left = `${Math.round(left + window.scrollX)}px`;
}

function currentTrigger(closing) {
    if (closing.trigger.isConnected) return closing.trigger;

    return closing.input.closest('.ag-date-input')?.querySelector('[data-ag-calendar-trigger]') ?? null;
}

function close({ restoreFocus = true } = {}) {
    if (!state) return;
    const closing = state;
    state = null;

    const trigger = currentTrigger(closing);
    closing.popover.remove();
    window.removeEventListener('resize', position);
    window.removeEventListener('scroll', position, true);
    trigger?.setAttribute('aria-expanded', 'false');
    if (restoreFocus) trigger?.focus({ preventScroll: true });
}

function open(trigger) {
    const input = inputFor(trigger);
    if (!input || input.disabled || input.readOnly) return;

    const parts = buildPopover(trigger);
    state = {
        trigger,
        input,
        parts,
        popover: parts.popover,
        focusDate: clampToRange(parseYmd(input.value) ?? today(), bounds(input)),
    };

    parts.popover.addEventListener('click', onPopoverClick);
    parts.popover.addEventListener('keydown', onPopoverKeydown);
    document.body.append(parts.popover);
    renderMonth();
    position();
    window.addEventListener('resize', position);
    window.addEventListener('scroll', position, true);
    trigger.setAttribute('aria-expanded', 'true');
    focusDay();
}

function select(date) {
    const { input } = state;
    let value = toYmd(date);

    if (input.type === 'datetime-local') {
        const time = /T(\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?)/.exec(input.value)?.[1] ?? '00:00';
        value = `${value}T${time}`;
    }

    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
    close();
}

/* Day buttons are native buttons, so Enter and Space arrive here as clicks. */
function onPopoverClick(event) {
    if (!state) return;

    const step = event.target.closest('[data-ag-calendar-step]');
    if (step) {
        if (!step.disabled) moveFocus(addMonths(state.focusDate, Number(step.dataset.agCalendarStep)), { focus: false });
        return;
    }

    const day = event.target.closest('[data-ag-calendar-date]');
    if (!day || day.getAttribute('aria-disabled') === 'true') return;

    select(parseYmd(day.dataset.agCalendarDate));
}

function trapTab(event) {
    const focusable = [state.parts.previous, state.parts.next, state.parts.body.querySelector('[tabindex="0"]')]
        .filter((node) => node && !node.disabled);
    if (focusable.length === 0) return;

    const index = focusable.indexOf(document.activeElement);
    let nextIndex = index + 1;
    if (event.shiftKey) nextIndex = index <= 0 ? focusable.length - 1 : index - 1;
    else if (index === -1 || nextIndex >= focusable.length) nextIndex = 0;

    event.preventDefault();
    focusable[nextIndex].focus();
}

function onPopoverKeydown(event) {
    if (!state) return;

    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        close();
        return;
    }

    if (event.key === 'Tab') {
        trapTab(event);
        return;
    }

    if (!event.target.closest('[data-ag-calendar-date]')) return;

    const current = state.focusDate;
    const moves = {
        ArrowLeft: () => addDays(current, -1),
        ArrowRight: () => addDays(current, 1),
        ArrowUp: () => addDays(current, -7),
        ArrowDown: () => addDays(current, 7),
        PageUp: () => addMonths(current, event.shiftKey ? -12 : -1),
        PageDown: () => addMonths(current, event.shiftKey ? 12 : 1),
        Home: () => addDays(current, -weekdayIndex(current)),
        End: () => addDays(current, 6 - weekdayIndex(current)),
    };

    if (moves[event.key]) {
        event.preventDefault();
        moveFocus(moves[event.key]());
    }
}

function onDocumentClick(event) {
    const trigger = event.target.closest?.('[data-ag-calendar-trigger]');
    if (!trigger) return;

    event.preventDefault();
    const reopen = !state || state.trigger !== trigger;
    close({ restoreFocus: !reopen });
    if (reopen) open(trigger);
}

function onDocumentPointerDown(event) {
    if (!state) return;
    if (state.popover.contains(event.target)) return;
    if (event.target.closest?.('[data-ag-calendar-trigger]') === state.trigger) return;

    close({ restoreFocus: false });
}

function markReady() {
    document.documentElement.classList.add(READY_CLASS);
}

export function registerCalendar() {
    if (window.agCalendarRegistered) return;
    // Keep one native picker when the browser cannot hide its built-in calendar button.
    if (!window.CSS?.supports?.('selector(input::-webkit-calendar-picker-indicator)')) return;
    window.agCalendarRegistered = true;

    markReady();
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('pointerdown', onDocumentPointerDown, true);
    document.addEventListener('livewire:navigating', () => close({ restoreFocus: false }));
    document.addEventListener('livewire:navigated', markReady);
}
