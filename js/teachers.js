(async function () {
    const baseUrl = "../../backend/public/index.php";
    const subjectNameInput = document.getElementById('subjectName');
    const addSubjectBtn = document.getElementById('addSubjectBtn');
    const subjectsTable = document.getElementById('subjectsTable');

    const gradeValueInput = document.getElementById('gradeValue');
    const streamNamesInput = document.getElementById('streamNames');
    const addGradeBtn = document.getElementById('addGradeBtn');
    const gradesTable = document.getElementById('gradesTable');

    const teacherNameInput = document.getElementById('teacherName');
    const teacherActiveInput = document.getElementById('teacherActive');
    const teacherIdInput = document.getElementById('teacherId');
    const editTeacherName = document.getElementById('editTeacherName');
    const resetTeacherBtn = document.getElementById('resetTeacherBtn');
    const saveTeacherBtn = document.getElementById('saveTeacherBtn');
    const addAssignmentRowBtn = document.getElementById('addAssignmentRowBtn');
    const assignmentsWrap = document.getElementById('assignmentsWrap');

    const teachersTable = document.getElementById('teachersTable');

    let subjects = [];
    let grades = [];
    let streams = {}; // Map of grade_id -> array of streams

    try {
        const authRes = await fetch(`${baseUrl}/auth/check`, { credentials: "include" });
        const auth = await authRes.json();
        if (!auth.authenticated) {
            window.location.replace("../login.html");
            return;
        }
    } catch (error) {
        console.error('Failed to validate session', error);
    }

    async function loadStreamsForGrade(gradeId) {
        try {
            const res = await fetch(`${baseUrl}/settings/streams?grade_id=${gradeId}`, { credentials: 'include' });
            const data = await res.json();
            streams[gradeId] = data.success ? (data.data || []) : [];
        } catch (error) {
            console.error('Failed to load streams for grade', error);
            streams[gradeId] = [];
        }
    }

    function buildSubjectOptions(selectedId) {
        const options = subjects.map((subject) => {
            const selected = selectedId === subject.subject_id ? 'selected' : '';
            return `<option value="${subject.subject_id}" ${selected}>${subject.name}</option>`;
        }).join('');
        return `<option value="">-- Select Subject --</option>${options}`;
    }

    function buildGradeOptions(selectedIds) {
        const set = new Set(selectedIds || []);
        return grades.map((grade) => {
            const checked = set.has(grade.grade_id) ? 'checked' : '';
            return `
                <label class="grade-checkbox">
                    <input type="checkbox" class="grade-checkbox-input" value="${grade.grade_id}" ${checked}>
                    <span>Grade ${grade.grade}</span>
                </label>
            `;
        }).join('');
    }

    function ensureAssignmentsTable() {
        const table = assignmentsWrap.querySelector('table');
        if (table) {
            return table;
        }

        assignmentsWrap.innerHTML = `
            <table class="assignments-table">
                <thead>
                    <tr>
                        <th style="width: 240px;">Subject</th>
                        <th>Grades & Streams</th>
                        <th style="width: 90px;"></th>
                    </tr>
                </thead>
                <tbody id="assignmentRows"></tbody>
            </table>
        `;

        return assignmentsWrap.querySelector('table');
    }

    async function createAssignmentRow(subjectId, gradeData) {
        ensureAssignmentsTable();
        const tbody = document.getElementById('assignmentRows');
        const row = document.createElement('tr');
        const gradeIds = gradeData ? Object.keys(gradeData) : [];

        row.innerHTML = `
            <td>
                <select class="subject-select">${buildSubjectOptions(subjectId || 0)}</select>
            </td>
            <td>
                <div class="grade-streams-container"></div>
            </td>
            <td>
                <button class="btn-danger remove-row" type="button">Remove</button>
            </td>
        `;

        const streamContainer = row.querySelector('.grade-streams-container');
        
        // Populate grade and stream selections
        for (const grade of grades) {
            const gradeId = grade.grade_id;
            const selectedStreams = gradeData && gradeData[gradeId] ? gradeData[gradeId] : [];
            
            // Load streams if not already loaded
            if (!streams[gradeId]) {
                await loadStreamsForGrade(gradeId);
            }
            
            const gradeDiv = document.createElement('div');
            gradeDiv.className = 'grade-stream-item';
            gradeDiv.style.marginBottom = '10px';
            
            // Grade checkbox
            const gradeChecked = gradeIds.includes(gradeId.toString()) ? 'checked' : '';
            const gradeCheckbox = document.createElement('input');
            gradeCheckbox.type = 'checkbox';
            gradeCheckbox.className = 'grade-checkbox-input';
            gradeCheckbox.value = gradeId;
            if (gradeChecked) gradeCheckbox.checked = true;
            
            const gradeLabel = document.createElement('label');
            gradeLabel.className = 'grade-checkbox';
            gradeLabel.style.display = 'block';
            gradeLabel.style.marginBottom = '6px';
            const gradeSpan = document.createElement('span');
            gradeSpan.textContent = `Grade ${grade.grade}`;
            gradeLabel.appendChild(gradeCheckbox);
            gradeLabel.appendChild(gradeSpan);
            gradeDiv.appendChild(gradeLabel);
            
            // Stream checkboxes
            if (streams[gradeId] && streams[gradeId].length > 0) {
                const streamSet = new Set(selectedStreams);
                
                const streamDiv = document.createElement('div');
                streamDiv.className = 'streams-for-grade';
                streamDiv.style.marginLeft = '24px';
                streamDiv.style.paddingLeft = '12px';
                streamDiv.style.borderLeft = '2px solid #d1d5db';
                
                streams[gradeId].forEach(stream => {
                    const checked = streamSet.has(stream.stream_id);
                    
                    const streamCheckbox = document.createElement('input');
                    streamCheckbox.type = 'checkbox';
                    streamCheckbox.className = 'stream-checkbox-input';
                    streamCheckbox.value = stream.stream_id;
                    streamCheckbox.setAttribute('data-grade-id', gradeId);
                    if (checked) streamCheckbox.checked = true;
                    streamCheckbox.disabled = !gradeChecked; // Disable if grade not checked
                    
                    const streamLabel = document.createElement('label');
                    streamLabel.className = 'stream-checkbox';
                    streamLabel.style.display = 'inline-block';
                    streamLabel.style.marginRight = '12px';
                    const streamSpan = document.createElement('span');
                    streamSpan.style.fontSize = '12px';
                    streamSpan.textContent = stream.name;
                    streamLabel.appendChild(streamCheckbox);
                    streamLabel.appendChild(streamSpan);
                    streamDiv.appendChild(streamLabel);
                });
                
                // Grade checkbox toggle: enable/disable stream checkboxes
                gradeCheckbox.addEventListener('change', () => {
                    const isChecked = gradeCheckbox.checked;
                    streamDiv.querySelectorAll('.stream-checkbox-input').forEach(cb => {
                        cb.disabled = !isChecked;
                        if (!isChecked) cb.checked = false;
                    });
                });
                
                gradeDiv.appendChild(streamDiv);
            }
            
            streamContainer.appendChild(gradeDiv);
        }

        row.querySelector('.remove-row').addEventListener('click', () => {
            row.remove();
            const remaining = document.querySelectorAll('#assignmentRows tr').length;
            if (remaining === 0) {
                assignmentsWrap.textContent = 'Add a subject row to start assignments.';
            }
        });

        tbody.appendChild(row);
    }

    function getSelectedAssignments() {
        const assignments = [];
        const selectedSubjects = Array.from(document.querySelectorAll('.subject-checkbox:checked')).map(cb => Number(cb.value));
        
        selectedSubjects.forEach(subjectId => {
            const grades = [];
            
            // Check full grade selections
            const fullGradeCheckboxes = document.querySelectorAll(`.grade-full-checkbox[data-subject-id="${subjectId}"]:checked`);
            fullGradeCheckboxes.forEach(cb => {
                grades.push({
                    grade_id: Number(cb.value),
                    stream_ids: [], // Empty array means all streams
                });
            });
            
            // Check individual stream selections
            const streamCheckboxes = document.querySelectorAll(`.stream-individual-checkbox[data-subject-id="${subjectId}"]:checked`);
            const streamsByGrade = {};
            streamCheckboxes.forEach(cb => {
                const gradeId = Number(cb.getAttribute('data-grade-id'));
                const streamId = Number(cb.value);
                if (!streamsByGrade[gradeId]) {
                    streamsByGrade[gradeId] = [];
                }
                streamsByGrade[gradeId].push(streamId);
            });
            
            // Add stream-specific grades
            Object.keys(streamsByGrade).forEach(gradeId => {
                const hasFullGrade = grades.some(g => g.grade_id === Number(gradeId));
                if (!hasFullGrade) {
                    grades.push({
                        grade_id: Number(gradeId),
                        stream_ids: streamsByGrade[gradeId],
                    });
                }
            });
            
            if (grades.length > 0) {
                assignments.push({
                    subject_id: subjectId,
                    grades: grades,
                });
            }
        });
        
        return assignments;
    }

    function renderSubjectsCheckboxes() {
        if (!subjects.length) {
            document.getElementById('subjectsCheckboxes').innerHTML = '<div class="muted">No subjects created yet.</div>';
            return;
        }

        const container = document.getElementById('subjectsCheckboxes');
        container.innerHTML = '';
        subjects.forEach(subject => {
            const label = document.createElement('label');
            label.style.display = 'flex';
            label.style.alignItems = 'center';
            label.style.gap = '8px';
            label.style.padding = '8px 12px';
            label.style.borderRadius = '6px';
            label.style.cursor = 'pointer';
            label.addEventListener('mouseenter', () => label.style.backgroundColor = '#e5e7eb');
            label.addEventListener('mouseleave', () => label.style.backgroundColor = 'transparent');
            
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'subject-checkbox';
            checkbox.value = subject.subject_id;
            checkbox.style.width = '18px';
            checkbox.style.height = '18px';
            checkbox.style.cursor = 'pointer';
            checkbox.addEventListener('change', () => {
                const selections = getCurrentAssignmentSelections();
                renderAssignmentForm(selections);
            });
            
            const span = document.createElement('span');
            span.textContent = subject.name;
            span.style.fontWeight = '500';
            
            label.appendChild(checkbox);
            label.appendChild(span);
            container.appendChild(label);
        });
    }

    function getCurrentAssignmentSelections() {
        const selections = {};

        document.querySelectorAll('.grade-full-checkbox:checked').forEach(cb => {
            const subjectId = Number(cb.getAttribute('data-subject-id'));
            const gradeId = Number(cb.value);
            if (!selections[subjectId]) {
                selections[subjectId] = {};
            }
            selections[subjectId][gradeId] = [];
        });

        document.querySelectorAll('.stream-individual-checkbox:checked').forEach(cb => {
            const subjectId = Number(cb.getAttribute('data-subject-id'));
            const gradeId = Number(cb.getAttribute('data-grade-id'));
            const streamId = Number(cb.value);
            if (!selections[subjectId]) {
                selections[subjectId] = {};
            }
            if (!selections[subjectId][gradeId]) {
                selections[subjectId][gradeId] = [];
            }
            selections[subjectId][gradeId].push(streamId);
        });

        return selections;
    }

    function renderAssignmentForm(existingSelections = null) {
        const selectedSubjects = Array.from(document.querySelectorAll('.subject-checkbox:checked')).map(cb => ({
            id: Number(cb.value),
            name: cb.parentElement.querySelector('span').textContent,
        }));

        if (!selectedSubjects.length) {
            assignmentsWrap.textContent = 'Select subjects above to assign grades and streams.';
            return;
        }

        const selections = existingSelections || getCurrentAssignmentSelections();
        assignmentsWrap.innerHTML = '';
        selectedSubjects.forEach(subject => {
            const section = document.createElement('div');
            section.style.marginBottom = '25px';
            section.style.padding = '15px';
            section.style.border = '1px solid #d1d5db';
            section.style.borderRadius = '8px';
            section.style.backgroundColor = '#f9fafb';
            
            const title = document.createElement('h4');
            title.textContent = subject.name;
            title.style.margin = '0 0 12px 0';
            title.style.color = '#374151';
            section.appendChild(title);
            
            const grid = document.createElement('div');
            grid.style.display = 'grid';
            grid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(200px, 1fr))';
            grid.style.gap = '15px';
            
            grades.forEach(grade => {
                const subjectSelections = selections[subject.id] || {};
                const selectedStreams = subjectSelections[grade.grade_id] || null;
                const hasFullGrade = Array.isArray(selectedStreams) && selectedStreams.length === 0;

                const gradeCard = document.createElement('div');
                gradeCard.style.padding = '12px';
                gradeCard.style.border = '1px solid #e5e7eb';
                gradeCard.style.borderRadius = '6px';
                gradeCard.style.backgroundColor = '#fff';
                gradeCard.style.transition = 'border-color 0.2s ease';
                
                const gradeCheckbox = document.createElement('input');
                gradeCheckbox.type = 'checkbox';
                gradeCheckbox.className = 'grade-full-checkbox';
                gradeCheckbox.value = grade.grade_id;
                gradeCheckbox.setAttribute('data-subject-id', subject.id);
                gradeCheckbox.style.width = '18px';
                gradeCheckbox.style.height = '18px';
                gradeCheckbox.style.marginRight = '8px';
                gradeCheckbox.style.cursor = 'pointer';
                gradeCheckbox.style.accentColor = '#4caf50';
                
                const gradeLabel = document.createElement('label');
                gradeLabel.style.display = 'flex';
                gradeLabel.style.alignItems = 'center';
                gradeLabel.style.marginBottom = '10px';
                gradeLabel.style.cursor = 'pointer';
                gradeLabel.style.fontWeight = '600';
                gradeLabel.style.transition = 'color 0.2s ease';
                const gradeLabelText = document.createElement('span');
                gradeLabelText.textContent = `Grade ${grade.grade} (All)`;
                gradeLabel.appendChild(gradeCheckbox);
                gradeLabel.appendChild(gradeLabelText);
                if (hasFullGrade) {
                    gradeCheckbox.checked = true;
                }

                gradeCard.appendChild(gradeLabel);
                
                gradeCheckbox.addEventListener('change', function () {
                    if (this.checked) {
                        gradeCard.querySelectorAll('.stream-individual-checkbox').forEach(cb => {
                            cb.checked = false;
                            cb.disabled = true;
                        });
                        gradeCard.style.borderColor = '#4caf50';
                        gradeLabelText.style.color = '#4caf50';
                    } else {
                        gradeCard.querySelectorAll('.stream-individual-checkbox').forEach(cb => {
                            cb.disabled = false;
                        });
                        gradeCard.style.borderColor = '#e5e7eb';
                        gradeLabelText.style.color = 'inherit';
                    }
                });
                
                if (streams[grade.grade_id] && streams[grade.grade_id].length > 0) {
                    const streamContainer = document.createElement('div');
                    streamContainer.style.marginLeft = '26px';
                    streamContainer.style.borderLeft = '2px solid #d1d5db';
                    streamContainer.style.paddingLeft = '12px';
                    
                    streams[grade.grade_id].forEach(stream => {
                        const streamCheckbox = document.createElement('input');
                        streamCheckbox.type = 'checkbox';
                        streamCheckbox.className = 'stream-individual-checkbox';
                        streamCheckbox.value = stream.stream_id;
                        streamCheckbox.setAttribute('data-grade-id', grade.grade_id);
                        streamCheckbox.setAttribute('data-subject-id', subject.id);
                        streamCheckbox.style.width = '16px';
                        streamCheckbox.style.height = '16px';
                        streamCheckbox.style.marginRight = '6px';
                        streamCheckbox.style.cursor = 'pointer';
                        streamCheckbox.style.accentColor = '#667eea';
                        
                        const streamLabel = document.createElement('label');
                        streamLabel.style.display = 'flex';
                        streamLabel.style.alignItems = 'center';
                        streamLabel.style.marginBottom = '6px';
                        streamLabel.style.fontSize = '13px';
                        streamLabel.style.cursor = 'pointer';
                        const streamLabelText = document.createElement('span');
                        streamLabelText.textContent = `Stream ${stream.name}`;
                        streamLabel.appendChild(streamCheckbox);
                        streamLabel.appendChild(streamLabelText);
                        if (Array.isArray(selectedStreams) && selectedStreams.includes(stream.stream_id)) {
                            streamCheckbox.checked = true;
                        }

                        if (hasFullGrade) {
                            streamCheckbox.disabled = true;
                        }

                        streamContainer.appendChild(streamLabel);
                        
                        streamCheckbox.addEventListener('change', function () {
                            if (this.checked) {
                                gradeCheckbox.checked = false;
                                gradeCard.style.borderColor = '#e5e7eb';
                                gradeLabelText.style.color = 'inherit';
                            }
                        });
                    });
                    gradeCard.appendChild(streamContainer);
                }
                
                grid.appendChild(gradeCard);
            });
            
            section.appendChild(grid);
            assignmentsWrap.appendChild(section);
        });
    }

    function resetTeacherForm() {
        if (teacherIdInput) {
            teacherIdInput.value = '';
        }
        if (editTeacherName) {
            editTeacherName.textContent = 'Creating new teacher';
        }
        teacherNameInput.value = '';
        teacherActiveInput.checked = true;
        document.querySelectorAll('.subject-checkbox').forEach(cb => cb.checked = false);
        assignmentsWrap.textContent = 'Select subjects above to assign grades and streams.';
    }

    function loadTeacherIntoForm(teacher) {
        if (!teacher) {
            return;
        }

        if (teacherIdInput) {
            teacherIdInput.value = String(teacher.teacher_id || '');
        }
        if (editTeacherName) {
            editTeacherName.textContent = `Editing: ${teacher.name}`;
        }
        teacherNameInput.value = teacher.name || '';
        teacherActiveInput.checked = Number(teacher.is_active) === 1;

        document.querySelectorAll('.subject-checkbox').forEach(cb => cb.checked = false);
        const subjectIds = (teacher.assignments || []).map(a => Number(a.subject_id));
        document.querySelectorAll('.subject-checkbox').forEach(cb => {
            if (subjectIds.includes(Number(cb.value))) {
                cb.checked = true;
            }
        });

        const selections = {};
        (teacher.assignments || []).forEach(assignment => {
            const subjectId = Number(assignment.subject_id);
            (assignment.grades || []).forEach(grade => {
                const gradeId = Number(grade.grade_id);
                const streamsList = grade.streams || [];

                if (!selections[subjectId]) {
                    selections[subjectId] = {};
                }

                if (!streamsList.length) {
                    selections[subjectId][gradeId] = [];
                    return;
                }

                selections[subjectId][gradeId] = streamsList.map(stream => Number(stream.stream_id));
            });
        });

        renderAssignmentForm(selections);
    }

    async function loadSubjects() {
        try {
            const res = await fetch(`${baseUrl}/settings/subjects`, { credentials: 'include' });
            const data = await res.json();
            subjects = data.success ? (data.data || []) : [];
            renderSubjects();
            renderSubjectsCheckboxes();
        } catch (error) {
            console.error('Failed to load subjects', error);
        }
    }

    async function loadGrades() {
        try {
            const res = await fetch(`${baseUrl}/settings/grades`, { credentials: 'include' });
            const data = await res.json();
            grades = data.success ? (data.data || []) : [];
            
            // Preload streams for all grades
            for (const grade of grades) {
                await loadStreamsForGrade(grade.grade_id);
            }
            
            renderGrades();
            renderAssignmentForm();
        } catch (error) {
            console.error('Failed to load grades', error);
        }
    }

    async function loadTeachers() {
        try {
            const res = await fetch(`${baseUrl}/settings/teachers`, { credentials: 'include' });
            const data = await res.json();
            renderTeachers(data.success ? (data.data || []) : []);
        } catch (error) {
            console.error('Failed to load teachers', error);
        }
    }

    async function loadSubjectStats() {
        try {
            const res = await fetch(`${baseUrl}/settings/subject-stats`, { credentials: 'include' });
            const data = await res.json();
            renderSubjectStats(data.success ? (data.data || []) : []);
        } catch (error) {
            console.error('Failed to load subject stats', error);
            document.getElementById('subjectStatsTable').innerHTML = '<div class="muted">Failed to load statistics.</div>';
        }
    }

    function renderSubjectStats(stats) {
        const statsTable = document.getElementById('subjectStatsTable');
        
        if (!stats || Object.keys(stats).length === 0) {
            statsTable.innerHTML = '<div class="muted">No subjects yet.</div>';
            return;
        }

        const levels = ['1', '2', '3'];
        const levelLabels = {
            '1': 'Grade 1-3',
            '2': 'Grade 4-6',
            '3': 'Grade 7-9'
        };

        let html = '';

        levels.forEach(level => {
            const levelData = stats[level];
            if (!levelData || !levelData.subjects || levelData.subjects.length === 0) {
                return;
            }

            html += `<div style="margin-bottom: 30px;">
                <h4 style="font-size: 16px; font-weight: 700; color: #374151; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid #4caf50;">
                    ${levelLabels[level]}
                </h4>
                <div class="stats-grid">`;

            const colors = ['green', 'blue', 'orange'];
            levelData.subjects.forEach((subject, index) => {
                const color = colors[index % colors.length];
                html += `
                    <div class="stats-card ${color}">
                        <div class="stats-subject-name">${subject.subject_name}</div>
                        <div class="stats-number">${subject.teacher_count}</div>
                        <div class="stats-label">Teachers</div>
                    </div>
                `;
            });

            html += `</div></div>`;
        });

        statsTable.innerHTML = html;
    }

    function renderSubjects() {
        if (!subjects.length) {
            subjectsTable.textContent = 'No subjects yet.';
            return;
        }

        const rows = subjects.map((subject) => `
            <tr>
                <td>${subject.name}</td>
                <td style="width: 120px;">
                    <button class="btn-danger" data-subject-id="${subject.subject_id}">Delete</button>
                </td>
            </tr>
        `).join('');

        subjectsTable.innerHTML = `
            <table>
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        `;

        subjectsTable.querySelectorAll('button[data-subject-id]').forEach((btn) => {
            btn.addEventListener('click', () => deleteSubject(Number(btn.dataset.subjectId)));
        });
    }

    function renderGrades() {
        if (!grades.length) {
            gradesTable.textContent = 'No grades yet.';
            return;
        }

        const rows = grades.map((grade) => {
            const gradeStreams = streams[grade.grade_id] || [];
            const streamsList = gradeStreams.length > 0
                ? gradeStreams.map(s => `<span class="pill">${s.name}</span>`).join('')
                : '<span class="muted">No streams</span>';
            
            return `
                <tr>
                    <td>
                        <strong>Grade ${grade.grade}</strong>
                        <div style="margin-top: 6px; font-size: 13px;">${streamsList}</div>
                    </td>
                    <td style="width: 180px;">
                        <button class="btn-ghost" data-grade-id="${grade.grade_id}" style="width: 100%; margin-bottom: 4px;">Add Stream</button>
                        <button class="btn-danger" data-grade-id="${grade.grade_id}">Delete Grade</button>
                    </td>
                </tr>
            `;
        }).join('');

        gradesTable.innerHTML = `
            <table>
                <thead>
                    <tr>
                        <th>Grade & Streams</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        `;

        // Delete grade buttons
        gradesTable.querySelectorAll('button[data-grade-id]').forEach((btn) => {
            const isDelete = btn.classList.contains('btn-danger');
            btn.addEventListener('click', () => {
                if (isDelete) {
                    deleteGrade(Number(btn.dataset.gradeId));
                } else {
                    showAddStreamDialog(Number(btn.dataset.gradeId));
                }
            });
        });
    }

    async function showAddStreamDialog(gradeId) {
        const streamName = prompt('Enter stream name (e.g., A, B, C):');
        if (!streamName || !streamName.trim()) return;

        try {
            const res = await fetch(`${baseUrl}/settings/streams`, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ grade_id: gradeId, name: streamName.trim() }),
            });
            const data = await res.json();
            if (!data.success) {
                swal('Error', data.message || 'Failed to add stream.', 'error');
                return;
            }
            await loadGrades();
            swal('Success', 'Stream added.', 'success');
        } catch (error) {
            console.error('Failed to add stream', error);
            swal('Error', 'Failed to add stream.', 'error');
        }
    }

    const teacherModal = document.getElementById('teacherModal');
    const modalTeacherName = document.getElementById('modalTeacherName');
    const modalBody = document.getElementById('modalBody');
    const modalClose = document.getElementById('modalClose');
    const modalCloseBtn = document.getElementById('modalCloseBtn');
    const modalDeleteBtn = document.getElementById('modalDeleteBtn');
    
    let currentTeacherId = null;

    function showTeacherModal(teacher) {
        currentTeacherId = teacher.teacher_id;
        modalTeacherName.textContent = teacher.name;
        
        const assignmentsHtml = (teacher.assignments || []).length === 0 
            ? '<div style="color: #6b7280; font-size: 13px;">No assignments</div>'
            : (teacher.assignments || []).map(assignment => {
                const gradesHtml = (assignment.grades || []).map(grade => {
                    const streamText = grade.streams && grade.streams.length > 0
                        ? grade.streams.map(s => s.stream_name).join(', ')
                        : 'All streams';
                    return `<div>Grade ${grade.grade} (${streamText})</div>`;
                }).join('');
                
                return `
                    <div class="modal-assignment">
                        <div class="modal-assignment-subject">${assignment.subject_name}</div>
                        <div class="modal-assignment-grades">${gradesHtml}</div>
                    </div>
                `;
            }).join('');

        modalBody.innerHTML = `
            <div class="modal-section">
                <div class="modal-section-title">Status</div>
                <div style="font-size: 13px; color: #374151;">
                    ${teacher.is_active ? '<span style="color: #10b981; font-weight: 600;">✓ Active</span>' : '<span style="color: #6b7280;">Inactive</span>'}
                </div>
            </div>
            <div class="modal-section">
                <div class="modal-section-title">Assignments</div>
                ${assignmentsHtml}
            </div>
        `;
        
        teacherModal.classList.add('show');
    }

    function hideTeacherModal() {
        teacherModal.classList.remove('show');
        currentTeacherId = null;
    }

    modalClose.addEventListener('click', hideTeacherModal);
    modalCloseBtn.addEventListener('click', hideTeacherModal);
    teacherModal.addEventListener('click', (e) => {
        if (e.target === teacherModal) hideTeacherModal();
    });
    modalDeleteBtn.addEventListener('click', async () => {
        if (currentTeacherId && currentTeacherId > 0) {
            hideTeacherModal();
            await deleteTeacher(Number(currentTeacherId));
        } else {
            swal('Error', 'No teacher selected.', 'error');
        }
    });

    function renderTeachers(teachers) {
        if (!teachers.length) {
            teachersTable.innerHTML = '<div class="muted">No teachers yet.</div>';
            return;
        }

        // Group teachers by grade
        const teachersByGrade = {};
        teachers.forEach(teacher => {
            const grades = (teacher.assignments || []).flatMap(assignment => 
                (assignment.grades || []).map(grade => grade.grade)
            );
            const uniqueGrades = [...new Set(grades)].sort((a, b) => a - b);
            
            if (uniqueGrades.length === 0) {
                // Teachers with no assignments
                if (!teachersByGrade['No Grade']) {
                    teachersByGrade['No Grade'] = [];
                }
                teachersByGrade['No Grade'].push(teacher);
            } else {
                uniqueGrades.forEach(grade => {
                    if (!teachersByGrade[grade]) {
                        teachersByGrade[grade] = [];
                    }
                    teachersByGrade[grade].push(teacher);
                });
            }
        });

        // Sort grades numerically
        const sortedGrades = Object.keys(teachersByGrade).sort((a, b) => {
            if (a === 'No Grade') return 1;
            if (b === 'No Grade') return -1;
            return Number(a) - Number(b);
        });

        const sectionsHtml = sortedGrades.map(grade => {
            const gradeTeachers = teachersByGrade[grade];
            const cardsHtml = gradeTeachers.map((teacher) => {
                const subjectCount = (teacher.assignments || []).length;
                const subjectsText = subjectCount === 0 
                    ? 'No assignments'
                    : subjectCount === 1 
                        ? '1 subject'
                        : `${subjectCount} subjects`;

                return `
                    <div class="teacher-card" data-teacher-id="${teacher.teacher_id}">
                        <div class="teacher-card-name">${teacher.name}</div>
                        <div class="teacher-card-status ${teacher.is_active ? 'active' : ''}">
                            ${teacher.is_active ? '✓ Active' : 'Inactive'}
                        </div>
                        <div class="teacher-card-subjects">${subjectsText}</div>
                        <button class="btn-ghost teacher-edit-btn" data-teacher-id="${teacher.teacher_id}" type="button">Edit</button>
                    </div>
                `;
            }).join('');

            const gradeLabel = grade === 'No Grade' ? 'Unassigned' : `Grade ${grade}`;
            return `
                <div class="grade-section">
                    <h3 class="grade-section-title">${gradeLabel}</h3>
                    <div class="teachers-grid">${cardsHtml}</div>
                </div>
            `;
        }).join('');

        teachersTable.innerHTML = sectionsHtml;

        // Add click listeners to cards
        teachersTable.querySelectorAll('.teacher-card').forEach((card) => {
            card.addEventListener('click', () => {
                const teacherId = Number(card.dataset.teacherId);
                const teacher = teachers.find(t => t.teacher_id === teacherId);
                if (teacher) showTeacherModal(teacher);
            });
        });

        teachersTable.querySelectorAll('.teacher-edit-btn').forEach((btn) => {
            btn.addEventListener('click', (event) => {
                event.stopPropagation();
                const teacherId = Number(btn.dataset.teacherId);
                const teacher = teachers.find(t => t.teacher_id === teacherId);
                if (teacher) {
                    loadTeacherIntoForm(teacher);
                    const assignmentsTab = document.querySelector('.tab-btn[data-tab="assignments"]');
                    if (assignmentsTab) {
                        assignmentsTab.click();
                    }
                }
            });
        });
    }

    async function addSubject() {
        const name = subjectNameInput.value.trim();
        if (!name) {
            swal('Info', 'Enter a subject name.', 'info');
            return;
        }

        try {
            const res = await fetch(`${baseUrl}/settings/subjects`, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name }),
            });
            const data = await res.json();
            if (!data.success) {
                swal('Error', data.message || 'Failed to save subject.', 'error');
                return;
            }
            subjectNameInput.value = '';
            await loadSubjects();
            await loadSubjectStats();
            swal('Success', 'Subject saved.', 'success');
        } catch (error) {
            console.error('Failed to save subject', error);
            swal('Error', 'Failed to save subject.', 'error');
        }
    }

    async function deleteSubject(subjectId) {
        const confirm = await swal({
            title: 'Delete subject?',
            text: 'This will remove assignments using it.',
            icon: 'warning',
            buttons: true,
            dangerMode: true,
        });
        if (!confirm) {
            return;
        }

        try {
            const res = await fetch(`${baseUrl}/settings/subjects/delete`, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ subject_id: subjectId }),
            });
            const data = await res.json();
            if (!data.success) {
                swal('Error', data.message || 'Failed to delete subject.', 'error');
                return;
            }
            await loadSubjects();
            await loadTeachers();
            await loadSubjectStats();
            swal('Success', 'Subject deleted.', 'success');
        } catch (error) {
            console.error('Failed to delete subject', error);
            swal('Error', 'Failed to delete subject.', 'error');
        }
    }

    async function addGrade() {
        const grade = Number(gradeValueInput.value || 0);
        if (!grade || grade <= 0) {
            swal('Info', 'Enter a valid grade.', 'info');
            return;
        }

        // Parse stream names
        const streamNamesText = streamNamesInput.value.trim();
        const streamNames = streamNamesText
            ? streamNamesText.split(',').map(s => s.trim()).filter(s => s)
            : [];

        try {
            const res = await fetch(`${baseUrl}/settings/grades`, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ grade }),
            });
            const data = await res.json();
            if (!data.success) {
                swal('Error', data.message || 'Failed to save grade.', 'error');
                return;
            }

            // Get the newly created grade ID and add streams
            const gradeId = data.data?.grade_id;
            if (gradeId && streamNames.length > 0) {
                for (const streamName of streamNames) {
                    try {
                        await fetch(`${baseUrl}/settings/streams`, {
                            method: 'POST',
                            credentials: 'include',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ grade_id: gradeId, name: streamName }),
                        });
                    } catch (error) {
                        console.error(`Failed to add stream '${streamName}'`, error);
                    }
                }
            }

            gradeValueInput.value = '';
            streamNamesInput.value = '';
            await loadGrades();
            swal('Success', 'Grade and streams saved.', 'success');
        } catch (error) {
            console.error('Failed to save grade', error);
            swal('Error', 'Failed to save grade.', 'error');
        }
    }

    async function deleteGrade(gradeId) {
        const confirm = await swal({
            title: 'Delete grade?',
            text: 'This will remove assignments using it.',
            icon: 'warning',
            buttons: true,
            dangerMode: true,
        });
        if (!confirm) {
            return;
        }

        try {
            const res = await fetch(`${baseUrl}/settings/grades/delete`, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ grade_id: gradeId }),
            });
            const data = await res.json();
            if (!data.success) {
                swal('Error', data.message || 'Failed to delete grade.', 'error');
                return;
            }
            await loadGrades();
            await loadTeachers();
            swal('Success', 'Grade deleted.', 'success');
        } catch (error) {
            console.error('Failed to delete grade', error);
            swal('Error', 'Failed to delete grade.', 'error');
        }
    }

    async function saveTeacher() {
        const name = teacherNameInput.value.trim();
        const isActive = teacherActiveInput.checked ? 1 : 0;
        const teacherId = teacherIdInput && teacherIdInput.value ? Number(teacherIdInput.value) : 0;
        if (!name) {
            swal('Info', 'Enter a teacher name.', 'info');
            return;
        }

        const assignments = getSelectedAssignments();
        if (!assignments.length) {
            swal('Info', 'Select at least one subject and grade/stream.', 'info');
            return;
        }

        try {
            const res = await fetch(`${baseUrl}/settings/teachers`, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ teacher_id: teacherId, name, is_active: isActive, assignments }),
            });
            const data = await res.json();
            if (!data.success) {
                swal('Error', data.message || 'Failed to save teacher.', 'error');
                return;
            }
            resetTeacherForm();
            await loadTeachers();
            await loadSubjectStats();
            swal('Success', 'Teacher saved.', 'success');
        } catch (error) {
            console.error('Failed to save teacher', error);
            swal('Error', 'Failed to save teacher.', 'error');
        }
    }

    async function deleteTeacher(teacherId) {
        // Ensure teacherId is a number
        teacherId = Number(teacherId);
        
        if (!teacherId || teacherId <= 0) {
            swal('Error', 'Invalid teacher ID.', 'error');
            return;
        }

        const confirm = await swal({
            title: 'Delete teacher?',
            text: 'This will remove their assignments.',
            icon: 'warning',
            buttons: true,
            dangerMode: true,
        });
        if (!confirm) {
            return;
        }

        try {
            const res = await fetch(`${baseUrl}/settings/teachers/delete`, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ teacher_id: teacherId }),
            });
            const data = await res.json();
            if (!data.success) {
                swal('Error', data.message || 'Failed to delete teacher.', 'error');
                return;
            }
            await loadTeachers();
            await loadSubjectStats();
            swal('Success', 'Teacher deleted.', 'success');
        } catch (error) {
            console.error('Failed to delete teacher', error);
            swal('Error', 'Failed to delete teacher.', 'error');
        }
    }

    addSubjectBtn.addEventListener('click', addSubject);
    addGradeBtn.addEventListener('click', addGrade);
    saveTeacherBtn.addEventListener('click', saveTeacher);
    if (resetTeacherBtn) {
        resetTeacherBtn.addEventListener('click', resetTeacherForm);
    }

    await loadSubjects();
    await loadGrades();
    await loadTeachers();
    await loadSubjectStats();
})();
