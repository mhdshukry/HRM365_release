<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if ($currentUser['role'] === 'employee') {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, employee_code FROM employees WHERE id = ?");
    $stmt->execute([$currentUser['employee_id'] ?? 0]);
    $employees = $stmt->fetchAll();
} elseif ($currentUser['role'] === 'manager') {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, employee_code FROM employees WHERE department = ? ORDER BY first_name ASC");
    $stmt->execute([$currentUser['department'] ?? '']);
    $employees = $stmt->fetchAll();
} else {
    $stmt = $pdo->query("SELECT id, first_name, last_name, employee_code FROM employees ORDER BY first_name ASC");
    $employees = $stmt->fetchAll();
}

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Submit Regularization</h1>
        <div class="page-subtitle">Request a mathematical correction for a broken timesheet.</div>
    </div>
    <a href="index.php" class="btn" style="background: var(--bg-hover); color: var(--text-primary);">
        <i class="fas fa-arrow-left"></i> Cancel
    </a>
</div>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <form action="save.php" method="POST" id="regularizationForm">
        
        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Target Timesheet</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Select Employee *</label>
                <select name="employee_id" id="employeeSelect" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <option value="">Choose employee...</option>
                    <?php foreach ($employees as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo count($employees) === 1 ? 'selected' : ''; ?>><?php echo htmlspecialchars($e['first_name'] . ' ' . $e['last_name'] . ' (' . $e['employee_code'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Target Date *</label>
                <input type="date" name="date" id="targetDate" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light;">
                <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">The system will automatically find the original timesheet for this date to preserve the audit trail.</small>
            </div>
        </div>

        <div id="regularizationContext" style="display: none; margin-bottom: 2rem; padding: 1rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 0.9rem; flex-wrap: wrap;">
                <div style="font-weight: 700; color: var(--text-primary);"><i class="fas fa-info-circle"></i> Selected Date Summary</div>
                <span id="regularizationDecision" class="status-badge" style="background: rgba(59, 130, 246, 0.1); color: var(--accent-primary);">Select date</span>
            </div>
            <div id="regularizationContextBody" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 0.75rem; color: var(--text-secondary); font-size: 0.9rem;"></div>
            <div id="regularizationBlockMessage" style="display: none; margin-top: 1rem; padding: 0.85rem 1rem; border-radius: var(--radius-md); background: rgba(239, 68, 68, 0.08); color: var(--accent-danger); border: 1px solid rgba(239, 68, 68, 0.18); font-weight: 600;"></div>
        </div>

        <h3 class="mb-4" style="color: var(--accent-warning); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Correction Data</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Corrected Sign-In Time *</label>
                <input type="time" name="requested_clock_in" id="requestedClockIn" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Corrected Sign-Out Time *</label>
                <input type="time" name="requested_clock_out" id="requestedClockOut" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light;">
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Reason for Regularization *</label>
                <textarea name="reason" required rows="3" placeholder="e.g. Forgot to sign out yesterday due to emergency..." style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;"></textarea>
            </div>
        </div>

        <div style="text-align: right; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
            <button type="submit" class="btn btn-primary" id="submitRegularization" style="padding: 1rem 3rem; font-size: 1.1rem;">
                <i class="fas fa-paper-plane"></i> Submit Request
            </button>
        </div>
    </form>
</div>

<script>
    const employeeSelect = document.getElementById('employeeSelect');
    const targetDate = document.getElementById('targetDate');
    const contextBox = document.getElementById('regularizationContext');
    const contextBody = document.getElementById('regularizationContextBody');
    const contextDecision = document.getElementById('regularizationDecision');
    const blockMessage = document.getElementById('regularizationBlockMessage');
    const requestedClockIn = document.getElementById('requestedClockIn');
    const requestedClockOut = document.getElementById('requestedClockOut');
    const submitRegularization = document.getElementById('submitRegularization');
    const regularizationForm = document.getElementById('regularizationForm');

    function timeOnly(value) {
        return value ? value.substring(11, 16) : '';
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));
    }

    function contextRow(label, value) {
        return `
            <div style="padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-primary);">
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; margin-bottom: 0.25rem;">${escapeHtml(label)}</div>
                <div style="color: var(--text-primary); font-weight: 600; line-height: 1.45;">${value}</div>
            </div>
        `;
    }

    function setSubmitState(isAllowed, message = '') {
        submitRegularization.disabled = !isAllowed;
        submitRegularization.style.opacity = isAllowed ? '1' : '0.55';
        submitRegularization.style.cursor = isAllowed ? 'pointer' : 'not-allowed';
        blockMessage.style.display = message ? 'block' : 'none';
        blockMessage.textContent = message;
    }

    async function loadRegularizationContext() {
        const employeeId = employeeSelect.value;
        const date = targetDate.value;
        if (!employeeId || !date) {
            contextBox.style.display = 'none';
            setSubmitState(true);
            return;
        }

        contextBox.style.display = 'block';
        contextBody.innerHTML = contextRow('Status', 'Loading selected date...');
        contextDecision.textContent = 'Checking';
        contextDecision.style.background = 'rgba(59, 130, 246, 0.1)';
        contextDecision.style.color = 'var(--accent-primary)';
        setSubmitState(false);

        try {
            const response = await fetch(`ajax_context.php?employee_id=${encodeURIComponent(employeeId)}&date=${encodeURIComponent(date)}`);
            const data = await response.json();
            if (!response.ok) {
                contextBody.innerHTML = contextRow('Error', escapeHtml(data.error || 'Could not load selected date.'));
                contextDecision.textContent = 'Error';
                contextDecision.style.background = 'rgba(239, 68, 68, 0.1)';
                contextDecision.style.color = 'var(--accent-danger)';
                setSubmitState(false, data.error || 'Could not load selected date.');
                return;
            }

            const attendance = data.attendance || null;
            const leaveText = data.leaves.length
                ? data.leaves.map((leave) => `${escapeHtml(leave.leave_type)} (${escapeHtml(leave.status)}, ${parseFloat(leave.total_days).toFixed(2)} day(s), ${parseInt(leave.is_paid, 10) ? 'Paid' : 'Unpaid'})`).join('<br>')
                : 'No leave application found for this date';
            const dayContext = data.day_context || {};
            const blockStatus = dayContext.block_status || '';
            const isAllowed = !blockStatus;

            if (attendance) {
                requestedClockIn.value = timeOnly(attendance.clock_in) || requestedClockIn.value;
                requestedClockOut.value = timeOnly(attendance.clock_out) || requestedClockOut.value;
            } else {
                requestedClockIn.value = '';
                requestedClockOut.value = '';
            }

            const flags = [];
            if (attendance && parseInt(attendance.is_late, 10)) flags.push('Late arrival');
            if (attendance && parseInt(attendance.is_early_departure, 10)) flags.push('Early out');
            if (attendance && parseInt(attendance.is_absent, 10)) flags.push('No punch / unpaid absence');
            if (parseInt(data.calendar.is_holiday, 10)) flags.push('Holiday');
            if (parseInt(data.calendar.is_weekend, 10)) flags.push('Weekend');

            contextDecision.textContent = isAllowed ? 'Regularization allowed' : blockStatus;
            contextDecision.style.background = isAllowed ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)';
            contextDecision.style.color = isAllowed ? 'var(--accent-success)' : 'var(--accent-danger)';
            setSubmitState(isAllowed, isAllowed ? '' : `Regularization is blocked for this date because it is marked as ${blockStatus}.`);

            contextBody.innerHTML = [
                contextRow('Employee', `${escapeHtml(data.employee.name)} (${escapeHtml(data.employee.code)})`),
                contextRow('Work Status', escapeHtml(dayContext.work_status || 'Working Day')),
                contextRow('Shift', `${escapeHtml(data.employee.shift)} ${escapeHtml(data.employee.start_time || '')}${data.employee.end_time ? ' - ' + escapeHtml(data.employee.end_time) : ''}`),
                contextRow('Policy', `${escapeHtml(data.employee.policy)} | Late grace ${parseInt(data.employee.late_grace, 10)} min | Early grace ${parseInt(data.employee.early_grace, 10)} min`),
                contextRow('Original Sign-In / Sign-Out', attendance ? `${timeOnly(attendance.clock_in) || '--:--'} to ${timeOnly(attendance.clock_out) || '--:--'}` : 'No attendance record yet'),
                contextRow('Attendance Status', attendance ? escapeHtml(attendance.status) : 'Missing / not generated yet'),
                contextRow('Flags', flags.length ? escapeHtml(flags.join(', ')) : '-'),
                contextRow('Leave', leaveText)
            ].join('');
        } catch (error) {
            contextBody.innerHTML = contextRow('Error', 'Could not load selected date.');
            contextDecision.textContent = 'Error';
            contextDecision.style.background = 'rgba(239, 68, 68, 0.1)';
            contextDecision.style.color = 'var(--accent-danger)';
            setSubmitState(false, 'Could not load selected date.');
        }
    }

    regularizationForm.addEventListener('submit', (event) => {
        if (submitRegularization.disabled) {
            event.preventDefault();
        }
    });
    employeeSelect.addEventListener('change', loadRegularizationContext);
    targetDate.addEventListener('change', loadRegularizationContext);
    loadRegularizationContext();
</script>

<?php include '../../includes/footer.php'; ?>
