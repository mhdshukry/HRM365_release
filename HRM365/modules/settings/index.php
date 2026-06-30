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
$enableOvertime = (($settings['payroll_enable_overtime'] ?? '1') === '1');
$enableEpf = (($settings['payroll_enable_epf'] ?? '1') === '1');
$enableEtf = (($settings['payroll_enable_etf'] ?? '1') === '1');

$deviceStats = [];
try {
    $statsRows = $pdo->query("
        SELECT terminal_sn,
               COUNT(*) AS punch_count,
               MAX(punch_time) AS last_punch,
               SUM(CASE WHEN is_synced = 0 THEN 1 ELSE 0 END) AS pending_sync
        FROM biometric_punches
        GROUP BY terminal_sn
        ORDER BY MAX(punch_time) DESC
    ")->fetchAll();
    foreach ($statsRows as $row) {
        $deviceStats[(string) ($row['terminal_sn'] ?: 'UNKNOWN_DEVICE')] = $row;
    }
} catch (Throwable $e) {
    $deviceStats = [];
}

$deviceRows = [];
$onlineWindowSeconds = 600;
$admsLogFile = __DIR__ . '/../../adms_debug.log';
if (is_readable($admsLogFile)) {
    $lines = file($admsLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach (array_slice($lines, -2000) as $line) {
        if (!preg_match('/^\[(?<time>[^\]]+)\]\s+(?<ip>\S+)\s+(?<uri>\S+)/', $line, $matches)) {
            continue;
        }
        $seenAt = strtotime($matches['time']);
        if ($seenAt === false || $seenAt < (time() - $onlineWindowSeconds)) {
            continue;
        }
        $uri = $matches['uri'];
        if (strpos($uri, '/iclock/getrequest') === false && strpos($uri, '/iclock/cdata') === false) {
            continue;
        }
        $query = parse_url($uri, PHP_URL_QUERY) ?: '';
        parse_str($query, $queryParts);
        $sn = trim((string) ($queryParts['SN'] ?? ''));
        if ($sn === '') {
            continue;
        }
        $stats = $deviceStats[$sn] ?? [
            'terminal_sn' => $sn,
            'punch_count' => 0,
            'last_punch' => null,
            'pending_sync' => 0,
        ];
        $existingSeenAt = isset($deviceRows[$sn]['seen_at_timestamp']) ? intval($deviceRows[$sn]['seen_at_timestamp']) : 0;
        if ($seenAt >= $existingSeenAt) {
            $deviceRows[$sn] = array_merge($stats, [
                'terminal_sn' => $sn,
                'last_seen' => date('Y-m-d H:i:s', $seenAt),
                'seen_at_timestamp' => $seenAt,
                'ip_address' => $matches['ip'],
                'endpoint' => strpos($uri, '/iclock/cdata') !== false ? 'cdata' : 'getrequest',
            ]);
        }
    }
}
usort($deviceRows, fn($a, $b) => intval($b['seen_at_timestamp']) <=> intval($a['seen_at_timestamp']));
$connectedDeviceCount = count($deviceRows);

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

            <div style="padding: 1rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary); margin-bottom: 1.5rem;">
                <div style="font-weight: 800; color: var(--text-primary); margin-bottom: 0.75rem;">Login SMS Messages</div>
                <label style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.9rem; color: var(--text-secondary); font-weight: 700;">
                    <input type="checkbox" name="sms_enabled" value="1" <?php echo (($settings['sms_enabled'] ?? '1') === '1') ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--accent-primary);">
                    Send username/password SMS when a user is created or password is reset
                </label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
                    <div>
                        <label style="display: block; margin-bottom: 0.45rem; color: var(--text-secondary); font-size: 0.85rem;">Text.lk API Key</label>
                        <input type="password" name="sms_api_key" value="" placeholder="<?php echo !empty($settings['sms_api_key']) ? 'Saved API key is protected' : 'Paste Text.lk API key'; ?>" autocomplete="new-password" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-primary); outline: none;">
                        <small style="color: var(--text-muted); display: block; margin-top: 0.4rem;">Leave blank to keep the saved API key.</small>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 0.45rem; color: var(--text-secondary); font-size: 0.85rem;">Sender ID</label>
                        <input type="text" name="sms_sender_name" value="<?php echo htmlspecialchars($settings['sms_sender_name'] ?? 'Pos365.lk'); ?>" placeholder="e.g. Pos365.lk" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-primary); outline: none;">
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                <div style="grid-column: 1 / -1; padding: 1rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);">
                    <div style="font-weight: 800; color: var(--text-primary); margin-bottom: 0.75rem;">Payroll Feature Switches</div>
                    <label style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.55rem; color: var(--text-secondary); font-weight: 700;">
                        <input type="checkbox" name="payroll_enable_overtime" value="1" <?php echo $enableOvertime ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--accent-primary);">
                        Enable Overtime Calculation
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.55rem; color: var(--text-secondary); font-weight: 700;">
                        <input type="checkbox" name="payroll_enable_epf" value="1" <?php echo $enableEpf ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--accent-primary);">
                        Enable EPF Calculation
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.6rem; color: var(--text-secondary); font-weight: 700;">
                        <input type="checkbox" name="payroll_enable_etf" value="1" <?php echo $enableEtf ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--accent-primary);">
                        Enable ETF Calculation
                    </label>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Employee EPF %</label>
                    <input type="number" step="0.01" min="0" max="100" name="epf_employee_rate" value="<?php echo htmlspecialchars($settings['epf_employee_rate'] ?? '8.00'); ?>" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Employer EPF %</label>
                    <input type="number" step="0.01" min="0" max="100" name="epf_employer_rate" value="<?php echo htmlspecialchars($settings['epf_employer_rate'] ?? '12.00'); ?>" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">ETF %</label>
                    <input type="number" step="0.01" min="0" max="100" name="etf_employer_rate" value="<?php echo htmlspecialchars($settings['etf_employer_rate'] ?? '3.00'); ?>" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                </div>
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

        <div style="background: #fff; padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 1rem;">
            <div style="font-weight: 800; color: var(--text-primary); margin-bottom: 0.55rem;">ADMS Test URLs</div>
            <div style="display: grid; gap: 0.45rem; font-family: monospace; font-size: 0.85rem; color: var(--text-secondary); overflow-wrap: anywhere;">
                <div>Poll: <code>http://<?php echo htmlspecialchars($server_ip); ?>/iclock/getrequest</code></div>
                <div>Poll fallback: <code>http://<?php echo htmlspecialchars($server_ip); ?>/iclock/getrequest.php</code></div>
                <div>Push: <code>http://<?php echo htmlspecialchars($server_ip); ?>/iclock/cdata</code></div>
                <div>Push fallback: <code>http://<?php echo htmlspecialchars($server_ip); ?>/iclock/cdata.php</code></div>
            </div>
        </div>

        <div style="font-size: 0.85rem; color: var(--text-secondary); display: flex; gap: 0.75rem; align-items: flex-start;">
            <i class="fas fa-info-circle" style="color: var(--accent-primary); margin-top: 0.2rem;"></i>
            <span>Use <code>/iclock/getrequest</code> and <code>/iclock/cdata</code> when URL rewriting is enabled. If the host only responds to PHP files, use the fallback <code>.php</code> endpoints in the web server rewrite/proxy rule.</span>
        </div>

        <div style="margin-top: 1.5rem; background: var(--bg-main); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 0.8rem;">
                <div>
                    <strong style="color: var(--text-primary);">Currently Connected Fingerprint Devices</strong>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">Online means the device contacted ADMS within the last 10 minutes.</div>
                </div>
                <span class="status-badge"><?php echo intval($connectedDeviceCount); ?> online</span>
            </div>
            <?php if ($deviceRows): ?>
                <?php foreach ($deviceRows as $device): ?>
                    <div style="display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 0.75rem; padding: 0.65rem 0; border-top: 1px solid var(--border-color);">
                        <div>
                            <div style="font-weight: 800; color: var(--text-primary);"><?php echo htmlspecialchars($device['terminal_sn'] ?: 'UNKNOWN_DEVICE'); ?></div>
                            <div style="font-size: 0.78rem; color: var(--text-muted);">Last seen: <?php echo htmlspecialchars($device['last_seen']); ?> from <?php echo htmlspecialchars($device['ip_address']); ?></div>
                            <div style="font-size: 0.74rem; color: var(--text-muted);">Last punch: <?php echo $device['last_punch'] ? htmlspecialchars($device['last_punch']) : 'Never'; ?></div>
                        </div>
                        <div style="text-align: right; font-size: 0.78rem; color: var(--text-secondary);">
                            <div style="font-weight: 800; color: var(--accent-success);">Online</div>
                            <div><?php echo htmlspecialchars(strtoupper($device['endpoint'])); ?></div>
                            <div><?php echo intval($device['punch_count']); ?> punch(es)</div>
                            <div><?php echo intval($device['pending_sync']); ?> pending</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="color: var(--text-muted); font-size: 0.85rem; border-top: 1px solid var(--border-color); padding-top: 0.8rem;">No fingerprint device is currently polling ADMS. Check the device IP/server address, port 80, Wi-Fi network, and <code>/iclock/getrequest</code> access.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
