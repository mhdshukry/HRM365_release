<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if ($currentUser['role'] === 'employee') {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, department FROM employees WHERE id = ?");
    $stmt->execute([$currentUser['employee_id'] ?? 0]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($currentUser['role'] === 'manager') {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, department FROM employees WHERE status = 'Active' AND department = ? ORDER BY first_name ASC");
    $stmt->execute([$currentUser['department'] ?? '']);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $employees = $pdo->query("SELECT id, first_name, last_name, department FROM employees WHERE status = 'Active' ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

$meetingSql = "
    SELECT m.*, e.first_name, e.last_name 
    FROM meetings m 
    JOIN employees e ON m.organizer_id = e.id 
";
$params = [];
if ($currentUser['role'] === 'employee') {
    $meetingSql .= " WHERE m.organizer_id = ?";
    $params[] = $currentUser['employee_id'] ?? 0;
} elseif ($currentUser['role'] === 'manager') {
    $meetingSql .= " WHERE e.department = ?";
    $params[] = $currentUser['department'] ?? '';
}
$meetingSql .= " ORDER BY m.start_time DESC";

$stmt = $pdo->prepare($meetingSql);
$stmt->execute($params);
$meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Meetings</h1>
        <div class="page-subtitle">Schedule and manage organizational meetings.</div>
    </div>
    <button onclick="document.getElementById('addModal').style.display='flex'" class="btn btn-primary">
        <i class="fas fa-plus"></i> Schedule Meeting
    </button>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Meeting</th>
                    <th>Date & Time</th>
                    <th>Location/Link</th>
                    <th>Organizer</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($meetings as $m): ?>
                <tr>
                    <td>
                        <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 0.2rem;"><?php echo htmlspecialchars($m['title']); ?></div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($m['description']); ?>">
                            <?php echo htmlspecialchars($m['description']); ?>
                        </div>
                    </td>
                    <td style="font-family: monospace; font-size: 0.9rem; color: var(--text-primary);">
                        <div style="margin-bottom: 0.2rem;"><i class="far fa-calendar" style="color: var(--accent-primary); margin-right: 0.3rem;"></i> <?php echo date('M d, Y', strtotime($m['start_time'])); ?></div>
                        <div style="color: var(--text-secondary); font-size: 0.8rem;"><i class="far fa-clock" style="margin-right: 0.3rem;"></i> <?php echo date('h:i A', strtotime($m['start_time'])) . ' - ' . date('h:i A', strtotime($m['end_time'])); ?></div>
                    </td>
                    <td>
                        <span style="background: var(--bg-hover); padding: 0.3rem 0.6rem; border-radius: var(--radius-md); font-size: 0.85rem; color: var(--text-secondary);">
                            <i class="fas fa-map-marker-alt" style="margin-right: 0.3rem;"></i> <?php echo htmlspecialchars($m['location'] ?: 'No Location'); ?>
                        </span>
                    </td>
                    <td>
                        <div style="font-weight: 500; color: var(--text-primary);"><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></div>
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
                    <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 3rem;">
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
<div id="addModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
    <div style="background: var(--bg-main); width: 90%; max-width: 600px; border-radius: var(--radius-lg); box-shadow: 0 10px 25px rgba(0,0,0,0.2); overflow: hidden;">
        <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: var(--text-primary); font-size: 1.25rem;">Schedule Meeting</h3>
            <button onclick="document.getElementById('addModal').style.display='none'" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;"><i class="fas fa-times"></i></button>
        </div>
        <form action="save.php" method="POST" style="padding: 1.5rem;">
            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Meeting Title *</label>
                <input type="text" name="title" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Start Time *</label>
                    <input type="datetime-local" name="start_time" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">End Time *</label>
                    <input type="datetime-local" name="end_time" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light;">
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Location or Link</label>
                <input type="text" name="location" placeholder="e.g. Conference Room A or Zoom Link" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Organizer *</label>
                <select name="organizer_id" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <?php foreach ($employees as $e): ?>
                        <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['first_name'] . ' ' . $e['last_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Description & Agenda</label>
                <textarea name="description" rows="3" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;"></textarea>
            </div>

            <div style="text-align: right;">
                <button type="button" onclick="document.getElementById('addModal').style.display='none'" class="btn" style="background: var(--bg-hover); color: var(--text-primary); margin-right: 0.5rem;">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Schedule</button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
