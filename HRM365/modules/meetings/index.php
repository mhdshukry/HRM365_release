<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

function meeting_location_html(?string $location): string
{
    $location = trim((string) $location);
    if ($location === '') {
        return '<span class="meeting-location"><i class="fas fa-map-marker-alt"></i> No Location</span>';
    }
    $safe = htmlspecialchars($location, ENT_QUOTES, 'UTF-8');
    if (preg_match('/^https?:\/\//i', $location)) {
        return '<a class="meeting-location meeting-location-link" href="' . $safe . '" target="_blank" rel="noopener"><i class="fas fa-link"></i> ' . $safe . '</a>';
    }
    return '<span class="meeting-location"><i class="fas fa-map-marker-alt"></i> ' . $safe . '</span>';
}

if ($currentUser['role'] === 'employee') {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, employee_code, department FROM employees WHERE id = ?");
    $stmt->execute([$currentUser['employee_id'] ?? 0]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($currentUser['role'] === 'manager') {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, employee_code, department FROM employees WHERE status = 'Active' AND department = ? ORDER BY first_name ASC");
    $stmt->execute([$currentUser['department'] ?? '']);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $employees = $pdo->query("SELECT id, first_name, last_name, employee_code, department FROM employees WHERE status = 'Active' ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC);
}
$employeePickerData = array_map(function ($employee) {
    return [
        'id' => intval($employee['id']),
        'name' => trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')),
        'code' => $employee['employee_code'] ?? '',
        'department' => $employee['department'] ?: 'No department',
    ];
}, $employees);

$meetingSql = "
    SELECT m.*, e.first_name, e.last_name,
           (
                SELECT GROUP_CONCAT(CONCAT(a.first_name, ' ', a.last_name) ORDER BY a.first_name ASC SEPARATOR ', ')
                FROM employees a
                WHERE FIND_IN_SET(a.id, COALESCE(m.attendees, ''))
           ) AS attendee_names
    FROM meetings m 
    JOIN employees e ON m.organizer_id = e.id 
";
$params = [];
if ($currentUser['role'] === 'employee') {
    $meetingSql .= " WHERE (m.organizer_id = ? OR FIND_IN_SET(?, COALESCE(m.attendees, '')))";
    $params[] = $currentUser['employee_id'] ?? 0;
    $params[] = $currentUser['employee_id'] ?? 0;
} elseif ($currentUser['role'] === 'manager') {
    $meetingSql .= " WHERE (e.department = ? OR EXISTS (
        SELECT 1 FROM employees ae
        WHERE ae.department = ? AND FIND_IN_SET(ae.id, COALESCE(m.attendees, ''))
    ))";
    $params[] = $currentUser['department'] ?? '';
    $params[] = $currentUser['department'] ?? '';
}
$meetingSql .= " ORDER BY m.start_time DESC";

$stmt = $pdo->prepare($meetingSql);
$stmt->execute($params);
$meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php'; 
?>

<style>
    .meetings-card {
        overflow: hidden;
    }
    .meeting-alert {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.8rem 1rem;
        border-radius: var(--radius-md);
        margin-bottom: 1rem;
        font-size: 0.9rem;
        font-weight: 700;
    }
    .meeting-alert-success {
        background: rgba(16, 185, 129, 0.1);
        color: var(--accent-success);
        border: 1px solid rgba(16, 185, 129, 0.2);
    }
    .meeting-alert-error {
        background: rgba(239, 68, 68, 0.1);
        color: var(--accent-danger);
        border: 1px solid rgba(239, 68, 68, 0.2);
    }
    .meetings-table {
        min-width: 980px;
    }
    .meeting-title {
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }
    .meeting-description {
        font-size: 0.8rem;
        color: var(--text-secondary);
        max-width: 280px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .meeting-time {
        font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
        font-size: 0.88rem;
        color: var(--text-primary);
        white-space: nowrap;
    }
    .meeting-time div + div {
        color: var(--text-secondary);
        font-size: 0.8rem;
        margin-top: 0.25rem;
    }
    .meeting-location {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        max-width: 260px;
        padding: 0.35rem 0.6rem;
        border-radius: var(--radius-md);
        background: var(--bg-hover);
        color: var(--text-secondary);
        font-size: 0.82rem;
        font-weight: 700;
        text-decoration: none;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .meeting-location-link {
        color: var(--accent-primary);
        background: rgba(59, 130, 246, 0.09);
    }
    .meeting-people {
        color: var(--text-muted);
        font-size: 0.78rem;
        margin-top: 0.18rem;
        max-width: 260px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .meeting-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    .meeting-field {
        margin-bottom: 1rem;
    }
    .meeting-field label {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--text-secondary);
        font-size: 0.9rem;
        font-weight: 700;
    }
    .meeting-field input,
    .meeting-field select,
    .meeting-field textarea {
        width: 100%;
        padding: 0.75rem;
        border-radius: var(--radius-md);
        border: 1px solid var(--border-color);
        background: var(--bg-secondary);
        color: var(--text-primary);
        outline: none;
    }
    .meeting-modal {
        display: none;
        position: fixed;
        inset: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.48);
        z-index: 1000;
        justify-content: center;
        align-items: center;
        padding: 1rem;
        backdrop-filter: blur(4px);
    }
    .meeting-modal-panel {
        background: var(--bg-main);
        width: min(760px, 96vw);
        max-height: min(92vh, 820px);
        border-radius: var(--radius-lg);
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.22);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        border: 1px solid var(--border-color);
    }
    .meeting-modal-header {
        padding: 1.15rem 1.35rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        background: var(--bg-card);
    }
    .meeting-modal-title {
        margin: 0;
        color: var(--text-primary);
        font-size: 1.18rem;
        font-weight: 900;
    }
    .meeting-modal-subtitle {
        margin-top: 0.2rem;
        color: var(--text-muted);
        font-size: 0.82rem;
        font-weight: 700;
    }
    .meeting-modal-close {
        width: 36px;
        height: 36px;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        background: var(--bg-secondary);
        color: var(--text-muted);
        cursor: pointer;
    }
    .meeting-modal-body {
        padding: 1.35rem;
        overflow-y: auto;
    }
    .meeting-picker {
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        background: var(--bg-secondary);
        padding: 0.7rem;
    }
    .meeting-picker-search {
        position: relative;
        margin-bottom: 0.7rem;
    }
    .meeting-picker-search i {
        position: absolute;
        left: 0.8rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-size: 0.85rem;
    }
    .meeting-picker-search input {
        padding-left: 2.2rem;
        background: var(--bg-card);
    }
    .meeting-selected-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
        min-height: 38px;
        padding: 0.5rem;
        border: 1px dashed var(--border-color);
        border-radius: var(--radius-md);
        background: var(--bg-card);
        margin-bottom: 0.7rem;
    }
    .meeting-selected-empty {
        color: var(--text-muted);
        font-size: 0.82rem;
        font-weight: 700;
        padding: 0.25rem 0.1rem;
    }
    .meeting-attendee-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.35rem 0.5rem;
        border-radius: 999px;
        background: rgba(59, 130, 246, 0.1);
        color: var(--accent-primary);
        font-size: 0.78rem;
        font-weight: 800;
    }
    .meeting-attendee-chip button {
        border: 0;
        background: transparent;
        color: inherit;
        cursor: pointer;
        padding: 0;
        line-height: 1;
    }
    .meeting-picker-results {
        display: grid;
        gap: 0.45rem;
        max-height: 220px;
        overflow-y: auto;
    }
    .meeting-picker-result {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.7rem;
        padding: 0.55rem 0.65rem;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        background: var(--bg-card);
    }
    .meeting-picker-result-main {
        min-width: 0;
    }
    .meeting-attendee-name {
        display: block;
        font-size: 0.85rem;
        font-weight: 800;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .meeting-attendee-dept {
        display: block;
        margin-top: 0.08rem;
        color: var(--text-muted);
        font-size: 0.72rem;
        font-weight: 700;
    }
    .meeting-picker-add {
        flex: 0 0 auto;
        min-height: 30px;
        padding: 0.35rem 0.55rem;
        border-radius: var(--radius-md);
        border: 1px solid rgba(59, 130, 246, 0.25);
        background: rgba(59, 130, 246, 0.1);
        color: var(--accent-primary);
        font-size: 0.78rem;
        font-weight: 800;
        cursor: pointer;
    }
    .meeting-picker-add:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }    .meeting-form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.6rem;
        padding-top: 0.2rem;
    }
    @media (max-width: 700px) {
        .meeting-form-grid {
            grid-template-columns: 1fr;
        }
        .meeting-modal-body {
            padding: 1rem;
        }
        .meeting-form-actions {
            flex-direction: column-reverse;
        }
        .meeting-form-actions .btn {
            width: 100%;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">Meetings</h1>
        <div class="page-subtitle">Schedule and manage organizational meetings.</div>
    </div>
    <button onclick="document.getElementById('addModal').style.display='flex'" class="btn btn-primary">
        <i class="fas fa-plus"></i> Schedule Meeting
    </button>
</div>

<?php if (isset($_GET['success']) && $_GET['success'] === 'scheduled'): ?>
    <div class="meeting-alert meeting-alert-success">
        <i class="fas fa-check-circle"></i>
        Meeting scheduled successfully.
    </div>
<?php endif; ?>
<?php if (!empty($_GET['error'])): ?>
    <div class="meeting-alert meeting-alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($_GET['error']); ?>
    </div>
<?php endif; ?>

<div class="card meetings-card">
    <div class="table-container">
        <table class="table meetings-table">
            <thead>
                <tr>
                    <th>Meeting</th>
                    <th>Date & Time</th>
                    <th>Location/Link</th>
                    <th>Organizer</th>
                    <th>Attendees</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($meetings as $m): ?>
                <tr>
                    <td>
                        <div class="meeting-title"><?php echo htmlspecialchars($m['title']); ?></div>
                        <div class="meeting-description" title="<?php echo htmlspecialchars($m['description']); ?>">
                            <?php echo htmlspecialchars($m['description']); ?>
                        </div>
                    </td>
                    <td class="meeting-time">
                        <div><i class="far fa-calendar" style="color: var(--accent-primary); margin-right: 0.3rem;"></i> <?php echo date('M d, Y', strtotime($m['start_time'])); ?></div>
                        <div><i class="far fa-clock" style="margin-right: 0.3rem;"></i> <?php echo date('h:i A', strtotime($m['start_time'])) . ' - ' . date('h:i A', strtotime($m['end_time'])); ?></div>
                    </td>
                    <td>
                        <?php echo meeting_location_html($m['location'] ?? ''); ?>
                    </td>
                    <td>
                        <div style="font-weight: 500; color: var(--text-primary);"><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></div>
                    </td>
                    <td>
                        <div class="meeting-people" title="<?php echo htmlspecialchars($m['attendee_names'] ?: 'No attendees selected'); ?>">
                            <?php echo htmlspecialchars($m['attendee_names'] ?: 'No attendees selected'); ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($m['status'] === 'Scheduled'): ?>
                            <span class="status-badge" style="background: rgba(59, 130, 246, 0.1); color: var(--accent-primary);"><i class="fas fa-calendar-check"></i> Scheduled</span>
                        <?php elseif ($m['status'] === 'Completed'): ?>
                            <span class="status-badge" style="background: rgba(16, 185, 129, 0.1); color: var(--accent-success);"><i class="fas fa-check-double"></i> Completed</span>
                        <?php else: ?>
                            <span class="status-badge" style="background: rgba(239, 68, 68, 0.1); color: var(--accent-danger);"><i class="fas fa-ban"></i> Cancelled</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($meetings)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 3rem;">
                        <i class="fas fa-users" style="font-size: 2.5rem; margin-bottom: 1rem; color: var(--border-color);"></i><br>
                        No meetings scheduled yet.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Meeting Modal -->
<div id="addModal" class="meeting-modal">
    <div class="meeting-modal-panel">
        <div class="meeting-modal-header">
            <div>
                <h3 class="meeting-modal-title">Schedule Meeting</h3>
                <div class="meeting-modal-subtitle">Create a calendar meeting with attendees and a room or online link.</div>
            </div>
            <button type="button" onclick="document.getElementById('addModal').style.display='none'" class="meeting-modal-close"><i class="fas fa-times"></i></button>
        </div>
        <form action="save.php" method="POST" class="meeting-modal-body">
            <div class="meeting-field">
                <label>Meeting Title *</label>
                <input type="text" name="title" required>
            </div>
            
            <div class="meeting-form-grid">
                <div class="meeting-field" style="margin-bottom: 0;">
                    <label>Start Time *</label>
                    <input type="datetime-local" name="start_time" required style="color-scheme: light;">
                </div>
                <div class="meeting-field" style="margin-bottom: 0;">
                    <label>End Time *</label>
                    <input type="datetime-local" name="end_time" required style="color-scheme: light;">
                </div>
            </div>

            <div class="meeting-field">
                <label>Location or Meeting Link</label>
                <input type="text" name="location" placeholder="e.g. Conference Room A or https://meet.google.com/...">
            </div>

            <div class="meeting-field">
                <label>Organizer *</label>
                <select name="organizer_id" required>
                    <?php foreach ($employees as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo intval($e['id']) === intval($currentUser['employee_id'] ?? 0) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($e['first_name'] . ' ' . $e['last_name'] . ' - ' . ($e['department'] ?: 'No department')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="meeting-field">
                <label>Attendees</label>
                <div class="meeting-picker" data-attendee-picker>
                    <div class="meeting-picker-search">
                        <i class="fas fa-search"></i>
                        <input type="search" data-attendee-search placeholder="Search employee name, code, or department">
                    </div>
                    <div class="meeting-selected-chips" data-attendee-selected>
                        <span class="meeting-selected-empty">No attendees selected</span>
                    </div>
                    <div class="meeting-picker-results" data-attendee-results></div>
                    <div data-attendee-inputs></div>
                </div>
            </div>
            <div class="meeting-field" style="margin-top: 1rem; margin-bottom: 1.5rem;">
                <label>Description & Agenda</label>
                <textarea name="description" rows="3"></textarea>
            </div>

            <div class="meeting-form-actions">
                <button type="button" onclick="document.getElementById('addModal').style.display='none'" class="btn" style="background: var(--bg-hover); color: var(--text-primary);">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Schedule</button>
            </div>
        </form>
    </div>
</div>

<script>
const meetingEmployees = <?php echo json_encode($employeePickerData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('addModal');
    if (!modal) return;

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.style.display === 'flex') {
            modal.style.display = 'none';
        }
    });

    const searchInput = document.querySelector('[data-attendee-search]');
    const resultsEl = document.querySelector('[data-attendee-results]');
    const selectedEl = document.querySelector('[data-attendee-selected]');
    const inputsEl = document.querySelector('[data-attendee-inputs]');
    const selected = new Map();

    function employeeLabel(employee) {
        return [employee.name, employee.code, employee.department].filter(Boolean).join(' ');
    }

    function renderSelected() {
        selectedEl.innerHTML = '';
        inputsEl.innerHTML = '';
        if (selected.size === 0) {
            selectedEl.innerHTML = '<span class="meeting-selected-empty">No attendees selected</span>';
            return;
        }
        selected.forEach(function (employee, id) {
            const chip = document.createElement('span');
            chip.className = 'meeting-attendee-chip';
            chip.innerHTML = `<span>${escapeHtml(employee.name)}</span><button type="button" aria-label="Remove ${escapeHtml(employee.name)}"><i class="fas fa-times"></i></button>`;
            chip.querySelector('button').addEventListener('click', function () {
                selected.delete(id);
                renderSelected();
                renderResults();
            });
            selectedEl.appendChild(chip);

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'attendees[]';
            input.value = id;
            inputsEl.appendChild(input);
        });
    }

    function renderResults() {
        if (!resultsEl || !searchInput) return;
        const query = searchInput.value.trim().toLowerCase();
        const matches = meetingEmployees.filter(function (employee) {
            if (selected.has(String(employee.id))) return false;
            if (!query) return true;
            return employeeLabel(employee).toLowerCase().includes(query);
        }).slice(0, 12);

        resultsEl.innerHTML = '';
        if (matches.length === 0) {
            resultsEl.innerHTML = '<div class="meeting-selected-empty">No matching employees</div>';
            return;
        }
        matches.forEach(function (employee) {
            const row = document.createElement('div');
            row.className = 'meeting-picker-result';
            row.innerHTML = `
                <div class="meeting-picker-result-main">
                    <span class="meeting-attendee-name">${escapeHtml(employee.name)} ${employee.code ? '(' + escapeHtml(employee.code) + ')' : ''}</span>
                    <span class="meeting-attendee-dept">${escapeHtml(employee.department || 'No department')}</span>
                </div>
                <button type="button" class="meeting-picker-add"><i class="fas fa-plus"></i> Add</button>
            `;
            row.querySelector('button').addEventListener('click', function () {
                selected.set(String(employee.id), employee);
                searchInput.value = '';
                renderSelected();
                renderResults();
                searchInput.focus();
            });
            resultsEl.appendChild(row);
        });
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'})[char];
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', renderResults);
        renderSelected();
        renderResults();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
