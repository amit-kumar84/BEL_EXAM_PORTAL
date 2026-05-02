<?php
// ============================================================================
// Lightweight SMTP mailer for BEL Exam Portal (no Composer required)
// Configure SMTP_* constants in includes/config.php
// ============================================================================

function send_mail(string $to, string $subject, string $htmlBody, string $toName = ''): array {
    if (!defined('SMTP_HOST') || !SMTP_HOST) {
        return ['ok' => false, 'error' => 'SMTP not configured (set SMTP_HOST in config.php)'];
    }
    try {
        $sock = @fsockopen((SMTP_SECURE === 'ssl' ? 'ssl://' : '') . SMTP_HOST, SMTP_PORT, $eno, $estr, 15);
        if (!$sock) return ['ok' => false, 'error' => "Connect failed: $estr ($eno)"];
        stream_set_timeout($sock, 15);
        $get = function() use ($sock) { $line = ''; while (!feof($sock)) { $r = fgets($sock, 515); $line .= $r; if (substr($r, 3, 1) === ' ') break; } return $line; };
        $put = function($cmd) use ($sock) { fwrite($sock, $cmd . "\r\n"); };
        $get();
        $put('EHLO ' . (SMTP_FROM_DOMAIN ?: 'localhost')); $get();
        if (SMTP_SECURE === 'tls') {
            $put('STARTTLS'); $get();
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $put('EHLO ' . (SMTP_FROM_DOMAIN ?: 'localhost')); $get();
        }
        if (SMTP_USER) {
            $put('AUTH LOGIN'); $get();
            $put(base64_encode(SMTP_USER)); $get();
            $put(base64_encode(SMTP_PASS)); $r = $get();
            if (substr($r, 0, 3) !== '235') { fclose($sock); return ['ok' => false, 'error' => "Auth failed: $r"]; }
        }
        $put('MAIL FROM: <' . SMTP_FROM_EMAIL . '>'); $get();
        $put('RCPT TO: <' . $to . '>'); $r = $get();
        if (substr($r, 0, 1) !== '2') { fclose($sock); return ['ok' => false, 'error' => "Recipient rejected: $r"]; }
        $put('DATA'); $get();
        $headers  = 'From: "' . (SMTP_FROM_NAME ?: 'BEL Kotdwar') . '" <' . SMTP_FROM_EMAIL . ">\r\n";
        $headers .= 'To: ' . ($toName ? "\"$toName\" <$to>" : $to) . "\r\n";
        $headers .= 'Subject: ' . $subject . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=utf-8\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        fwrite($sock, $headers . "\r\n" . $htmlBody . "\r\n.\r\n");
        $r = $get(); $put('QUIT'); fclose($sock);
        return substr($r, 0, 1) === '2' ? ['ok' => true] : ['ok' => false, 'error' => "Send failed: $r"];
    } catch (Throwable $e) { return ['ok' => false, 'error' => $e->getMessage()]; }
}

function mail_credentials(array $student, string $plainPassword): array {
    $body = '<div style="font-family:Segoe UI,sans-serif;max-width:560px;margin:auto;border:1px solid #e2e8f0">
<div style="background:#0E2A47;color:#fff;padding:14px;border-bottom:2px solid #FF9933"><b>BEL Kotdwar — Examination Portal</b></div>
<div style="padding:18px;line-height:1.6;color:#1e293b">
<p>Dear <b>' . htmlspecialchars($student['name']) . '</b>,</p>
<p>Your candidate account has been created on the BEL Kotdwar Online Examination Portal. Please use the credentials below to login:</p>
<table cellpadding="6" style="border-collapse:collapse;background:#f8fafc;border:1px solid #cbd5e1">
<tr><td><b>Roll / Staff ID</b></td><td><code>' . htmlspecialchars($student['roll_number']) . '</code></td></tr>
<tr><td><b>Date of Birth</b></td><td>' . htmlspecialchars($student['dob']) . '</td></tr>
<tr><td><b>Username</b></td><td><code>' . htmlspecialchars($student['username']) . '</code></td></tr>
<tr><td><b>Password</b></td><td><code>' . htmlspecialchars($plainPassword) . '</code></td></tr>
</table>
<p style="margin-top:14px;font-size:13px;color:#64748b">Login uses Roll/Staff ID + DOB + Password (3-factor). Keep these credentials confidential.</p>
<p style="font-size:13px"><b>Note:</b> Reach the examination centre 30 minutes before exam start. Carry this email and a Government photo ID.</p>
<hr style="border:none;border-top:1px solid #e2e8f0;margin:16px 0">
<small style="color:#94a3b8">© ' . date('Y') . ' Bharat Electronics Limited · Kotdwar Unit · For authorised use only</small>
</div></div>';
    return send_mail($student['email'], 'BEL Kotdwar — Your Examination Portal Credentials', $body, $student['name']);
}

function mail_reset_token(array $user, string $token): array {
    $body = '<div style="font-family:Segoe UI,sans-serif;max-width:560px;margin:auto;border:1px solid #e2e8f0">
<div style="background:#0E2A47;color:#fff;padding:14px;border-bottom:2px solid #FF9933"><b>BEL Kotdwar — Password Reset</b></div>
<div style="padding:18px;line-height:1.6">
<p>Dear <b>' . htmlspecialchars($user['name']) . '</b>,</p>
<p>A password reset has been requested for your account. Use the token below within <b>1 hour</b> to set a new password:</p>
<p style="background:#fff7ed;border:1px solid #fdba74;padding:10px;font-family:monospace;word-break:break-all"><b>' . htmlspecialchars($token) . '</b></p>
<p style="font-size:12px;color:#64748b">If you did not request this, ignore this email. The token expires in 60 minutes and can only be used once.</p>
</div></div>';
    return send_mail($user['email'], 'BEL Kotdwar — Password Reset Token', $body, $user['name']);
}
