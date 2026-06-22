<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if (!in_array($currentUser['role'], ['admin', 'HR'])) {
    die("Unauthorized access.");
}

// Fetch current settings into an associative array
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$settings_data = $stmt->fetchAll();

$settings = [];
foreach ($settings_data as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Dynamically get the server IP for the ADMS instructions
// Note: On XAMPP Mac, SERVER_ADDR might resolve to ::1, so we fallback to a hardcoded typical IP or use a shell script trick if needed, but since we know it's local we'll try to get the real IP.
$server_ip = $_SERVER['SERVER_ADDR'];
if ($server_ip === '::1' || $server_ip === '127.0.0.1') {
    // Attempt to get the real LAN IP
    $server_ip = getHostByName(getHostName());
    if ($server_ip === '127.0.0.1') {
        $server_ip = '192.168.1.130';
    }
}

include '../../includes/header.php'; 
?>

<div class="page-header">
    <h2>System Settings</h2>
    <div style="color: var(--text-secondary); font-size: 0.9rem;">Configure global system preferences and hardware integrations</div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div style="background: rgba(16, 185, 129, 0.1); color: var(--accent-success); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 2rem; border: 1px solid rgba(16, 185, 129, 0.2); display: flex; align-items: center; gap: 0.75rem;">
        <i class="fas fa-check-circle"></i> Settings updated successfully!
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
    <!-- General Settings Form -->
    <div class="card">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
            <h3 style="font-size: 1.25rem;"><i class="fas fa-sliders-h" style="color: var(--accent-primary); margin-right: 0.5rem;"></i> General Preferences</h3>
        </div>
        
        <form id="settingsForm" action="save.php" method="POST">
            <div class="mb-4">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Company Name</label>
                <input type="text" name="company_name" value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            
            <div class="mb-4">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">System Timezone</label>
                <select name="timezone" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <option value="Asia/Colombo" <?php echo (($settings['timezone'] ?? 'Asia/Colombo') === 'Asia/Colombo') ? 'selected' : ''; ?>>Asia/Colombo</option>
                    <option value="Asia/Kolkata" <?php echo (($settings['timezone'] ?? '') === 'Asia/Kolkata') ? 'selected' : ''; ?>>Asia/Kolkata</option>
                    <option value="UTC" <?php echo (($settings['timezone'] ?? '') === 'UTC') ? 'selected' : ''; ?>>UTC</option>
                    <option value="America/New_York" <?php echo (($settings['timezone'] ?? '') === 'America/New_York') ? 'selected' : ''; ?>>America/New_York</option>
                </select>
            </div>

            <div class="mb-4">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Default Currency Symbol</label>
                <input type="text" name="currency" value="<?php echo htmlspecialchars($settings['currency'] ?? 'LKR'); ?>" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>

            <div class="mb-4">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Public Holiday Country</label>
                <select name="holiday_country" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <option value="LK" <?php echo (($settings['holiday_country'] ?? 'LK') === 'LK') ? 'selected' : ''; ?>>Sri Lanka (LK)</option>
                    <option value="IN" <?php echo (($settings['holiday_country'] ?? '') === 'IN') ? 'selected' : ''; ?>>India (IN)</option>
                    <option value="US" <?php echo (($settings['holiday_country'] ?? '') === 'US') ? 'selected' : ''; ?>>United States (US)</option>
                    <option value="GB" <?php echo (($settings['holiday_country'] ?? '') === 'GB') ? 'selected' : ''; ?>>United Kingdom (GB)</option>
                </select>
                <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">Used by the Holidays page to import public holidays automatically.</small>
            </div>
            
            <div style="display: flex; gap: 1rem;">
                <a href="audit.php" class="btn" style="background: var(--bg-hover); color: var(--accent-primary); border: 1px solid rgba(59, 130, 246, 0.3);">
                    <i class="fas fa-shield-alt"></i> Compliance Audit Logs
                </a>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('settingsForm').submit()">
                    <i class="fas fa-save"></i> Save Configuration
                </button>
            </div>
        </form>
    </div>

    <!-- ADMS Integration Guide -->
    <div class="card" style="background: linear-gradient(135deg, rgba(59,130,246,0.05), rgba(16,185,129,0.05)); border: 1px solid rgba(59,130,246,0.15);">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
            <h3 style="font-size: 1.25rem;"><i class="fas fa-satellite-dish" style="color: var(--accent-success); margin-right: 0.5rem;"></i> ZKTeco ADMS Configuration</h3>
            <span class="status-badge status-active">Listening</span>
        </div>
        
        <p style="color: var(--text-secondary); line-height: 1.6; margin-bottom: 1.5rem;">
            To connect a physical ZKTeco biometric device to this system, navigate to the device's <strong>Cloud Server Settings (ADMS)</strong> menu and enter the exact details below.
        </p>

        <div style="background: var(--bg-main); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 1.5rem; font-family: monospace; font-size: 0.95rem;">
            <div style="display: grid; grid-template-columns: 150px 1fr; margin-bottom: 0.5rem;">
                <span style="color: var(--text-muted);">Server Address:</span>
                <span style="color: var(--accent-primary); font-weight: bold;"><?php echo htmlspecialchars($server_ip); ?></span>
            </div>
            <div style="display: grid; grid-template-columns: 150px 1fr; margin-bottom: 0.5rem;">
                <span style="color: var(--text-muted);">Server Port:</span>
                <span style="color: var(--text-primary); font-weight: bold;">80</span>
            </div>
            <div style="display: grid; grid-template-columns: 150px 1fr; margin-bottom: 0.5rem;">
                <span style="color: var(--text-muted);">Enable Domain:</span>
                <span style="color: var(--accent-danger); font-weight: bold;">OFF / No</span>
            </div>
            <div style="display: grid; grid-template-columns: 150px 1fr;">
                <span style="color: var(--text-muted);">Enable HTTPS:</span>
                <span style="color: var(--accent-danger); font-weight: bold;">OFF / No</span>
            </div>
        </div>

        <div style="font-size: 0.85rem; color: var(--text-secondary); display: flex; gap: 0.75rem; align-items: flex-start;">
            <i class="fas fa-info-circle" style="color: var(--accent-primary); margin-top: 0.2rem;"></i>
            <span>The system utilizes an internal Apache URL rewriter to natively ingest <code>/iclock/cdata</code> HTTP push requests without any external middleware.</span>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
