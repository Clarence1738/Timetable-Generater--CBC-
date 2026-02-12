// Shared page scripts for timetable and teachers views.
(function () {
    const currentPath = window.location.pathname || "";
    const pagesIndex = currentPath.indexOf("/Pages/");
    const rootPath = pagesIndex >= 0 ? currentPath.slice(0, pagesIndex) : "";
    const baseUrl = `${rootPath}/backend/public/index.php`;

    function setButtonLoading(button, isLoading, loadingText = 'Loading...') {
        if (!button) {
            return;
        }

        if (isLoading) {
            if (!button.dataset.originalText) {
                button.dataset.originalText = button.innerHTML;
            }
            button.disabled = true;
            button.innerHTML = `
                <span class="btn-spinner" aria-hidden="true">⏳</span>
                <span>${loadingText}</span>
            `;
            return;
        }

        button.disabled = false;
        if (button.dataset.originalText) {
            button.innerHTML = button.dataset.originalText;
            delete button.dataset.originalText;
        }
    }

    async function ensureAuth() {
        try {
            const authRes = await fetch(`${baseUrl}/auth/check`, { credentials: "include" });
            const auth = await authRes.json();
            if (!auth.authenticated) {
                window.location.replace("../login.html");
                return false;
            }
            return true;
        } catch (error) {
            console.error('Failed to validate session', error);
            return false;
        }
    }

    function toSubjectCode(subject) {
        if (!subject) {
            return '';
        }
        const trimmed = String(subject).trim();
        if (!trimmed) {
            return '';
        }
        const parts = trimmed.split(/\s+/).filter(Boolean);
        if (parts.length > 1) {
            return parts.map((part) => part[0].toUpperCase()).join('');
        }
        return trimmed.slice(0, 3).toUpperCase();
    }

    function buildTimeSlots(periodsPerDay) {
        const allTimeSlots = [
            "8:00-8:40",
            "8:40-9:20",
            "9:30-10:10",
            "10:10-10:50",
            "11:20-12:00",
            "12:00-12:40",
            "2:00-2:40",
            "2:40-3:20",
            "3:20-4:00",
        ];
        const timeSlots = allTimeSlots.slice(0, periodsPerDay);
        const periods = timeSlots.length;

        const allBreaks = [
            { after: 2, time: "9:20-9:30", label: "SHORT BREAK" },
            { after: 4, time: "10:50-11:20", label: "LONG BREAK" },
            { after: 6, time: "12:40-2:00", label: "LUNCH BREAK" },
        ];
        const breaks = allBreaks.filter(b => b.after < periods);
        const breakMap = breaks.reduce((acc, item) => {
            acc[item.after] = item;
            return acc;
        }, {});

        const columns = [];
        for (let i = 1; i <= periods; i++) {
            columns.push({ type: 'period', period: i, label: timeSlots[i - 1] });
            if (breakMap[i]) {
                columns.push({ type: 'break', label: breakMap[i].label, time: breakMap[i].time });
            }
        }

        return { columns, periods, timeSlots };
    }

    function renderClassTimetable(data, timetableView) {
        const days = data.days || [];
        const entries = data.entries || [];
        const streamInfo = data.class || {};
        const periodsPerDay = data.periods_per_day || 8;

        if (!entries.length) {
            timetableView.textContent = 'No timetable entries found for this class.';
            return;
        }

        const map = {};
        entries.forEach((entry) => {
            map[`${entry.day}-${entry.period}`] = entry;
        });

        const { columns } = buildTimeSlots(periodsPerDay);
        const headerCells = ['<th>DAY</th>']
            .concat(columns.map((col) => `<th>${col.type === 'period' ? col.label : col.time}</th>`))
            .join('');

        const dayCount = days.length || 1;
        let body = '';
        const headerHtml = `<div style="margin-bottom: 16px;"><h3>Grade ${streamInfo.grade_number || streamInfo.grade_id} ${streamInfo.name}</h3></div>`;

        days.forEach((day, dayIndex) => {
            let row = `<tr><td><strong>${day.toUpperCase().slice(0, 3)}</strong></td>`;
            columns.forEach((col) => {
                if (col.type === 'break') {
                    if (dayIndex === 0) {
                        row += `
                            <td class="break-cell" rowspan="${dayCount}">
                                <div class="vertical">${col.label}</div>
                            </td>
                        `;
                    }
                    return;
                }
                const entry = map[`${day}-${col.period}`];
                if (!entry) {
                    row += '<td></td>';
                    return;
                }
                row += `
                    <td class="tt-cell">
                        <div class="subject">${toSubjectCode(entry.subject)}</div>
                        <div class="teacher">${entry.examiner || ''}</div>
                    </td>
                `;
            });
            row += '</tr>';
            body += row;
        });

        const tableHtml = `
            <table class="tt-grid">
                <thead><tr>${headerCells}</tr></thead>
                <tbody>${body}</tbody>
            </table>
        `;

        timetableView.innerHTML = headerHtml + tableHtml;
    }

    function renderTeacherTimetable(data, timetableView) {
        const days = data.days || [];
        const entries = data.entries || [];
        const teacher = data.teacher || {};
        const periodsPerDay = data.periods_per_day || 8;

        if (!entries.length) {
            timetableView.textContent = 'No timetable entries found for this teacher.';
            return;
        }

        const map = {};
        entries.forEach((entry) => {
            map[`${entry.day}-${entry.period}`] = entry;
        });

        const { columns } = buildTimeSlots(periodsPerDay);
        const headerCells = ['<th>DAY</th>']
            .concat(columns.map((col) => `<th>${col.type === 'period' ? col.label : col.time}</th>`))
            .join('');

        const dayCount = days.length || 1;
        let body = '';
        const headerHtml = `<div style="margin-bottom: 16px;"><h3>${teacher.name || 'Teacher Timetable'}</h3></div>`;

        const toClassLabel = (entry) => {
            const grade = entry.grade_number || entry.grade_id || '';
            const stream = entry.stream_name || '';
            if (!grade && !stream) {
                return '';
            }
            return `G${grade} ${stream}`.trim();
        };

        days.forEach((day, dayIndex) => {
            let row = `<tr><td><strong>${day.toUpperCase().slice(0, 3)}</strong></td>`;
            columns.forEach((col) => {
                if (col.type === 'break') {
                    if (dayIndex === 0) {
                        row += `
                            <td class="break-cell" rowspan="${dayCount}">
                                <div class="vertical">${col.label}</div>
                            </td>
                        `;
                    }
                    return;
                }
                const entry = map[`${day}-${col.period}`];
                if (!entry) {
                    row += '<td></td>';
                    return;
                }
                row += `
                    <td class="tt-cell">
                        <div class="subject">${toSubjectCode(entry.subject)}</div>
                        <div class="class-name">${toClassLabel(entry)}</div>
                    </td>
                `;
            });
            row += '</tr>';
            body += row;
        });

        const tableHtml = `
            <table class="tt-grid">
                <thead><tr>${headerCells}</tr></thead>
                <tbody>${body}</tbody>
            </table>
        `;

        timetableView.innerHTML = headerHtml + tableHtml;
    }

    async function initTimetableSettingsPage() {
        const periodsLevel1 = document.getElementById('periodsLevel1');
        const periodsLevel2 = document.getElementById('periodsLevel2');
        const periodsLevel3 = document.getElementById('periodsLevel3');
        const saveConfigBtn = document.getElementById('saveConfigBtn');
        const gradeSelect = document.getElementById('gradeSelect');
        const viewGradeSelect = document.getElementById('viewGradeSelect');
        const viewStreamSelect = document.getElementById('viewStreamSelect');
        const loadRequirementsBtn = document.getElementById('loadRequirementsBtn');
        const saveRequirementsBtn = document.getElementById('saveRequirementsBtn');
        const requirementsTableWrap = document.getElementById('requirementsTableWrap');
        const generateBtn = document.getElementById('generateBtn');
        const viewBtn = document.getElementById('viewBtn');
        const printBtn = document.getElementById('printBtn');
        const timetableView = document.getElementById('timetableView');

        if (!periodsLevel1 || !timetableView) {
            return;
        }

        const authed = await ensureAuth();
        if (!authed) {
            return;
        }

        async function loadConfig() {
            try {
                const res = await fetch(`${baseUrl}/timetable/config`, { credentials: 'include' });
                const data = await res.json();
                if (data.success && data.data) {
                    const levels = data.data.levels || {};
                    periodsLevel1.value = levels['1'] || 8;
                    periodsLevel2.value = levels['2'] || 8;
                    periodsLevel3.value = levels['3'] || 8;
                }
            } catch (error) {
                console.error('Failed to load config', error);
            }
        }

        async function loadGrades() {
            try {
                const res = await fetch(`${baseUrl}/settings/grades`, { credentials: 'include' });
                const data = await res.json();
                if (!data.success || !data.data) {
                    return;
                }
                const gradeOptions = data.data
                    .map((row) => `<option value="${row.grade_id}">Grade ${row.grade}</option>`)
                    .join('');
                gradeSelect.insertAdjacentHTML('beforeend', gradeOptions);

                const viewGradeOptions = data.data
                    .map((row) => `<option value="${row.grade}">${row.name || 'Grade ' + row.grade}</option>`)
                    .join('');
                viewGradeSelect.insertAdjacentHTML('beforeend', viewGradeOptions);
            } catch (error) {
                console.error('Failed to load grades', error);
            }
        }

        async function loadStreamsForGrade(gradeNum) {
            try {
                const res = await fetch(`${baseUrl}/settings/streams`, { credentials: 'include' });
                const data = await res.json();
                if (!data.success) {
                    console.error('Failed to load streams:', data.message);
                    viewStreamSelect.innerHTML = '<option value="">No streams available</option>';
                    return;
                }

                const streamsForGrade = data.data.filter(row => {
                    const num = row.grade_number || row.grade_id;
                    return num === Number(gradeNum);
                });

                let html = '<option value="">-- Select Stream --</option>';
                streamsForGrade.forEach(row => {
                    html += `<option value="${row.stream_id}">${row.name.charAt(0).toUpperCase() + row.name.slice(1)}</option>`;
                });

                viewStreamSelect.innerHTML = html;
                viewStreamSelect.disabled = false;
            } catch (error) {
                console.error('Failed to load streams', error);
                viewStreamSelect.innerHTML = '<option value="">Error loading streams</option>';
            }
        }

        function updateNotTakenStatus(input) {
            const row = input.closest('tr');
            const isZero = input.value == 0;
            row.classList.toggle('not-taken', isZero);

            const badge = row.querySelector('.not-taken-badge');
            if (isZero && !badge) {
                row.querySelector('td').insertAdjacentHTML('beforeend', '<span class="not-taken-badge">Not taken</span>');
            } else if (!isZero && badge) {
                badge.remove();
            }
        }

        function renderRequirementsTable(rows) {
            if (!rows || rows.length === 0) {
                requirementsTableWrap.textContent = 'No subjects found.';
                return;
            }

            const body = rows.map((row) => {
                const lessons = Number(row.lessons_per_week || 0);
                const notTaken = lessons === 0;
                return `
                    <tr class="${notTaken ? 'not-taken' : ''}">
                        <td>${row.name}${notTaken ? '<span class="not-taken-badge">Not taken</span>' : ''}</td>
                        <td>
                            <input type="number" min="0" max="20" value="${lessons}" data-subject-id="${row.subject_id}" style="width:120px;">
                        </td>
                    </tr>
                `;
            }).join('');

            requirementsTableWrap.innerHTML = `
                <table>
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Lessons per week</th>
                        </tr>
                    </thead>
                    <tbody>${body}</tbody>
                </table>
            `;

            requirementsTableWrap.querySelectorAll('input[data-subject-id]').forEach((input) => {
                input.addEventListener('change', () => updateNotTakenStatus(input));
            });
        }

        async function loadRequirements() {
            const grade = gradeSelect.value;
            if (!grade) {
                swal('Info', 'Select a grade first.', 'info');
                return;
            }
            setButtonLoading(loadRequirementsBtn, true, 'Loading...');
            requirementsTableWrap.textContent = 'Loading subjects...';
            try {
                const res = await fetch(`${baseUrl}/timetable/requirements?grade=${grade}`, { credentials: 'include' });
                const data = await res.json();
                if (!data.success) {
                    swal('Error', data.message || 'Failed to load requirements.', 'error');
                    requirementsTableWrap.textContent = data.message || 'Failed to load requirements.';
                    return;
                }
                renderRequirementsTable(data.data || []);
            } catch (error) {
                console.error('Failed to load requirements', error);
                swal('Error', 'Failed to load requirements.', 'error');
                requirementsTableWrap.textContent = 'Failed to load requirements.';
            } finally {
                setButtonLoading(loadRequirementsBtn, false);
            }
        }

        async function saveRequirements() {
            const grade = gradeSelect.value;
            if (!grade) {
                swal('Info', 'Select a grade first.', 'info');
                return;
            }

            const inputs = requirementsTableWrap.querySelectorAll('input[data-subject-id]');
            const requirements = Array.from(inputs).map((input) => ({
                subject_id: Number(input.dataset.subjectId),
                lessons_per_week: Number(input.value || 0),
            }));

            setButtonLoading(saveRequirementsBtn, true, 'Saving...');
            try {
                const res = await fetch(`${baseUrl}/timetable/requirements`, {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ grade: Number(grade), requirements }),
                });
                const data = await res.json();
                if (!data.success) {
                    swal('Error', data.message || 'Failed to save requirements.', 'error');
                    return;
                }
                swal('Success', 'Requirements saved.', 'success');
            } catch (error) {
                console.error('Failed to save requirements', error);
                swal('Error', 'Failed to save requirements.', 'error');
            } finally {
                setButtonLoading(saveRequirementsBtn, false);
            }
        }

        async function saveConfig() {
            const p1 = Number(periodsLevel1.value || 0);
            const p2 = Number(periodsLevel2.value || 0);
            const p3 = Number(periodsLevel3.value || 0);

            if (p1 <= 0 || p2 <= 0 || p3 <= 0) {
                swal('Error', 'Enter valid periods for all grade levels.', 'error');
                return;
            }
            setButtonLoading(saveConfigBtn, true, 'Saving...');
            try {
                const res = await fetch(`${baseUrl}/timetable/config`, {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        levels: {
                            '1': p1,
                            '2': p2,
                            '3': p3
                        }
                    }),
                });
                const data = await res.json();
                if (!data.success) {
                    swal('Error', data.message || 'Failed to save config.', 'error');
                    return;
                }
                swal('Success', 'Configuration saved.', 'success');
            } catch (error) {
                console.error('Failed to save config', error);
                swal('Error', 'Failed to save config.', 'error');
            } finally {
                setButtonLoading(saveConfigBtn, false);
            }
        }

        async function generateTimetable() {
            setButtonLoading(generateBtn, true, 'Generating...');
            try {
                const res = await fetch(`${baseUrl}/timetable/generate`, {
                    method: 'POST',
                    credentials: 'include',
                });
                const data = await res.json();
                if (!data.success) {
                    swal('Error', data.message || 'Failed to generate timetable.', 'error');
                    return;
                }
                swal('Success', `Timetable generated in ${data.data.attempts} attempts.`, 'success');
            } catch (error) {
                console.error('Failed to generate timetable', error);
                swal('Error', 'Failed to generate timetable.', 'error');
            } finally {
                setButtonLoading(generateBtn, false);
            }
        }

        async function viewTimetable() {
            const classId = viewStreamSelect.value;
            if (!classId) {
                swal('Info', 'Select a stream first.', 'info');
                return;
            }
            setButtonLoading(viewBtn, true, 'Loading...');
            timetableView.textContent = 'Loading timetable...';
            try {
                const res = await fetch(`${baseUrl}/timetable/view?class_id=${classId}`, { credentials: 'include' });
                const data = await res.json();
                if (!data.success) {
                    swal('Error', data.message || 'Failed to load timetable.', 'error');
                    timetableView.textContent = data.message || 'Failed to load timetable.';
                    return;
                }
                const entries = (data.data && data.data.entries) ? data.data.entries : [];
                if (!entries.length) {
                    const msg = 'No timetable exists for this class yet. Generate timetable first.';
                    swal('Info', msg, 'info');
                    timetableView.textContent = msg;
                    return;
                }
                renderClassTimetable(data.data, timetableView);
            } catch (error) {
                console.error('Failed to load timetable', error);
                swal('Error', 'Failed to load timetable.', 'error');
                timetableView.textContent = 'Failed to load timetable.';
            } finally {
                setButtonLoading(viewBtn, false);
            }
        }

        saveConfigBtn.addEventListener('click', saveConfig);
        loadRequirementsBtn.addEventListener('click', loadRequirements);
        saveRequirementsBtn.addEventListener('click', saveRequirements);
        generateBtn.addEventListener('click', generateTimetable);
        viewBtn.addEventListener('click', viewTimetable);
        if (printBtn) {
            printBtn.addEventListener('click', () => window.print());
        }
        viewGradeSelect.addEventListener('change', (e) => {
            loadStreamsForGrade(e.target.value);
        });

        await loadConfig();
        await loadGrades();
    }

    async function initClassTimetablePage() {
        const viewGradeSelect = document.getElementById('viewGradeSelect');
        const viewStreamSelect = document.getElementById('viewStreamSelect');
        const viewBtn = document.getElementById('viewBtn');
        const printBtn = document.getElementById('printBtn');
        const timetableView = document.getElementById('timetableView');

        if (!viewGradeSelect || !viewStreamSelect || !viewBtn || !timetableView) {
            return;
        }

        const authed = await ensureAuth();
        if (!authed) {
            return;
        }

        async function loadGrades() {
            try {
                const res = await fetch(`${baseUrl}/settings/grades`, { credentials: 'include' });
                const data = await res.json();
                if (!data.success || !data.data) {
                    return;
                }
                const options = data.data
                    .map((row) => `<option value="${row.grade}">Grade ${row.grade}</option>`)
                    .join('');
                viewGradeSelect.insertAdjacentHTML('beforeend', options);
            } catch (error) {
                console.error('Failed to load grades', error);
            }
        }

        async function loadStreamsForGrade(gradeNum) {
            try {
                const res = await fetch(`${baseUrl}/settings/streams`, { credentials: 'include' });
                const data = await res.json();
                if (!data.success) {
                    viewStreamSelect.innerHTML = '<option value="">No streams available</option>';
                    return;
                }

                const streamsForGrade = data.data.filter(row => {
                    const num = row.grade_number || row.grade_id;
                    return num === Number(gradeNum);
                });

                let html = '<option value="">-- Select Stream --</option>';
                streamsForGrade.forEach(row => {
                    html += `<option value="${row.stream_id}">${row.name.charAt(0).toUpperCase() + row.name.slice(1)}</option>`;
                });

                viewStreamSelect.innerHTML = html;
                viewStreamSelect.disabled = false;
            } catch (error) {
                console.error('Failed to load streams', error);
                viewStreamSelect.innerHTML = '<option value="">Error loading streams</option>';
            }
        }

        async function viewTimetable() {
            const classId = viewStreamSelect.value;
            if (!classId) {
                swal('Info', 'Select a stream first.', 'info');
                return;
            }
            setButtonLoading(viewBtn, true, 'Loading...');
            timetableView.textContent = 'Loading timetable...';
            try {
                const res = await fetch(`${baseUrl}/timetable/view?class_id=${classId}`, { credentials: 'include' });
                const data = await res.json();
                if (!data.success) {
                    swal('Error', data.message || 'Failed to load timetable.', 'error');
                    timetableView.textContent = data.message || 'Failed to load timetable.';
                    return;
                }
                const entries = (data.data && data.data.entries) ? data.data.entries : [];
                if (!entries.length) {
                    const msg = 'No timetable exists for this class yet. Generate timetable first.';
                    swal('Info', msg, 'info');
                    timetableView.textContent = msg;
                    return;
                }
                renderClassTimetable(data.data, timetableView);
            } catch (error) {
                console.error('Failed to load timetable', error);
                swal('Error', 'Failed to load timetable.', 'error');
                timetableView.textContent = 'Failed to load timetable.';
            } finally {
                setButtonLoading(viewBtn, false);
            }
        }

        viewBtn.addEventListener('click', viewTimetable);
        if (printBtn) {
            printBtn.addEventListener('click', () => window.print());
        }
        viewGradeSelect.addEventListener('change', (e) => {
            loadStreamsForGrade(e.target.value);
        });

        await loadGrades();
    }

    async function initTeacherTimetablePage() {
        const teacherSelect = document.getElementById('teacherSelect');
        const viewBtn = document.getElementById('viewBtn');
        const printBtn = document.getElementById('printBtn');
        const timetableView = document.getElementById('timetableView');

        if (!teacherSelect || !viewBtn || !timetableView) {
            return;
        }

        const authed = await ensureAuth();
        if (!authed) {
            return;
        }

        async function loadTeachers() {
            try {
                const res = await fetch(`${baseUrl}/settings/teachers`, { credentials: 'include' });
                const data = await res.json();
                if (!data.success || !data.data) {
                    return;
                }
                const options = data.data
                    .map((row) => {
                        const status = row.is_active ? '' : ' (inactive)';
                        return `<option value="${row.teacher_id}">${row.name}${status}</option>`;
                    })
                    .join('');
                teacherSelect.insertAdjacentHTML('beforeend', options);
            } catch (error) {
                console.error('Failed to load teachers', error);
            }
        }

        async function viewTimetable() {
            const teacherId = teacherSelect.value;
            if (!teacherId) {
                swal('Info', 'Select a teacher first.', 'info');
                return;
            }
            setButtonLoading(viewBtn, true, 'Loading...');
            timetableView.textContent = 'Loading timetable...';
            try {
                const res = await fetch(`${baseUrl}/timetable/teacher-view?teacher_id=${teacherId}`, { credentials: 'include' });
                const data = await res.json();
                if (!data.success) {
                    swal('Error', data.message || 'Failed to load timetable.', 'error');
                    timetableView.textContent = data.message || 'Failed to load timetable.';
                    return;
                }
                const entries = (data.data && data.data.entries) ? data.data.entries : [];
                if (!entries.length) {
                    const msg = 'No timetable exists for this teacher yet. Generate timetable first.';
                    swal('Info', msg, 'info');
                    timetableView.textContent = msg;
                    return;
                }
                renderTeacherTimetable(data.data, timetableView);
            } catch (error) {
                console.error('Failed to load timetable', error);
                swal('Error', 'Failed to load timetable.', 'error');
                timetableView.textContent = 'Failed to load timetable.';
            } finally {
                setButtonLoading(viewBtn, false);
            }
        }

        viewBtn.addEventListener('click', viewTimetable);
        if (printBtn) {
            printBtn.addEventListener('click', () => window.print());
        }

        await loadTeachers();
    }

    function initTabNavigation() {
        const tabButtons = document.querySelectorAll('.tab-btn');
        if (!tabButtons.length) {
            return;
        }
        tabButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const tabName = btn.dataset.tab;

                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));

                btn.classList.add('active');
                const tab = document.getElementById(tabName);
                if (tab) {
                    tab.classList.add('active');
                }
            });
        });
    }

    initTabNavigation();
    initTimetableSettingsPage();
    initClassTimetablePage();
    initTeacherTimetablePage();
})();
