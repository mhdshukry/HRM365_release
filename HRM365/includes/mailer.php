<?php
/**
 * Utility function to send emails.
 * Since we don't have a live SMTP server configured, this function mocks 
 * the email sending process by appending the email content to a local log file.
 */
function send_email($to, $subject, $body) {
    $log_file = __DIR__ . '/../email_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    
    $entry = "=================================================\n";
    $entry .= "DATE: {$timestamp}\n";
    $entry .= "TO: {$to}\n";
    $entry .= "SUBJECT: {$subject}\n";
    $entry .= "BODY:\n{$body}\n";
    $entry .= "=================================================\n\n";
    
    file_put_contents($log_file, $entry, FILE_APPEND);
    return true;
}
?>
