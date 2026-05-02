<?php $ADMIN_TITLE = 'Classroom Monitor';
require_once __DIR__ . '/../includes/helpers.php'; $me = require_login('admin');
ensure_softdelete_and_permissions();

$eid = (int)($_GET['exam_id'] ?? 0);
$exam = db()->prepare('SELECT e.*, creator.name AS creator_name, creator.email AS creator_email FROM exams e LEFT JOIN users creator ON creator.id=e.created_by WHERE e.id=? AND e.deleted_at IS NULL');
$exam->execute([$eid]);
$ex = $exam->fetch();
if (!$ex) { flash('Exam not found','error'); redirect(url('admin/live-monitor.php')); }
if (!$me['is_super'] && (int)$ex['created_by'] !== (int)$me['id']) {
  flash('You can monitor only exams you created.','error'); redirect(url('admin/live-monitor.php'));
}
require __DIR__ . '/_shell_top.php';
$st = exam_status($ex);
?>
<style>
.monitor-hero { background:linear-gradient(135deg,#0E2A47 0%,#081a2e 100%); color:#fff; border-left:4px solid var(--saffron); border-radius:3px; padding:18px 22px; }
.monitor-hero h4 { letter-spacing:.02em; }
.stat-chip { background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); border-radius:3px; padding:10px 16px; min-width:110px; text-align:center; }
.stat-chip .num { font-size:26px; font-weight:800; line-height:1; }
.stat-chip .lbl { font-size:10px; letter-spacing:.1em; text-transform:uppercase; opacity:.75; margin-top:4px; }
.classroom { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:14px; }
.stu-card { background:#fff; border:1px solid #e2e8f0; border-radius:4px; padding:12px; position:relative; transition:all .2s; border-top:3px solid #94a3b8; }
.stu-card.writing { border-top-color:#f59e0b; }
.stu-card.submitted { border-top-color:#16a34a; }
.stu-card.absent { border-top-color:#dc2626; opacity:.7; }
.stu-card.violated { animation:shake .5s; box-shadow:0 0 0 3px rgba(220,38,38,.3); }
@keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-3px)} 75%{transform:translateX(3px)} }
.stu-photo { width:72px; height:92px; object-fit:cover; border:1px solid #cbd5e1; border-radius:3px; display:block; margin:0 auto 8px; }
.stu-no-photo { width:72px; height:92px; border:1px dashed #cbd5e1; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:11px; margin:0 auto 8px; border-radius:3px; }
.stu-meta { font-size:11px; line-height:1.3; }
.stu-meta .name { font-weight:700; color:#0f172a; font-size:13px; margin-bottom:2px; text-align:center; }
.stu-meta .roll { font-family:monospace; color:#475569; text-align:center; }
.stu-meta .dob { color:#64748b; text-align:center; font-size:10px; }
.stu-badge { position:absolute; top:6px; right:6px; font-size:10px; font-weight:700; padding:2px 6px; border-radius:3px; text-transform:uppercase; letter-spacing:.05em; }
.stu-badge.writing { background:#fef3c7; color:#92400e; }
.stu-badge.submitted { background:#d1fae5; color:#065f46; }
.stu-badge.absent { background:#fee2e2; color:#991b1b; }
.viol-indicator { position:absolute; top:6px; left:6px; font-size:10px; font-weight:700; padding:2px 6px; border-radius:3px; background:#dc2626; color:#fff; }
.timer-pill { background:#0f172a; color:#fff; font-family:monospace; font-weight:700; padding:6px 14px; border-radius:3px; letter-spacing:.05em; }
.timer-pill.warn { background:#f59e0b; }
.timer-pill.danger { background:#dc2626; animation:pulse 1s infinite; }
@keyframes pulse { 0%,100%{opacity:1}50%{opacity:.7} }

/* Notification bell */
.notif-bell { position:relative; background:#fff; border:1px solid #cbd5e1; border-radius:50%; width:44px; height:44px; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; transition:all .15s; }
.notif-bell:hover { background:#f1f5f9; }
.notif-bell.ring { animation:ring .6s ease-in-out 3; }
@keyframes ring { 0%,100%{transform:rotate(0)} 25%{transform:rotate(-15deg)} 75%{transform:rotate(15deg)} }
.notif-bell .count { position:absolute; top:-4px; right:-4px; background:#dc2626; color:#fff; border-radius:50%; min-width:20px; height:20px; font-size:11px; font-weight:700; display:flex; align-items:center; justify-content:center; padding:0 5px; }
.notif-panel { position:fixed; top:70px; right:20px; width:420px; max-height:70vh; overflow-y:auto; background:#fff; border:1px solid #e2e8f0; border-radius:4px; box-shadow:0 10px 30px rgba(15,23,42,.2); z-index:3000; display:none; }
.notif-panel.open { display:block; }
.notif-panel header { padding:12px 16px; border-bottom:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:space-between; align-items:center; }
.notif-panel .item { padding:10px 14px; border-bottom:1px solid #f1f5f9; display:flex; gap:10px; }
.notif-panel .item:last-child { border-bottom:0; }
.notif-panel .item img { width:40px; height:48px; object-fit:cover; border:1px solid #cbd5e1; border-radius:3px; flex-shrink:0; }
.notif-panel .item .no-pic { width:40px; height:48px; background:#e2e8f0; border-radius:3px; flex-shrink:0; display:flex; align-items:center; justify-content:center; color:#94a3b8; }
.notif-empty { text-align:center; padding:30px; color:#64748b; }

/* Center alert overlay */
.viol-alert-wrap { position:fixed; inset:0; background:rgba(15,23,42,.5); display:none; align-items:center; justify-content:center; z-index:4000; backdrop-filter:blur(3px); }
.viol-alert-wrap.open { display:flex; animation:fadein .2s; }
@keyframes fadein { from{opacity:0} to{opacity:1} }
.viol-alert { background:#fff; border:3px solid #dc2626; border-radius:6px; width:min(520px,92%); overflow:hidden; box-shadow:0 20px 60px rgba(220,38,38,.45); }
.viol-alert header { background:#dc2626; color:#fff; padding:14px 18px; display:flex; align-items:center; gap:10px; }
.viol-alert header h5 { margin:0; font-weight:800; letter-spacing:.02em; }
.viol-alert .body { display:flex; gap:16px; padding:18px; }
.viol-alert .body img { width:90px; height:110px; object-fit:cover; border:2px solid #0f172a; border-radius:3px; }
.viol-alert .body .no-pic { width:90px; height:110px; background:#e2e8f0; display:flex; align-items:center; justify-content:center; border-radius:3px; color:#94a3b8; font-size:11px; }
.viol-alert .name { font-size:20px; font-weight:800; color:#0f172a; }
.viol-alert .roll { font-family:monospace; color:#475569; font-weight:700; }
.viol-alert .dob { color:#64748b; font-size:12px; }
.viol-alert .what { background:#fee2e2; border-left:4px solid #dc2626; padding:10px 14px; margin-top:10px; color:#991b1b; font-weight:600; font-size:14px; }
.viol-alert footer { padding:10px 18px; display:flex; gap:8px; justify-content:flex-end; background:#f8fafc; border-top:1px solid #e2e8f0; }
</style>

<div class="monitor-hero d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
  <div>
    <div class="small" style="opacity:.7; letter-spacing:.15em; text-transform:uppercase"><i class="fas fa-satellite-dish me-1"></i>Live Classroom Monitor</div>
    <h4 class="fw-bold mb-1 mt-1"><?= h($ex['exam_name']) ?></h4>
    <div class="small" style="opacity:.8">
      <span><i class="fas fa-user-shield me-1"></i>Hosted by <b><?= h($ex['creator_name'] ?? '—') ?></b>
        <?= !empty($ex['creator_email'])? '<span style="opacity:.7">('.h($ex['creator_email']).')</span>':'' ?></span>
      <span class="ms-3"><i class="far fa-clock me-1"></i><?= fmt_dt($ex['start_time']) ?> → <?= fmt_dt($ex['end_time']) ?></span>
    </div>
  </div>
  <div class="d-flex gap-2 align-items-center flex-wrap">
    <div class="stat-chip"><div class="num" id="k-reg">0</div><div class="lbl">Registered</div></div>
    <div class="stat-chip"><div class="num text-warning" id="k-live">0</div><div class="lbl">Writing</div></div>
    <div class="stat-chip"><div class="num text-success" id="k-sub">0</div><div class="lbl">Submitted</div></div>
    <div class="stat-chip"><div class="num text-danger" id="k-abs">0</div><div class="lbl">Absent</div></div>
    <div class="stat-chip" style="background:rgba(220,38,38,0.2)"><div class="num" id="k-viol">0</div><div class="lbl">Violations</div></div>
    <div class="text-center">
      <div class="timer-pill" id="timer">--:--:--</div>
      <div style="font-size:10px; opacity:.7; margin-top:3px" id="timer-lbl">Status</div>
    </div>
    <button class="notif-bell" onclick="toggleNotifPanel()" title="Alerts">
      <i class="fas fa-bell text-warning"></i>
      <span class="count" id="notif-count" style="display:none">0</span>
    </button>
    <div class="dropdown">
      <button class="btn btn-outline-light btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-file-export me-1"></i>Export</button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="<?= url('admin/export-classroom.php?exam_id='.$eid) ?>"><i class="fas fa-file-csv me-1 text-success"></i>Classroom Roster (CSV)</a></li>
        <li><a class="dropdown-item" target="_blank" href="<?= url('admin/export-classroom-pdf.php?exam_id='.$eid) ?>"><i class="fas fa-file-pdf me-1 text-danger"></i>Attendance Sheet (Print / PDF)</a></li>
      </ul>
    </div>
  </div>
</div>

<div class="exam-card">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h6 class="fw-bold mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>Classroom Roster</h6>
      <small class="text-muted">Auto-refreshes every 5 seconds · <span id="last-sync">—</span></small>
    </div>
    <div class="d-flex gap-2">
      <input type="text" id="roster-search" class="form-control form-control-sm" placeholder="Filter by name / roll…" style="width:220px">
      <select id="roster-filter" class="form-select form-select-sm" style="width:150px">
        <option value="all">All</option>
        <option value="writing">Writing</option>
        <option value="submitted">Submitted</option>
        <option value="absent">Absent</option>
      </select>
    </div>
  </div>
  <div class="classroom" id="classroom"><div class="text-muted text-center py-4 w-100">Loading students…</div></div>
</div>

<!-- Notification Panel -->
<div class="notif-panel" id="notif-panel">
  <header>
    <div><i class="fas fa-bell me-1"></i> <b>Violation Alerts</b></div>
    <div class="d-flex gap-2 align-items-center">
      <button class="btn btn-sm btn-outline-secondary" onclick="clearNotifs()">Clear</button>
      <button class="btn btn-sm btn-outline-secondary" onclick="toggleNotifPanel()">Close</button>
    </div>
  </header>
  <div id="notif-list"><div class="notif-empty"><i class="fas fa-bell-slash fa-2x mb-2"></i><br>No alerts yet</div></div>
</div>

<!-- Center Violation Alert Overlay -->
<div class="viol-alert-wrap" id="viol-overlay">
  <div class="viol-alert">
    <header><i class="fas fa-triangle-exclamation fa-lg"></i><h5>EXAM VIOLATION DETECTED</h5></header>
    <div class="body">
      <div id="va-pic"></div>
      <div class="flex-grow-1">
        <div class="name" id="va-name">—</div>
        <div class="roll" id="va-roll">—</div>
        <div class="dob" id="va-dob">—</div>
        <div class="what" id="va-what">—</div>
        <div class="small text-muted mt-2" id="va-time">—</div>
      </div>
    </div>
    <footer>
      <button class="btn btn-outline-secondary" onclick="closeViolAlert()">Dismiss</button>
    </footer>
  </div>
</div>

<script>
const EXAM_ID = <?= (int)$eid ?>;
const EXAM_START = <?= strtotime($ex['start_time']) * 1000 ?>;
const EXAM_END = <?= strtotime($ex['end_time']) * 1000 ?>;
const FEED_URL = <?= json_encode(url('api/monitor-feed.php')) ?>;
const ALERTS_URL = <?= json_encode(url('api/monitor-alerts.php')) ?>;
let lastAlertTs = 0;
const NOTIFS = [];
const notifPanel = document.getElementById('notif-panel');
const notifList = document.getElementById('notif-list');
const notifCount = document.getElementById('notif-count');
const bell = document.querySelector('.notif-bell');

function fmtClock(ms) {
  const s = Math.max(0, Math.floor(ms/1000));
  const h = String(Math.floor(s/3600)).padStart(2,'0');
  const m = String(Math.floor(s%3600/60)).padStart(2,'0');
  const sec = String(s%60).padStart(2,'0');
  return `${h}:${m}:${sec}`;
}
function updateTimer() {
  const now = Date.now();
  const t = document.getElementById('timer');
  const l = document.getElementById('timer-lbl');
  if (now < EXAM_START) {
    t.textContent = fmtClock(EXAM_START - now);
    t.className = 'timer-pill';
    l.textContent = 'Starts in';
  } else if (now < EXAM_END) {
    const remain = EXAM_END - now;
    t.textContent = fmtClock(remain);
    t.className = 'timer-pill' + (remain < 5*60*1000 ? ' danger' : (remain < 15*60*1000 ? ' warn' : ''));
    l.textContent = 'Time remaining';
  } else {
    t.textContent = '00:00:00';
    t.className = 'timer-pill';
    l.textContent = 'Exam ended';
  }
}
setInterval(updateTimer, 1000); updateTimer();

async function fetchFeed() {
  try {
    const r = await fetch(FEED_URL + '?exam_id=' + EXAM_ID, {credentials:'same-origin'});
    const data = await r.json();
    if (!data.ok) return;
    document.getElementById('k-reg').textContent = data.registered;
    document.getElementById('k-live').textContent = data.writing;
    document.getElementById('k-sub').textContent = data.submitted;
    document.getElementById('k-abs').textContent = data.absent;
    document.getElementById('k-viol').textContent = data.total_violations;
    renderRoster(data.students);
    document.getElementById('last-sync').textContent = new Date().toLocaleTimeString();
  } catch(e) { console.warn('feed error', e); }
}

function renderRoster(students) {
  const search = (document.getElementById('roster-search').value || '').trim().toLowerCase();
  const filter = document.getElementById('roster-filter').value;
  const wrap = document.getElementById('classroom');
  const filtered = students.filter(s => {
    if (filter !== 'all' && s.status !== filter) return false;
    if (search && !(s.name.toLowerCase().includes(search) || (s.roll||'').toLowerCase().includes(search))) return false;
    return true;
  });
  if (!filtered.length) { wrap.innerHTML = '<div class="text-muted text-center py-4 w-100">No students match.</div>'; return; }
  wrap.innerHTML = filtered.map(s => {
    const pic = s.photo_url
      ? `<img class="stu-photo" src="${s.photo_url}" alt="">`
      : `<div class="stu-no-photo">No Photo</div>`;
    const viol = (s.violations||0) > 0
      ? `<span class="viol-indicator"><i class="fas fa-triangle-exclamation me-1"></i>${s.violations}</span>` : '';
    const badge = `<span class="stu-badge ${s.status}">${s.status_label}</span>`;
    return `<div class="stu-card ${s.status}" data-sid="${s.id}">
      ${badge}${viol}
      ${pic}
      <div class="stu-meta">
        <div class="name">${escapeHtml(s.name)}</div>
        <div class="roll">${escapeHtml(s.roll||'—')}</div>
        <div class="dob">DOB: ${escapeHtml(s.dob||'—')}</div>
      </div>
    </div>`;
  }).join('');
}
function escapeHtml(s){return String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

async function fetchAlerts() {
  try {
    const r = await fetch(ALERTS_URL + '?exam_id=' + EXAM_ID + '&since=' + lastAlertTs, {credentials:'same-origin'});
    const data = await r.json();
    if (!data.ok || !data.alerts || !data.alerts.length) return;
    data.alerts.forEach(a => {
      NOTIFS.unshift(a);
      if (a.ts > lastAlertTs) lastAlertTs = a.ts;
      showViolAlert(a);
      flashStudent(a.user_id);
    });
    notifCount.style.display = '';
    notifCount.textContent = NOTIFS.length > 99 ? '99+' : NOTIFS.length;
    bell.classList.add('ring');
    setTimeout(() => bell.classList.remove('ring'), 2000);
    renderNotifList();
  } catch(e) { console.warn('alerts error', e); }
}

function renderNotifList() {
  if (!NOTIFS.length) { notifList.innerHTML = '<div class="notif-empty"><i class="fas fa-bell-slash fa-2x mb-2"></i><br>No alerts yet</div>'; return; }
  notifList.innerHTML = NOTIFS.slice(0,50).map(a => {
    const pic = a.photo_url ? `<img src="${a.photo_url}" alt="">` : `<div class="no-pic"><i class="fas fa-user"></i></div>`;
    return `<div class="item">
      ${pic}
      <div style="flex:1; font-size:13px">
        <div><b>${escapeHtml(a.name)}</b> <small class="text-muted">(${escapeHtml(a.roll||'—')})</small></div>
        <div class="text-danger" style="font-size:12px; font-weight:600">${escapeHtml(a.event_type)}</div>
        <div class="small text-muted">${escapeHtml(a.description || '')}</div>
        <div class="small text-muted" style="font-size:10px">${new Date(a.ts).toLocaleString()}</div>
      </div>
    </div>`;
  }).join('');
}

function flashStudent(uid) {
  const card = document.querySelector(`.stu-card[data-sid="${uid}"]`);
  if (card) { card.classList.add('violated'); setTimeout(()=>card.classList.remove('violated'), 3000); }
}

function showViolAlert(a) {
  document.getElementById('va-name').textContent = a.name || '—';
  document.getElementById('va-roll').textContent = 'Roll: ' + (a.roll || '—');
  document.getElementById('va-dob').textContent = 'DOB: ' + (a.dob || '—');
  document.getElementById('va-what').textContent = (a.event_type || 'Unknown') + (a.description ? ' — ' + a.description : '');
  document.getElementById('va-time').textContent = new Date(a.ts).toLocaleString();
  document.getElementById('va-pic').innerHTML = a.photo_url ? `<img src="${a.photo_url}" alt="">` : `<div class="no-pic">No Photo</div>`;
  document.getElementById('viol-overlay').classList.add('open');
  try { beep(); } catch(e){}
  // Auto-close after 8 seconds if user doesn't dismiss
  clearTimeout(window.__va_timer);
  window.__va_timer = setTimeout(closeViolAlert, 8000);
}
function closeViolAlert() { document.getElementById('viol-overlay').classList.remove('open'); }
function toggleNotifPanel() { notifPanel.classList.toggle('open'); if (notifPanel.classList.contains('open')) renderNotifList(); }
function clearNotifs() { NOTIFS.length = 0; notifCount.style.display='none'; notifCount.textContent='0'; renderNotifList(); }

function beep() {
  try {
    const C = window.AudioContext || window.webkitAudioContext;
    const ctx = new C();
    const o = ctx.createOscillator(); const g = ctx.createGain();
    o.type='square'; o.frequency.value=1040;
    o.connect(g); g.connect(ctx.destination);
    g.gain.setValueAtTime(0.0001, ctx.currentTime);
    o.start();
    g.gain.exponentialRampToValueAtTime(0.2, ctx.currentTime + 0.01);
    g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.4);
    o.stop(ctx.currentTime + 0.45);
  } catch(e){}
}

document.getElementById('roster-search').addEventListener('input', fetchFeed);
document.getElementById('roster-filter').addEventListener('change', fetchFeed);
document.getElementById('viol-overlay').addEventListener('click', (e)=>{ if(e.target.id==='viol-overlay') closeViolAlert(); });

fetchFeed();
fetchAlerts();
setInterval(fetchFeed, 5000);
setInterval(fetchAlerts, 5000);
</script>
<?php require __DIR__ . '/_shell_bottom.php'; ?>
