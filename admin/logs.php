<?php $ADMIN_TITLE = 'Activity Logs';
require_once __DIR__ . '/../includes/helpers.php'; $me = require_login('admin');
if (empty($me['is_super'])) {
  http_response_code(403);
  die('Only super admin can access activity logs.');
}
ensure_admin_activity_logs_table();
$q = trim($_GET['q'] ?? '');
$where = [];
$params = [];
if ($q !== '') {
  $where[] = '(admin_name LIKE ? OR admin_email LIKE ? OR action LIKE ? OR details LIKE ? OR page LIKE ?)';
  $like = '%' . $q . '%';
  $params = [$like, $like, $like, $like, $like];
}
$sql = 'SELECT * FROM admin_activity_logs';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY created_at DESC, id DESC LIMIT 250';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
require __DIR__ . '/_shell_top.php';

// Helper to format payload details for display
function format_payload_details($payload, $action) {
  if (!$payload || !is_array($payload)) return '';
  $before = $payload['before'] ?? null;
  $after = $payload['after'] ?? null;
  $table = $payload['table'] ?? 'unknown';
  $lines = [];

  // Simple formatter for values
  $val = function($v) {
    if (is_array($v)) return json_encode($v);
    return (string)$v;
  };

  if (strpos($action, '_add') !== false && is_array($after)) {
    foreach ($after as $k => $v) {
      if ($k === 'id') continue;
      $lines[] = "Added " . h($k) . ": " . h($val($v));
    }
    if (!$lines) $lines[] = 'Added (no visible fields)';
    return "<div class='mt-2 small activity-log-added'><strong><i class='fas fa-plus-circle me-1'></i>Added to " . h($table) . ":</strong><div class='mt-1'>" . h(implode('; ', $lines)) . "</div></div>";
  }

  if (strpos($action, '_delete') !== false && is_array($before)) {
    foreach ($before as $k => $v) {
      if ($k === 'id') continue;
      $lines[] = "Deleted " . h($k) . ": " . h($val($v));
    }
    if (!$lines) $lines[] = 'Deleted (no visible fields)';
    return "<div class='mt-2 small activity-log-deleted'><strong><i class='fas fa-trash-alt me-1'></i>Deleted from " . h($table) . ":</strong><div class='mt-1'>" . h(implode('; ', $lines)) . "</div></div>";
  }

  if (strpos($action, '_update') !== false && is_array($before) && is_array($after)) {
    foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $k) {
      if ($k === 'id') continue;
      $old = array_key_exists($k, $before) ? $val($before[$k]) : '(not set)';
      $new = array_key_exists($k, $after) ? $val($after[$k]) : '(not set)';
      if ((string)$old !== (string)$new) {
        $lines[] = h($k) . ": " . h($old) . " → " . h($new);
      }
    }
    if (!$lines) $lines[] = 'Updated (no visible changes)';
    return "<div class='mt-2 small activity-log-changed'><strong><i class='fas fa-edit me-1'></i>Changed in " . h($table) . ":</strong><div class='mt-1'>" . h(implode('; ', $lines)) . "</div></div>";
  }

  // Fallbacks for options/count/ids
  if (!empty($payload['options'])) return "<div class='mt-2 small'><strong>Options:</strong> " . h(json_encode($payload['options'])) . "</div>";
  if (!empty($payload['count'])) return "<div class='mt-2 small'><strong>Count:</strong> " . (int)$payload['count'] . "</div>";
  if (!empty($payload['ids_deleted'])) return "<div class='mt-2 small'><strong>Deleted IDs:</strong> " . h(implode(', ', array_map('intval', $payload['ids_deleted']))) . "</div>";

  return '';
}

// Handle log clearing (super admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear') {
  csrf_check();
  $from = trim($_POST['from'] ?? '');
  $to = trim($_POST['to'] ?? '');
  if (!$from || !$to) {
    flash('Both From and To datetimes are required to clear logs', 'error');
    redirect(url('admin/logs.php'));
  }
  // Convert datetime-local (YYYY-MM-DDTHH:MM) to MySQL DATETIME
  $from_ts = str_replace('T', ' ', $from) . ':00';
  $to_ts = str_replace('T', ' ', $to) . ':59';
  try {
    $del = db()->prepare('DELETE FROM admin_activity_logs WHERE created_at BETWEEN ? AND ?');
    $del->execute([$from_ts, $to_ts]);
    $count = $del->rowCount();
    // Record this clearing action (will be outside the deleted range)
    log_admin_activity('logs_cleared', 'Cleared ' . $count . ' log(s) from ' . $from_ts . ' to ' . $to_ts, $me, 'admin/logs.php');
    flash('Cleared ' . $count . ' log(s) between specified datetimes', 'success');
  } catch (Throwable $e) {
    flash('Failed to clear logs: ' . $e->getMessage(), 'error');
  }
  redirect(url('admin/logs.php'));
}

// Handle undo requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'undo') {
  csrf_check();
  $lid = (int)($_POST['id'] ?? 0);
  if (!$lid) {
    flash('Invalid log id', 'error'); redirect(url('admin/logs.php'));
  }
  try {
    $s = db()->prepare('SELECT * FROM admin_activity_logs WHERE id = ? LIMIT 1');
    $s->execute([$lid]);
    $log = $s->fetch();
    if (!$log) { flash('Log not found', 'error'); redirect(url('admin/logs.php')); }
    if (empty($log['payload'])) { flash('No payload available to undo', 'error'); redirect(url('admin/logs.php')); }
    // Prevent duplicate undo for the same original log
    $chk = db()->prepare('SELECT COUNT(*) FROM admin_activity_logs WHERE action = ? AND payload LIKE ?');
    $chk->execute(['undo', '%"undo_of":' . $lid . '%']);
    if ((int)$chk->fetchColumn() > 0) { flash('This action has already been undone', 'error'); redirect(url('admin/logs.php')); }
    $payload = json_decode($log['payload'], true);
    if (!$payload || empty($payload['table'])) { flash('Malformed undo payload', 'error'); redirect(url('admin/logs.php')); }
    $table = $payload['table'];
    $before = $payload['before'] ?? null;
    $after = $payload['after'] ?? null;
    // Whitelist tables we allow undo on
    $allowed = ['users','exams','questions','question_options'];
    if (!in_array($table, $allowed, true)) { flash('Undo not supported for table: ' . h($table), 'error'); redirect(url('admin/logs.php')); }

    // Helper: build and execute insert from assoc array
    $insertRow = function(array $row) use ($table) {
      $cols = array_keys($row);
      $place = implode(',', array_fill(0, count($cols), '?'));
      $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . $place . ')';
      $stmt = db()->prepare($sql);
      $stmt->execute(array_values($row));
      return db()->lastInsertId();
    };

    // Helper: update by id (expects 'id' present)
    $updateRow = function(array $row) use ($table) {
      if (empty($row['id'])) throw new Exception('Missing id for update');
      $id = $row['id'];
      $cols = $row; unset($cols['id']);
      if (!$cols) throw new Exception('No columns to update');
      $sets = [];
      foreach ($cols as $k => $v) $sets[] = $k . ' = ?';
      $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE id = ?';
      $stmt = db()->prepare($sql);
      $params = array_values($cols); $params[] = $id;
      $stmt->execute($params);
      return $stmt->rowCount();
    };

    // Helper: delete by id
    $deleteById = function($id) use ($table) {
      $stmt = db()->prepare('DELETE FROM ' . $table . ' WHERE id = ?');
      $stmt->execute([$id]);
      return $stmt->rowCount();
    };

    $affected = 0;
    if (strpos($log['action'], '_add') !== false) {
      // undo add -> delete the 'after' id
      if (empty($after['id'])) throw new Exception('No id in payload.after');
      $affected = $deleteById($after['id']);
    } elseif (strpos($log['action'], '_delete') !== false) {
      // undo delete -> re-insert 'before' (may include id)
      if (empty($before) || !is_array($before)) throw new Exception('No before payload');
      // If related rows (e.g., question options) present, restore in order
      if ($table === 'questions' && !empty($before)) {
        // Insert question row (including id if present)
        $qid = $insertRow($before);
        $affected++;
      } else {
        $insertRow($before);
        $affected++;
      }
      // Restore related options if present
      if (!empty($payload['options']) && is_array($payload['options'])) {
        foreach ($payload['options'] as $opt) {
          // ensure question_id references restored id if needed
          if (isset($opt['question_id']) && isset($before['id'])) $opt['question_id'] = $before['id'];
          db()->prepare('INSERT INTO question_options (' . implode(',', array_keys($opt)) . ') VALUES (' . implode(',', array_fill(0, count($opt), '?')) . ')')->execute(array_values($opt));
          $affected++;
        }
      }
    } elseif (strpos($log['action'], '_update') !== false) {
      // undo update -> set to 'before'
      if (empty($before) || !is_array($before)) throw new Exception('No before payload');
      $affected = $updateRow($before);
    } else {
      throw new Exception('Unsupported undo action: ' . $log['action']);
    }

    log_admin_activity('undo', 'Undid log #' . $lid . ' action ' . $log['action'] . ' (restored ' . $affected . ' rows)', $me, 'admin/logs.php', ['undo_of' => $lid]);
    flash('Undo completed: ' . $affected . ' rows affected', 'success');
  } catch (Throwable $e) {
    flash('Undo failed: ' . $e->getMessage(), 'error');
  }
  redirect(url('admin/logs.php'));
}

// Handle redo requests (reverse an undo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'redo') {
  csrf_check();
  $undo_lid = (int)($_POST['id'] ?? 0);
  if (!$undo_lid) {
    flash('Invalid log id', 'error'); redirect(url('admin/logs.php'));
  }
  try {
    // Get the undo log entry
    $s = db()->prepare('SELECT * FROM admin_activity_logs WHERE id = ? LIMIT 1');
    $s->execute([$undo_lid]);
    $undo_log = $s->fetch();
    if (!$undo_log) { flash('Undo log not found', 'error'); redirect(url('admin/logs.php')); }
    if (empty($undo_log['payload'])) { flash('No undo payload', 'error'); redirect(url('admin/logs.php')); }
    // Prevent duplicate redo for the same undo entry
    $chk2 = db()->prepare('SELECT COUNT(*) FROM admin_activity_logs WHERE action = ? AND payload LIKE ?');
    $chk2->execute(['redo', '%"redo_of":' . $undo_lid . '%']);
    if ((int)$chk2->fetchColumn() > 0) { flash('This undo has already been redone', 'error'); redirect(url('admin/logs.php')); }
    $undo_payload = json_decode($undo_log['payload'], true);
    if (!$undo_payload || empty($undo_payload['undo_of'])) { flash('Not a valid undo entry', 'error'); redirect(url('admin/logs.php')); }
    
    // Get the original log that was undone
    $orig_lid = (int)$undo_payload['undo_of'];
    $orig = db()->prepare('SELECT * FROM admin_activity_logs WHERE id = ? LIMIT 1');
    $orig->execute([$orig_lid]);
    $orig_log = $orig->fetch();
    if (!$orig_log) { flash('Original log not found', 'error'); redirect(url('admin/logs.php')); }
    if (empty($orig_log['payload'])) { flash('No payload on original log', 'error'); redirect(url('admin/logs.php')); }
    $orig_payload = json_decode($orig_log['payload'], true);
    if (!$orig_payload || empty($orig_payload['table'])) { flash('Malformed original payload', 'error'); redirect(url('admin/logs.php')); }
    
    $table = $orig_payload['table'];
    $before = $orig_payload['before'] ?? null;
    $after = $orig_payload['after'] ?? null;
    $allowed = ['users','exams','questions','question_options'];
    if (!in_array($table, $allowed, true)) { flash('Redo not supported for table: ' . h($table), 'error'); redirect(url('admin/logs.php')); }

    // Helpers (same as undo)
    $insertRow = function(array $row) use ($table) {
      $cols = array_keys($row);
      $place = implode(',', array_fill(0, count($cols), '?'));
      $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . $place . ')';
      $stmt = db()->prepare($sql);
      $stmt->execute(array_values($row));
      return db()->lastInsertId();
    };
    $updateRow = function(array $row) use ($table) {
      if (empty($row['id'])) throw new Exception('Missing id for update');
      $id = $row['id'];
      $cols = $row; unset($cols['id']);
      if (!$cols) throw new Exception('No columns to update');
      $sets = [];
      foreach ($cols as $k => $v) $sets[] = $k . ' = ?';
      $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE id = ?';
      $stmt = db()->prepare($sql);
      $params = array_values($cols); $params[] = $id;
      $stmt->execute($params);
      return $stmt->rowCount();
    };
    $deleteById = function($id) use ($table) {
      $stmt = db()->prepare('DELETE FROM ' . $table . ' WHERE id = ?');
      $stmt->execute([$id]);
      return $stmt->rowCount();
    };

    // Re-apply the original change (redo)
    $affected = 0;
    if (strpos($orig_log['action'], '_add') !== false) {
      // redo add -> re-insert the 'after' row
      if (empty($after) || !is_array($after)) throw new Exception('No after payload');
      $insertRow($after);
      $affected++;
    } elseif (strpos($orig_log['action'], '_delete') !== false) {
      // redo delete -> delete the 'before' id again
      if (empty($before['id'])) throw new Exception('No id in payload.before');
      $affected = $deleteById($before['id']);
    } elseif (strpos($orig_log['action'], '_update') !== false) {
      // redo update -> set to 'after' state
      if (empty($after) || !is_array($after)) throw new Exception('No after payload');
      $affected = $updateRow($after);
    } else {
      throw new Exception('Unsupported redo action: ' . $orig_log['action']);
    }

    log_admin_activity('redo', 'Redid undo of log #' . $orig_lid . ' action ' . $orig_log['action'] . ' (restored ' . $affected . ' rows)', $me, 'admin/logs.php', ['redo_of' => $undo_lid, 'original_of' => $orig_lid]);
    flash('Redo completed: ' . $affected . ' rows affected', 'success');
  } catch (Throwable $e) {
    flash('Redo failed: ' . $e->getMessage(), 'error');
  }
  redirect(url('admin/logs.php'));
}

?>
<style>
.x-small { font-size: 0.80rem; }
.activity-log-added { color: #2e7d32; }
.activity-log-deleted { color: #c62828; }
.activity-log-changed { color: #1565c0; }
</style>
<div class="d-flex justify-content-between mb-3 gap-2 flex-wrap">
  <form class="d-flex gap-2 flex-wrap">
    <input name="q" value="<?= h($q) ?>" class="form-control" style="width:320px" placeholder="Search admin, action, details">
    <button class="btn btn-outline-secondary">Filter</button>
  </form>
  <form method="post" class="d-flex gap-2">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="clear">
    <label class="small mb-0">From</label>
    <input type="datetime-local" name="from" class="form-control form-control-sm" required>
    <label class="small mb-0">To</label>
    <input type="datetime-local" name="to" class="form-control form-control-sm" required>
    <button class="btn btn-outline-danger" onclick="return confirm('Clear logs in the given range? This cannot be undone.')">Clear</button>
  </form>
</div>
<div class="bg-white border">
  <table class="data-table"><thead><tr><th>Date &amp; Time</th><th>Admin</th><th>Action</th><th>Details</th><th>IP</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="small text-muted"><span class="badge bg-light text-dark"><?= fmt_dt($r['created_at']) ?></span></td>
      <td><div class="fw-medium small"><?= h($r['admin_name']) ?></div><div class="x-small text-muted"><?= h($r['admin_email']) ?></div></td>
      <td><span class="badge bg-info"><?= h($r['action']) ?></span></td>
      <td class="small" style="max-width:600px">
        <div><?= h($r['details'] ?? '—') ?></div>
        <?php if (!empty($r['payload'])): ?>
          <?php $payload = json_decode($r['payload'], true); ?>
          <?= format_payload_details($payload, $r['action']) ?>
          <div class="mt-2"><a href="#" class="text-muted small" onclick="this.parentElement.nextElementSibling.style.display=(this.parentElement.nextElementSibling.style.display==='none'?'block':'none');return false;"><i class="fas fa-code me-1"></i>Raw JSON</a></div>
          <pre style="display:none;max-height:150px;overflow:auto;background:#f8f9fa;border:1px solid #ddd;padding:8px;border-radius:3px;font-size:11px;margin-top:4px;"><?= h($r['payload']) ?></pre>
        <?php endif; ?>
      </td>
      <td class="x-small text-muted"><?= h($r['ip_address'] ?? '—') ?></td>
      <td class="small" style="white-space:nowrap">
        <?php if (!empty($r['payload'])):
          $p = json_decode($r['payload'], true);
          // Check if this is an undo entry (has undo_of in payload)
          $is_undo = $p && isset($p['undo_of']);
          // Check if this is a regular mutation that can be undone
          $can_undo = false;
          if ($p && !empty($p['table'])) {
            $allowed = ['users','exams','questions','question_options'];
            if (in_array($p['table'], $allowed, true) && (strpos($r['action'], '_add') !== false || strpos($r['action'], '_delete') !== false || strpos($r['action'], '_update') !== false)) $can_undo = true;
          }
        ?>
          <?php if ($is_undo): ?>
            <!-- This is an undo entry, show Redo button -->
            <form method="post" style="display:inline" onsubmit="(function(f){var b=f.querySelector('button[type=submit]'); if(b){b.disabled=true; b.innerHTML='<i class=\'fas fa-spinner fa-spin me-1\'></i>Processing...';} f.style.display='none'; })(this);">
              <?= csrf_input() ?>
              <input type="hidden" name="action" value="redo">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-success" onclick="if(!confirm('Redo this action (restore the change)?')){return false;} this.disabled=true; this.innerHTML='<i class=\'fas fa-spinner fa-spin me-1\'></i>Processing...'; this.closest('form').style.display='none';"><i class="fas fa-redo me-1"></i>Redo</button>
            </form>
          <?php elseif ($can_undo): ?>
            <!-- This is a regular mutation, show Undo button -->
            <form method="post" style="display:inline" onsubmit="(function(f){var b=f.querySelector('button[type=submit]'); if(b){b.disabled=true; b.innerHTML='<i class=\'fas fa-spinner fa-spin me-1\'></i>Processing...';} f.style.display='none'; })(this);">
              <?= csrf_input() ?>
              <input type="hidden" name="action" value="undo">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-warning" onclick="if(!confirm('Undo this action?')){return false;} this.disabled=true; this.innerHTML='<i class=\'fas fa-spinner fa-spin me-1\'></i>Processing...'; this.closest('form').style.display='none';"><i class="fas fa-undo me-1"></i>Undo</button>
            </form>
          <?php endif; ?>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No activity logs yet</td></tr><?php endif; ?>
  </tbody></table>
</div>
<?php require __DIR__ . '/_shell_bottom.php'; ?>