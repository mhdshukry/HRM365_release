<?php
/**
 * Utility function to securely log all sensitive administrative and user actions.
 */
function log_action($pdo, $user_id, $action, $details) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $details, $ip]);
        return true;
    } catch (\PDOException $e) {
        // Fail silently so as not to break core application flows
        error_log("Audit Log Failure: " . $e->getMessage());
        return false;
    }
}
?>
