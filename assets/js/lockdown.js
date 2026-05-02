// BEL Kotdwar Exam — Lockdown JS
// Provides: timer, fullscreen, tab-switch detection, copy/paste block, devtools block,
// palette sync, save-answer, mark-for-review, auto-submit
//
// Each check is gated by VIOLATION_CONFIG (set by take-exam.php) so super-admin can
// turn individual protections on/off per exam. Default: all ON (except future-proof hooks).

const _VC = (typeof VIOLATION_CONFIG !== 'undefined') ? VIOLATION_CONFIG : {};
const _FFS = (typeof FORCE_FULLSCREEN !== 'undefined') ? FORCE_FULLSCREEN : true;
function violCheck(name) { return _VC[name] !== 0 && _VC[name] !== false; }

let CURRENT = 0;
const TOTAL = document.querySelectorAll('.q-box').length;
let MARKED = new Set(INITIAL_MARKED.map(String));
const ANSWERED = new Set();
const VISITED = new Set(['0']);
let violations = 0;
let submitted = false;
let fsRetryInterval = null;
let fsRetryEndTime = 0;
const FS_RETRY_MS = 100;
const FS_RETRY_TIMEOUT_MS = 1000;

// init answered from DOM
document.querySelectorAll('.q-box').forEach(box => {
  const qid = box.dataset.qid;
  const checked = box.querySelector('input:checked, input[type=text]:not([value=""]), input[type=number]:not([value=""])');
  if (checked) ANSWERED.add(qid);
});

// ------ Timer ------
function updateTimer() {
  const left = Math.max(0, Math.floor((ENDS_TS - Date.now()) / 1000));
  const m = String(Math.floor(left / 60)).padStart(2, '0');
  const s = String(left % 60).padStart(2, '0');
  const el = document.getElementById('timer');
  el.textContent = `${m}:${s}`;
  if (left < 300) el.classList.add('low');
  if (left <= 0 && !submitted) { autoSubmit('Time up — auto-submitting'); }
}
setInterval(updateTimer, 1000); updateTimer();

// ------ Webcam ------
if (violCheck('camera')) {
  navigator.mediaDevices.getUserMedia({ video: { width: 240, height: 180 }, audio: false })
    .then(s => { document.getElementById('cam').srcObject = s; })
    .catch(() => logViolation('camera_blocked', 'Webcam access denied'));
} else {
  const cam = document.getElementById('cam');
  if (cam) cam.style.display = 'none';
}

// ------ Fullscreen ------
function enterFS() {
  if (document.fullscreenElement) return Promise.resolve();
  try {
    return document.documentElement.requestFullscreen().catch(()=>{ throw new Error('FS request failed'); });
  } catch (e) {
    // older browsers may throw synchronously
    return Promise.reject(e);
  }
}
function showFSOverlay(msg) {
  if (!violCheck('fullscreen_overlay')) return;
  let o = document.getElementById('fs-exit-overlay');
  if (!o) {
    o = document.createElement('div'); o.id = 'fs-exit-overlay';
    Object.assign(o.style, {position:'fixed',left:0,top:0,right:0,bottom:0,background:'rgba(0,0,0,0.92)',color:'#fff',display:'flex',alignItems:'center',justifyContent:'center',zIndex:2147483647,flexDirection:'column',padding:'20px',textAlign:'center',pointerEvents:'auto'});
    const box = document.createElement('div'); box.style.maxWidth='720px';
    const h = document.createElement('div'); h.id='fs-exit-overlay-msg'; h.style.fontSize='18px'; h.style.marginBottom='18px'; box.appendChild(h);
    const btn = document.createElement('button'); btn.className='btn btn-lg btn-light'; btn.textContent='Return to Fullscreen';
    btn.onclick = () => { enterFS().then(hideFSOverlay).catch(()=>{ /* ignore */ }); };
    box.appendChild(btn);
    o.appendChild(box);
    document.body.appendChild(o);
  }
  document.body.style.overflow = 'hidden';
  document.getElementById('fs-exit-overlay-msg').innerHTML = msg || 'The exam requires fullscreen — please click the button to return to fullscreen.';
  o.style.display = 'flex';
}
function hideFSOverlay() {
  const o = document.getElementById('fs-exit-overlay');
  if (o) o.style.display = 'none';
  document.body.style.overflow = '';
}

function showBlockedKeyWarning(keyName) {
  let toast = document.getElementById('blocked-key-toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'blocked-key-toast';
    Object.assign(toast.style, {
      position: 'fixed', bottom: '20px', right: '20px', backgroundColor: '#dc3545',
      color: '#fff', padding: '15px 20px', borderRadius: '5px', fontSize: '14px',
      fontWeight: 'bold', zIndex: 2147483646, textAlign: 'center',
      boxShadow: '0 4px 6px rgba(0,0,0,0.3)', maxWidth: '300px'
    });
    document.body.appendChild(toast);
  }
  toast.innerHTML = `<i class="fas fa-ban me-2"></i>${keyName} is <strong>BLOCKED</strong> during exam. Do not try again.`;
  toast.style.display = 'block';
  beep();
  setTimeout(() => { if (toast) toast.style.display = 'none'; }, 3000);
}

function startFSRetryLoop() {
  if (fsRetryInterval) return;
  fsRetryEndTime = Date.now() + FS_RETRY_TIMEOUT_MS;
  fsRetryInterval = setInterval(() => {
    if (document.fullscreenElement) { clearInterval(fsRetryInterval); fsRetryInterval = null; hideFSOverlay(); return; }
    if (Date.now() >= fsRetryEndTime) { clearInterval(fsRetryInterval); fsRetryInterval = null; return; }
    enterFS().then(() => { clearInterval(fsRetryInterval); fsRetryInterval = null; hideFSOverlay(); }).catch(() => { /* ignore, keep retrying until timeout */ });
  }, FS_RETRY_MS);
}

document.addEventListener('fullscreenchange', () => {
  if (!_FFS) { return; }
  if (!document.fullscreenElement && !submitted) {
    logViolation('fullscreen_exit', 'Exited fullscreen');
    showFSOverlay('You left fullscreen. The exam will try to return to fullscreen automatically. If it fails, click the button.');
    enterFS().then(() => { hideFSOverlay(); }).catch(() => { startFSRetryLoop(); });
  } else {
    hideFSOverlay();
    if (fsRetryInterval) { clearInterval(fsRetryInterval); fsRetryInterval = null; }
  }
});

function startLockdown() {
  if (_FFS) enterFS();
}

// ------ Tab / window / blur ------
if (violCheck('tab_switch')) {
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden' && !submitted) logViolation('tab_switch', 'Tab/window switched');
  });
}
if (violCheck('window_blur')) {
  window.addEventListener('blur', () => { if (!submitted) logViolation('window_blur', 'Window lost focus'); });
}

// ------ Right-click / copy / paste / keys ------
if (violCheck('right_click')) {
  document.addEventListener('contextmenu', e => { e.preventDefault(); logViolation('right_click', 'Right-click attempted'); });
}
if (violCheck('copy_paste')) {
  document.addEventListener('copy',  e => { e.preventDefault(); logViolation('copy_paste', 'Copy attempted'); });
  document.addEventListener('paste', e => { e.preventDefault(); logViolation('copy_paste', 'Paste attempted'); });
}
if (violCheck('copy_text_select')) {
  document.addEventListener('selectstart', e => { e.preventDefault(); });
  document.body.style.userSelect = 'none';
}
if (violCheck('keyboard_shortcuts') || violCheck('escape_f11_block') || violCheck('devtools_block') || violCheck('windows_key_block') || violCheck('screenshot_block')) {
document.addEventListener('keydown', e => {
  const k = e.key;
  let shouldBlock = false;
  let violType = null;
  let violDesc = null;
  let warningMsg = null;

  // Windows/Super key block (HARD BLOCK)
  if (violCheck('windows_key_block') && !submitted) {
    if (k === 'Meta' || k === 'OS' || e.keyCode === 91 || e.keyCode === 92) {
      shouldBlock = true;
      violType = 'windows_key_attempt';
      violDesc = 'Attempted to press Windows/Super key';
      warningMsg = '🛑 Windows Key Blocked';
    }
  }

  // Screenshot key block (HARD BLOCK — Print Screen, Shift+Print Screen, etc.)
  if (violCheck('screenshot_block') && !submitted && !shouldBlock) {
    if (e.keyCode === 44 || k === 'PrintScreen') {
      shouldBlock = true;
      violType = 'screenshot_attempt';
      violDesc = 'Attempted to capture screenshot: ' + k;
      warningMsg = '📸 Screenshot Blocked';
    }
  }

  // Escape/F11 block (high priority — explicit check first)
  if (violCheck('escape_f11_block') && !submitted && !shouldBlock) {
    if (k === 'Escape' || k === 'Esc' || k === 'F11' || e.keyCode === 27 || e.keyCode === 122) {
      shouldBlock = true;
      violType = 'fullscreen_key_exit';
      violDesc = 'Attempted to exit fullscreen using key: ' + k;
    }
  }

  // Devtools and keyboard shortcuts block
  if ((violCheck('devtools_block') || violCheck('keyboard_shortcuts')) && !submitted && !shouldBlock) {
    const block = k === 'F12' || k === 'F5' || k === 'F11' || k === 'Escape' || k === 'Esc' ||
      (e.ctrlKey && e.shiftKey && ['I','J','C'].includes(k.toUpperCase())) ||
      (e.ctrlKey && ['u','U','s','S','p','P','r','R'].includes(k));
    if (block) {
      shouldBlock = true;
      violType = 'blocked_key';
      violDesc = 'Blocked shortcut: ' + k;
    }
  }

  // Ctrl+W block
  if (violCheck('keyboard_shortcuts') && !submitted && !shouldBlock) {
    if ((e.ctrlKey || e.metaKey) && k.toLowerCase() === 'w') {
      shouldBlock = true;
      violType = 'blocked_key';
      violDesc = 'Attempted to close tab/window: Ctrl+W';
    }
  }

  if (shouldBlock) {
    e.preventDefault();
    e.stopImmediatePropagation();
    if (violType && violDesc) {
      logViolation(violType, violDesc);
      if (warningMsg) showBlockedKeyWarning(warningMsg);
    }
    if (violType === 'fullscreen_key_exit' && _FFS) {
      showFSOverlay('You pressed a key that would exit fullscreen. Returning to fullscreen now...');
      enterFS().then(() => { hideFSOverlay(); }).catch(() => { startFSRetryLoop(); });
    }
  }
}, {capture:true, passive:false});

['keyup','keypress'].forEach(ev => {
  document.addEventListener(ev, e => {
    const k = e.key;
    if (!submitted) {
      let shouldBlockHard = false;
      let warningMsg = null;

      // Escape/F11 hard block
      if (violCheck('escape_f11_block')) {
        if (k === 'Escape' || k === 'Esc' || k === 'F11' || e.keyCode === 27 || e.keyCode === 122) {
          shouldBlockHard = true;
          e.preventDefault();
          e.stopImmediatePropagation();
          logViolation('fullscreen_key_exit', 'Attempted to exit fullscreen using key ('+ev+'): ' + k);
          if (_FFS) { showFSOverlay('You pressed a key that would exit fullscreen. Please click the button to return to fullscreen.'); startFSRetryLoop(); }
        }
      }

      // Screenshot hard block
      if (violCheck('screenshot_block') && !shouldBlockHard) {
        if (e.keyCode === 44 || k === 'PrintScreen') {
          shouldBlockHard = true;
          warningMsg = '📸 Screenshot Blocked';
          e.preventDefault();
          e.stopImmediatePropagation();
          logViolation('screenshot_attempt', 'Attempted to capture screenshot ('+ev+'): ' + k);
          showBlockedKeyWarning(warningMsg);
        }
      }

      // Windows key hard block
      if (violCheck('windows_key_block') && !shouldBlockHard) {
        if (k === 'Meta' || k === 'OS' || e.keyCode === 91 || e.keyCode === 92) {
          shouldBlockHard = true;
          warningMsg = '🛑 Windows Key Blocked';
          e.preventDefault();
          e.stopImmediatePropagation();
          logViolation('windows_key_attempt', 'Attempted to press Windows/Super key ('+ev+')');
          showBlockedKeyWarning(warningMsg);
        }
      }
    }
  }, {capture:true, passive:false});
});
}

// Hard block for Windows key and Print Screen — prevent system drawer/capture from opening
if (violCheck('windows_key_block') || violCheck('screenshot_block')) {
  window.addEventListener('keydown', e => {
    if (submitted) return;
    let blockKey = false;
    let msg = null;
    
    if (violCheck('windows_key_block') && (e.keyCode === 91 || e.keyCode === 92 || e.key === 'Meta' || e.key === 'OS')) {
      blockKey = true;
      msg = '🛑 Windows Key Blocked';
    }
    if (violCheck('screenshot_block') && (e.keyCode === 44 || e.key === 'PrintScreen')) {
      blockKey = true;
      msg = '📸 Screenshot Blocked';
    }
    
    if (blockKey) {
      e.preventDefault();
      e.stopImmediatePropagation();
      if (msg) showBlockedKeyWarning(msg);
      logViolation('blocked_key', 'Blocked key: ' + (e.key || e.keyCode));
      return false;
    }
  }, {capture:true, passive:false});

  window.addEventListener('keyup', e => {
    if (submitted) return;
    let blockKey = false;
    
    if (violCheck('windows_key_block') && (e.keyCode === 91 || e.keyCode === 92 || e.key === 'Meta' || e.key === 'OS')) {
      blockKey = true;
    }
    if (violCheck('screenshot_block') && (e.keyCode === 44 || e.key === 'PrintScreen')) {
      blockKey = true;
    }
    
    if (blockKey) {
      e.preventDefault();
      e.stopImmediatePropagation();
      return false;
    }
  }, {capture:true, passive:false});
}

window.addEventListener('beforeunload', e => { if (!submitted) { e.preventDefault(); e.returnValue = ''; } });

// ------ Future-proof checks ------
if (violCheck('second_display')) {
  // Second display detection (experimental) — warn if window.screen.width mismatches.
  try {
    const expected = window.screen.availWidth;
    setInterval(() => {
      if (window.screen.availWidth !== expected && !submitted) logViolation('second_display', 'Multiple displays detected');
    }, 5000);
  } catch(e) {}
}

// ------ Violations ------
function logViolation(type, desc) {
  if (submitted) return;
  violations++;
  document.getElementById('viol-count').textContent = violations;
  const w = document.getElementById('warn');
  w.textContent = `Warning ${violations}/${MAX_V}: ${desc}`;
  w.classList.remove('d-none');
  setTimeout(() => w.classList.add('d-none'), 4000);
  fetch(VIOL_URL, { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'attempt_id=' + ATTEMPT_ID + '&event_type=' + encodeURIComponent(type) + '&description=' + encodeURIComponent(desc) });
  if (violations >= MAX_V) autoSubmit('Auto-submitted: too many violations');
}

// ------ Save answer ------
function saveAns(qid, answer) {
  ANSWERED.add(String(qid));
  updatePalette(qid);
  fetch(SAVE_URL, { method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ attempt_id: ATTEMPT_ID, question_id: qid, answer, marked_review: MARKED.has(String(qid)) }) });
}
function saveMulti(qid) {
  const selected = Array.from(document.querySelectorAll('.ms-' + qid + ':checked')).map(i => parseInt(i.value));
  saveAns(qid, { selected });
  if (selected.length === 0) ANSWERED.delete(String(qid));
  updatePalette(qid);
}

// ------ Navigation ------
function show(idx) {
  document.querySelectorAll('.q-box').forEach((b, i) => b.style.display = i === idx ? 'block' : 'none');
  CURRENT = idx;
  document.getElementById('cur').textContent = idx + 1;
  VISITED.add(String(idx));
  document.querySelectorAll('.palette-btn').forEach((p, i) => p.classList.toggle('current', i === idx));
  const qid = document.querySelectorAll('.q-box')[idx].dataset.qid;
  setMarkLabel(MARKED.has(String(qid)));
}
function goTo(i) { show(i); }
function prev() { if (CURRENT > 0) show(CURRENT - 1); }
function next() { if (CURRENT < TOTAL - 1) show(CURRENT + 1); }

function setMarkLabel(isMarked) {
  const el = document.getElementById('mark-lbl');
  if (!el) return;
  // Rebuild bilingual spans; the global language engine will show the right one.
  const en = isMarked ? 'Unmark Review' : 'Mark for Review';
  const hi = isMarked ? 'चिह्न हटाएँ' : 'समीक्षा हेतु चिह्नित करें';
  el.innerHTML = '<span data-lang-text="en">' + en + '</span>' +
                 '<span data-lang-text="hi">' + hi + '</span>';
  const lang = (typeof currentExamLang !== 'undefined') ? currentExamLang : (document.body.getAttribute('data-lang') || 'en');
  el.querySelectorAll('[data-lang-text]').forEach(s => {
    s.classList.toggle('lang-hidden', s.getAttribute('data-lang-text') !== lang);
  });
}

function toggleMark() {
  const box = document.querySelectorAll('.q-box')[CURRENT];
  const qid = String(box.dataset.qid);
  if (MARKED.has(qid)) MARKED.delete(qid); else MARKED.add(qid);
  updatePalette(qid);
  setMarkLabel(MARKED.has(qid));
  // persist via save-answer with current answer state (best-effort empty)
  fetch(SAVE_URL, { method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ attempt_id: ATTEMPT_ID, question_id: parseInt(qid), answer: null, marked_review: MARKED.has(qid), mark_only: true }) });
}

function updatePalette(qid) {
  const btns = document.querySelectorAll('.palette-btn');
  btns.forEach((b) => {
    if (String(b.dataset.qid) !== String(qid)) return;
    b.classList.remove('answered','not-answered','marked','ans-marked');
    const has = ANSWERED.has(String(qid));
    const mk = MARKED.has(String(qid));
    if (mk && has) b.classList.add('ans-marked');
    else if (mk) b.classList.add('marked');
    else if (has) b.classList.add('answered');
    else if (VISITED.has(String(b.dataset.idx))) b.classList.add('not-answered');
  });
}
// Initial palette sync
document.querySelectorAll('.q-box').forEach(b => updatePalette(b.dataset.qid));

// ------ Submit ------
function submitExam() {
  const lang = (typeof currentExamLang !== 'undefined') ? currentExamLang : (document.body.getAttribute('data-lang') || 'en');
  const msg = (lang === 'hi')
    ? 'क्या आप वाकई परीक्षा जमा करना चाहते हैं? यह क्रिया वापस नहीं ली जा सकती।'
    : 'Submit exam now? This cannot be undone.';
  if (!confirm(msg)) return;
  autoSubmit(null);
}
function autoSubmit(reason) {
  if (submitted) return;
  submitted = true;
  if (reason) alert(reason);
  try { document.getElementById('cam').srcObject?.getTracks().forEach(t => t.stop()); } catch(e){}
  window.location.href = SUBMIT_URL;
}
