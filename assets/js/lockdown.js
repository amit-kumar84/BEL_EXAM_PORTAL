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
const FS_RETRY_MS = 50;
const FS_RETRY_TIMEOUT_MS = 3000;

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
function _fsEl() { return document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement || null; }
function enterFS() {
  if (_fsEl()) return Promise.resolve();
  const el = document.documentElement;
  const req = el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen || el.mozRequestFullScreen;
  if (!req) return Promise.reject(new Error('Fullscreen API not supported'));
  try {
    const r = req.call(el);
    // Safari / old browsers return undefined (not a Promise)
    if (r && typeof r.then === 'function') {
      return r.catch(e => { throw new Error('FS request failed: ' + (e && e.message || e)); });
    }
    // Wait for fullscreenchange as a pseudo-promise fallback
    return new Promise((resolve, reject) => {
      let done = false;
      const timer = setTimeout(() => { if (!done) { done = true; _fsEl() ? resolve() : reject(new Error('FS timeout')); } }, 2000);
      const on = () => { if (!done && _fsEl()) { done = true; clearTimeout(timer); document.removeEventListener('fullscreenchange', on); document.removeEventListener('webkitfullscreenchange', on); resolve(); } };
      document.addEventListener('fullscreenchange', on);
      document.addEventListener('webkitfullscreenchange', on);
    });
  } catch (e) {
    return Promise.reject(e);
  }
}
function showFSOverlay(msg) {
  if (!violCheck('fullscreen_overlay')) return;
  let o = document.getElementById('fs-exit-overlay');
  if (!o) {
    o = document.createElement('div'); o.id = 'fs-exit-overlay';
    Object.assign(o.style, {position:'fixed',left:0,top:0,right:0,bottom:0,background:'rgba(15,23,42,0.96)',color:'#fff',display:'flex',alignItems:'center',justifyContent:'center',zIndex:2147483647,flexDirection:'column',padding:'20px',textAlign:'center',pointerEvents:'auto',cursor:'pointer'});
    const box = document.createElement('div'); box.style.maxWidth='720px';
    const h = document.createElement('div'); h.id='fs-exit-overlay-msg'; h.style.fontSize='18px'; h.style.marginBottom='18px'; box.appendChild(h);
    const btn = document.createElement('button'); btn.className='btn btn-lg btn-light'; btn.textContent='Return to Fullscreen';
    btn.onclick = (ev) => { ev.stopPropagation(); enterFS().then(hideFSOverlay).catch(()=>{ /* ignore */ }); };
    box.appendChild(btn);
    const hint = document.createElement('div'); hint.style.marginTop='14px'; hint.style.fontSize='12px'; hint.style.color='#cbd5e1'; hint.textContent='Tap anywhere to return instantly — this screen auto-retries every 50 ms';
    box.appendChild(hint);
    o.appendChild(box);
    // Any click / pointerdown on the overlay re-enters fullscreen using the user gesture.
    o.addEventListener('pointerdown', () => { enterFS().then(hideFSOverlay).catch(()=>{}); });
    document.body.appendChild(o);
  }
  document.body.style.overflow = 'hidden';
  document.getElementById('fs-exit-overlay-msg').innerHTML = msg || 'The exam requires fullscreen — please click to return to fullscreen.';
  o.style.display = 'flex';
}
function hideFSOverlay() {
  const o = document.getElementById('fs-exit-overlay');
  if (o) o.style.display = 'none';
  // Also clear the initial "Enter Fullscreen to Start" overlay if it is still around
  // (some browsers fire fullscreenchange before the click handler completes its remove()).
  const start = document.getElementById('fs-overlay');
  if (start && document.fullscreenElement) { try { start.remove(); } catch(_){} }
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
    if (_fsEl()) { clearInterval(fsRetryInterval); fsRetryInterval = null; hideFSOverlay(); return; }
    if (Date.now() >= fsRetryEndTime) { clearInterval(fsRetryInterval); fsRetryInterval = null; return; }
    enterFS().then(() => { clearInterval(fsRetryInterval); fsRetryInterval = null; hideFSOverlay(); }).catch(() => { /* ignore, keep retrying until timeout */ });
  }, FS_RETRY_MS);
}

document.addEventListener('fullscreenchange', () => _onFullscreenChange());
document.addEventListener('webkitfullscreenchange', () => _onFullscreenChange());
document.addEventListener('msfullscreenchange', () => _onFullscreenChange());
function _onFullscreenChange() {
  if (!_FFS) { return; }
  if (!_fsEl() && !submitted) {
    logViolation('fullscreen_exit', 'Exited fullscreen');
    // Try IMMEDIATE synchronous re-entry first (some browsers accept this inside fullscreenchange
    // if the original user activation gesture is still considered valid).
    try { enterFS().catch(() => {}); } catch(_) {}
    showFSOverlay('You left fullscreen. Auto-returning now...');
    // Start fast retry loop (50 ms × 60 = 3 s) — any click in the overlay will also force FS.
    startFSRetryLoop();
  } else {
    hideFSOverlay();
    if (fsRetryInterval) { clearInterval(fsRetryInterval); fsRetryInterval = null; }
  }
}

// Aggressive top-level F11 / Esc blocker — runs BEFORE any other handler in capture phase.
// Note: modern browsers reserve F11/Esc for exiting fullscreen and may ignore preventDefault for
// these specific keys. What we CAN guarantee is instant detection + instant re-entry retry.
window.addEventListener('keydown', e => {
  if (submitted) return;
  if ((e.key === 'F11' || e.keyCode === 122 || e.key === 'Escape' || e.key === 'Esc' || e.keyCode === 27)) {
    try { e.preventDefault(); e.stopImmediatePropagation(); e.stopPropagation(); } catch(_){}
    // If we are still in fullscreen (key was blocked), log once and show a brief toast.
    // If the browser ignored preventDefault (typical for Esc/F11), fullscreenchange will fire
    // and our retry loop will slam us back in within ~50 ms.
    logViolation('fullscreen_key_exit', 'Blocked fullscreen exit key: ' + (e.key || e.keyCode));
    showBlockedKeyWarning(e.key === 'F11' ? '⛔ F11 Blocked' : '⛔ Esc Blocked');
    return false;
  }
}, {capture: true, passive: false});

function startLockdown() {
  if (_FFS) enterFS();
}

// ------ Tab / window / blur ------
// Unify tab_switch and window_blur under one logical event so a single Alt+Tab
// (which fires BOTH visibilitychange and window.blur) doesn't count as 2 violations.
// The dedup window inside logViolation() also guards against repeats within 2.5s.
if (violCheck('tab_switch')) {
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden' && !submitted) logViolation('tab_switch', 'Tab/window switched');
  });
}
if (violCheck('window_blur')) {
  // Same dedup key so blur fired alongside visibilitychange is merged.
  window.addEventListener('blur', () => {
    if (!submitted) logViolation('tab_switch', 'Window lost focus');
  });
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

// ============================================================================
// COMPLETE LOCKDOWN — Mac / Linux / Windows hard-blocks + extension + screen-share
// All gated by VIOLATION_CONFIG toggles from admin/exams.php.
// ============================================================================

// ---- 1. Mac shortcuts: Cmd+Tab, Cmd+Q, Cmd+H, Cmd+M, Cmd+Space, Cmd+W, Cmd+N, Cmd+T, Cmd+` ----
// ---- 2. Alt shortcuts: Alt+Tab, Alt+F4, Alt+Space, Alt+Left/Right ----
// ---- 3. All function keys F1..F12 ----
// ---- 4. Mac screenshot combos: Cmd+Shift+3/4/5, Cmd+Ctrl+Shift+3/4 ----
// Single consolidated handler running in capture phase, highest priority.
function _hardBlockKey(e, type, desc, label) {
  try { e.preventDefault(); e.stopImmediatePropagation(); e.stopPropagation(); } catch(_) {}
  if (!submitted) {
    logViolation(type, desc);
    if (label) showBlockedKeyWarning(label);
  }
  return false;
}

document.addEventListener('keydown', e => {
  if (submitted) return;
  const k = (e.key || '');
  const code = e.keyCode || 0;
  const isMac = /Mac|iPhone|iPad|iPod/i.test(navigator.platform || '');
  const meta = e.metaKey;
  const ctrl = e.ctrlKey;
  const shift = e.shiftKey;
  const alt = e.altKey;
  const kLower = k.toLowerCase();

  // --- All function keys F1..F12 ---
  if (violCheck('all_function_keys_block') && /^F([1-9]|1[0-2])$/.test(k)) {
    return _hardBlockKey(e, 'blocked_key', 'Function key blocked: ' + k, '⛔ ' + k + ' Blocked');
  }

  // --- Mac screenshot combos: Cmd+Shift+3/4/5, Cmd+Ctrl+Shift+3/4 ---
  if (violCheck('screenshot_block') && meta && shift && (k === '3' || k === '4' || k === '5')) {
    return _hardBlockKey(e, 'screenshot_attempt', 'Mac screenshot combo: Cmd+Shift+' + k, '📸 Screenshot Blocked');
  }
  // Linux: Shift+PrtScr, Ctrl+PrtScr, Alt+PrtScr already caught by base PrtScr check; add explicit.
  if (violCheck('screenshot_block') && (shift || ctrl || alt) && (code === 44 || k === 'PrintScreen')) {
    return _hardBlockKey(e, 'screenshot_attempt', 'Screenshot combo blocked', '📸 Screenshot Blocked');
  }

  // --- Mac shortcuts (all Cmd+X combos except allowed ones) ---
  if (violCheck('mac_shortcuts_block') && meta && !ctrl) {
    // Cmd+Tab (app switcher)
    if (k === 'Tab') return _hardBlockKey(e, 'blocked_key', 'Blocked Cmd+Tab (app switcher)', '⛔ Cmd+Tab Blocked');
    // Cmd+Q (quit app), Cmd+H (hide), Cmd+M (minimise), Cmd+W (close tab), Cmd+N (new window), Cmd+T (new tab)
    if (['q','h','m','w','n','t'].includes(kLower)) {
      return _hardBlockKey(e, 'blocked_key', 'Blocked Cmd+' + k.toUpperCase(), '⛔ Cmd+' + k.toUpperCase() + ' Blocked');
    }
    // Cmd+Space (Spotlight)
    if (k === ' ' || code === 32) return _hardBlockKey(e, 'blocked_key', 'Blocked Cmd+Space (Spotlight)', '⛔ Spotlight Blocked');
    // Cmd+` (cycle windows)
    if (k === '`') return _hardBlockKey(e, 'blocked_key', 'Blocked Cmd+` (window cycle)', '⛔ Window Cycle Blocked');
    // Cmd+, (preferences), Cmd+Option+Esc (force quit)
    if (k === ',' || (alt && k === 'Escape')) return _hardBlockKey(e, 'blocked_key', 'Blocked Cmd combo', '⛔ Blocked');
  }

  // --- Alt shortcuts ---
  if (violCheck('alt_shortcuts_block') && alt) {
    // Alt+Tab (win/linux switcher), Alt+F4 (close window), Alt+Space (window menu)
    if (k === 'Tab') return _hardBlockKey(e, 'blocked_key', 'Blocked Alt+Tab', '⛔ Alt+Tab Blocked');
    if (k === 'F4')  return _hardBlockKey(e, 'blocked_key', 'Blocked Alt+F4', '⛔ Alt+F4 Blocked');
    if (k === ' ')   return _hardBlockKey(e, 'blocked_key', 'Blocked Alt+Space', '⛔ Alt+Space Blocked');
    // Alt+Left / Alt+Right = browser back/forward
    if (k === 'ArrowLeft' || k === 'ArrowRight') {
      return _hardBlockKey(e, 'blocked_key', 'Blocked Alt+' + k + ' (browser nav)', '⛔ Browser Nav Blocked');
    }
  }

  // --- Extra Ctrl combos (backup to existing) ---
  if (violCheck('keyboard_shortcuts') && (ctrl || meta)) {
    // Ctrl+N (new window), Ctrl+T (new tab), Ctrl+Tab (switch tab), Ctrl+Shift+T (reopen tab)
    if (['n','t'].includes(kLower))     return _hardBlockKey(e, 'blocked_key', 'Blocked Ctrl+' + k.toUpperCase(), '⛔ Ctrl+' + k.toUpperCase() + ' Blocked');
    if (k === 'Tab')                    return _hardBlockKey(e, 'blocked_key', 'Blocked Ctrl+Tab', '⛔ Ctrl+Tab Blocked');
    if (shift && kLower === 't')        return _hardBlockKey(e, 'blocked_key', 'Blocked Ctrl+Shift+T', '⛔ Blocked');
    if (shift && kLower === 'n')        return _hardBlockKey(e, 'blocked_key', 'Blocked Ctrl+Shift+N (incognito)', '⛔ Blocked');
    // Linux: Super+L (lock screen) — Meta+L
    if (meta && kLower === 'l')         return _hardBlockKey(e, 'blocked_key', 'Blocked Meta+L (lock)', '⛔ Blocked');
  }
}, {capture: true, passive: false});

// Also intercept keyup for the same set (some OS capture on keyup for screenshots)
document.addEventListener('keyup', e => {
  if (submitted) return;
  const k = (e.key || '');
  const meta = e.metaKey, shift = e.shiftKey;
  if (violCheck('screenshot_block') && meta && shift && (k === '3' || k === '4' || k === '5')) {
    try { e.preventDefault(); e.stopImmediatePropagation(); } catch(_){}
  }
  if (violCheck('all_function_keys_block') && /^F([1-9]|1[0-2])$/.test(k)) {
    try { e.preventDefault(); e.stopImmediatePropagation(); } catch(_){}
  }
}, {capture: true, passive: false});

// ---- 5. Clipboard API block + drag-drop block + cut ----
if (violCheck('clipboard_api_block')) {
  document.addEventListener('cut', e => { e.preventDefault(); logViolation('copy_paste', 'Cut attempted'); }, {capture:true});
  document.addEventListener('dragstart', e => { e.preventDefault(); }, {capture:true});
  document.addEventListener('drop', e => { e.preventDefault(); logViolation('blocked_key', 'Drop attempted'); }, {capture:true});
  document.addEventListener('dragover', e => { e.preventDefault(); }, {capture:true});
  // Neutralise navigator.clipboard (can't fully remove but reject reads)
  try {
    if (navigator.clipboard && navigator.clipboard.readText) {
      const orig = navigator.clipboard.readText.bind(navigator.clipboard);
      navigator.clipboard.readText = function() { logViolation('copy_paste', 'clipboard.readText blocked'); return Promise.reject(new Error('blocked')); };
      // keep orig reference just so we don't crash if exam code needs it (we never do)
      void orig;
    }
  } catch(_) {}
}

// ---- 6. Extension / AI overlay blocker (MutationObserver) ----
if (violCheck('extension_overlay_block')) {
  // Known extension / AI overlay signatures
  const BAD_IDS = [
    'grammarly', 'gramm', 'copilot', 'chatgpt', 'ai-assistant', 'ai-widget',
    'honey', 'lastpass', 'bitwarden', 'dashlane', 'loom', 'notion', 'clipdrop',
    'extension-', 'ext-', 'translate', 'deepl', 'monica'
  ];
  const BAD_ATTR = ['data-gramm', 'data-gramm_editor', 'data-grammarly-shadow-root', 'data-extension'];

  function looksLikeExtensionNode(node) {
    if (!node || node.nodeType !== 1) return false;
    const id = (node.id || '').toLowerCase();
    const cls = (node.className && typeof node.className === 'string' ? node.className : '').toLowerCase();
    if (BAD_IDS.some(s => id.includes(s) || cls.includes(s))) return true;
    for (const a of BAD_ATTR) { if (node.hasAttribute && node.hasAttribute(a)) return true; }
    // Iframes from other origins injected into the page (not our own)
    if (node.tagName === 'IFRAME') {
      const src = (node.src || '') + '';
      if (src && !src.startsWith(location.origin) && !src.startsWith('about:') && !src.startsWith('blob:')) return true;
    }
    // Very high z-index floating divs that are NOT ours
    try {
      const st = node instanceof HTMLElement ? window.getComputedStyle(node) : null;
      if (st && (st.position === 'fixed' || st.position === 'absolute')) {
        const z = parseInt(st.zIndex || '0', 10);
        const isOurs = node.id === 'fs-overlay' || node.id === 'fs-exit-overlay' || node.id === 'blocked-key-toast' || node.closest('.exam-wrap, .exam-header, .exam-side, .exam-footer');
        if (z > 2147483000 && !isOurs) return true;
      }
    } catch(_) {}
    return false;
  }

  const obs = new MutationObserver(muts => {
    if (submitted) return;
    for (const m of muts) {
      for (const n of m.addedNodes) {
        if (looksLikeExtensionNode(n)) {
          try { n.remove(); } catch(_) {}
          logViolation('extension_overlay', 'Extension / AI overlay removed: ' + (n.id || n.tagName));
        }
      }
    }
  });
  try { obs.observe(document.documentElement, { childList: true, subtree: true, attributes: false }); } catch(_) {}

  // Sweep on load for anything already injected
  setTimeout(() => {
    document.querySelectorAll('body *').forEach(n => {
      if (looksLikeExtensionNode(n)) { try { n.remove(); } catch(_){}; logViolation('extension_overlay', 'Pre-loaded extension overlay removed'); }
    });
  }, 800);
}

// ---- 7. Screen sharing detection (getDisplayMedia already active heuristic) ----
if (violCheck('screen_sharing_block')) {
  // Method 1: wrap getDisplayMedia so if any script (incl. extension) asks, we log + reject
  try {
    if (navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) {
      const origGDM = navigator.mediaDevices.getDisplayMedia.bind(navigator.mediaDevices);
      navigator.mediaDevices.getDisplayMedia = function(...args) {
        if (!submitted) { logViolation('screen_sharing', 'getDisplayMedia invoked (screen-share attempt)'); showBlockedKeyWarning('🛑 Screen Share Blocked'); }
        return Promise.reject(new DOMException('Blocked by exam proctor', 'NotAllowedError'));
      };
      void origGDM;
    }
  } catch(_) {}

  // Method 2: one-shot extended-display check (only log ONCE on load, not every 5s).
  // screen.isExtended is a reliable signal of multi-monitor; dedup handler also guards.
  try {
    if (typeof screen !== 'undefined' && typeof screen.isExtended === 'boolean' && screen.isExtended) {
      // Delay so page has finished initial paint before first violation fires.
      setTimeout(() => { if (!submitted) logViolation('screen_sharing', 'Multiple / extended displays detected'); }, 2000);
    }
  } catch(_) {}
}

// ---- 8. Remote access (RDP / AnyDesk / TeamViewer) heuristics ----
// Only keep the pointer-latency pattern test. Colour depth / hardwareConcurrency /
// devicePixelRatio gave too many false positives on normal laptops with dual displays.
if (violCheck('remote_access_block')) {
  // Pointer event latency — remote sessions often have higher latency variance.
  // One-shot: only log the first sustained lag pattern per session.
  let lastMove = 0, laggySamples = 0, alreadyLogged = false;
  document.addEventListener('pointermove', e => {
    if (alreadyLogged) return;
    const now = performance.now();
    if (lastMove && (now - lastMove) > 250) laggySamples++;
    lastMove = now;
    if (laggySamples >= 20 && !submitted) {
      alreadyLogged = true;
      logViolation('remote_access', 'High pointer latency pattern — possible remote desktop');
    }
  }, {passive: true, capture: true});
}

// ---- 9. CSP meta injection (block external iframes/scripts from extensions where possible) ----
(function injectCSP(){
  try {
    if (!violCheck('extension_overlay_block')) return;
    // Can only add hints via meta; real CSP is server-side. Best-effort.
    const m = document.createElement('meta');
    m.httpEquiv = 'Content-Security-Policy';
    m.content = "frame-src 'self' blob: data:; child-src 'self' blob: data:;";
    document.head.appendChild(m);
  } catch(_) {}
})();

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
// Throttle + dedup: same violation type within 2.5s is suppressed → prevents flooding
// when a single user action triggers multiple listeners (e.g. blur + visibilitychange).
const _VIOL_LAST = Object.create(null);
const _VIOL_DEDUP_MS = 2500;
function logViolation(type, desc) {
  if (submitted) return;
  const now = Date.now();
  if (_VIOL_LAST[type] && (now - _VIOL_LAST[type]) < _VIOL_DEDUP_MS) {
    // Duplicate of same type within dedup window — don't double-count.
    return;
  }
  _VIOL_LAST[type] = now;
  violations++;
  const vc = document.getElementById('viol-count'); if (vc) vc.textContent = violations;
  const w = document.getElementById('warn');
  if (w) {
    w.textContent = `Warning ${violations}/${MAX_V}: ${desc}`;
    w.classList.remove('d-none');
    setTimeout(() => w.classList.add('d-none'), 4000);
  }
  try {
    fetch(VIOL_URL, { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'attempt_id=' + ATTEMPT_ID + '&event_type=' + encodeURIComponent(type) + '&description=' + encodeURIComponent(desc) });
  } catch(_) {}
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
