/* TestaCBT mock exam runner. The server owns the paper, the clock and the marking; this script only shows
   the questions, keeps the countdown, and saves the student's answers as they go (with a local backup). */
(function () {
  'use strict';

  var dataEl = document.getElementById('session-data');
  if (!dataEl) { return; }

  var S = JSON.parse(dataEl.textContent);
  var Q = S.questions;
  var P = S.passages || {};
  var G = S.groups;
  var N = Q.length;
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var LETTERS = ['A', 'B', 'C', 'D', 'E'];

  // Where each subject starts and ends in the question list.
  var ranges = [];
  (function () { var off = 0; G.forEach(function (g) { ranges.push({ start: off, end: off + g.count - 1, count: g.count, name: g.name }); off += g.count; }); })();

  // The server clock is the truth. Measure how far this device is from it once, and use that for the countdown.
  var skew = Date.parse(S.server_now) - Date.now();
  var deadline = Date.parse(S.deadline);
  var LOCAL_KEY = 'tc-mock-' + S.endpoints.save;

  var state = {
    i: Math.max(0, Math.min(S.position || 0, N - 1)),
    answers: Object.assign({}, S.answers || {}),
    flagged: {},
    dirty: false,
    submitting: false
  };
  (S.flagged || []).forEach(function (id) { state.flagged[id] = true; });

  // If this device has newer answers than the server (the connection dropped, then the tab was reopened), use them.
  try {
    var local = JSON.parse(localStorage.getItem(LOCAL_KEY) || 'null');
    if (local && local.t > Date.parse(S.saved_at || 0)) {
      state.answers = local.answers || {};
      state.flagged = {};
      (local.flagged || []).forEach(function (id) { state.flagged[id] = true; });
      state.i = Math.max(0, Math.min(local.i || 0, N - 1));
      state.dirty = true;
    }
  } catch (e) {}

  var $ = function (id) { return document.getElementById(id); };
  var el = {
    tabs: $('tabs'), label: $('q-label'), flag: $('btn-flag'), passage: $('q-passage'), text: $('q-text'),
    options: $('q-options'), sheet: $('sheet'), note: $('sheet-note'), prev: $('btn-prev'), next: $('btn-next'),
    submitSide: $('btn-submit-side'), timer: $('timer'), timerText: $('timer-text'), saving: $('saving')
  };

  function h(tag, cls, html) {
    var n = document.createElement(tag);
    if (cls) { n.className = cls; }
    if (html != null) { n.innerHTML = html; }
    return n;
  }

  function toast(message) {
    var t = h('div', 'toast', null);
    t.textContent = message;
    t.setAttribute('role', 'status');
    document.body.appendChild(t);
    setTimeout(function () { t.remove(); }, 3500);
  }

  function groupOf(index) {
    for (var g = 0; g < ranges.length; g++) { if (index >= ranges[g].start && index <= ranges[g].end) { return g; } }
    return 0;
  }

  function answeredIn(range) {
    var n = 0;
    for (var k = range.start; k <= range.end; k++) { if (state.answers[Q[k].id]) { n++; } }
    return n;
  }

  // ---------------------------------------------------------------- rendering

  function render() {
    var question = Q[state.i];
    var gi = groupOf(state.i);
    var r = ranges[gi];

    // Subject tabs
    el.tabs.innerHTML = '';
    ranges.forEach(function (range, idx) {
      var b = h('button', 'chip' + (idx === gi ? ' on' : ''));
      b.type = 'button';
      b.setAttribute('role', 'tab');
      b.setAttribute('aria-selected', idx === gi ? 'true' : 'false');
      b.textContent = range.name + ' ' + answeredIn(range) + '/' + range.count;
      b.addEventListener('click', function () { state.i = range.start; move(); });
      el.tabs.appendChild(b);
    });

    el.label.innerHTML = 'Question ' + (state.i - r.start + 1) + ' <span class="muted" style="font-weight:500">of ' + r.count + '</span>';

    var flagged = !!state.flagged[question.id];
    el.flag.setAttribute('aria-pressed', flagged ? 'true' : 'false');
    el.flag.style.color = flagged ? 'var(--accent-ink)' : '';
    el.flag.style.background = flagged ? 'var(--accent-soft)' : '';

    var passage = question.passage_id ? P[question.passage_id] : null;
    if (passage) { el.passage.innerHTML = '<p class="h3" style="margin-bottom:8px">Read this first</p>' + passage; el.passage.hidden = false; }
    else { el.passage.hidden = true; el.passage.innerHTML = ''; }

    el.text.innerHTML = question.html;

    el.options.innerHTML = '';
    var chosen = state.answers[question.id];
    Object.keys(question.options).sort().forEach(function (k) {
      var b = h('button', 'opt' + (chosen === k ? ' sel' : ''));
      b.type = 'button';
      b.setAttribute('aria-pressed', chosen === k ? 'true' : 'false');
      b.appendChild(h('span', 'bub', k));
      b.appendChild(h('span', null, question.options[k]));
      b.addEventListener('click', function () { choose(k); });
      el.options.appendChild(b);
    });

    // Answer sheet for the current subject, numbered from 1 within the subject.
    el.sheet.innerHTML = '';
    for (var k = r.start; k <= r.end; k++) {
      (function (index) {
        var q = Q[index];
        var b = h('button', 'n', String(index - r.start + 1));
        b.type = 'button';
        b.setAttribute('aria-label', 'Go to question ' + (index - r.start + 1));
        if (state.flagged[q.id]) { b.classList.add('f'); }
        else if (state.answers[q.id]) { b.classList.add('a'); }
        if (index === state.i) { b.classList.add('cur'); }
        b.addEventListener('click', function () { state.i = index; move(); });
        el.sheet.appendChild(b);
      })(k);
    }
    el.note.textContent = answeredIn(r) + ' of ' + r.count + ' answered';

    el.prev.disabled = state.i === 0;
    el.next.textContent = state.i < N - 1 ? 'Next' : 'Review and submit';

    if (window.MathJax && MathJax.typesetPromise) { MathJax.typesetPromise([document.getElementById('runner')]).catch(function () {}); }
  }

  // ---------------------------------------------------------------- saving

  var saveTimer = null;

  function snapshot() {
    return { answers: state.answers, flagged: Object.keys(state.flagged).filter(function (k) { return state.flagged[k]; }).map(Number), position: state.i };
  }

  function backupLocally() {
    try {
      var s = snapshot();
      localStorage.setItem(LOCAL_KEY, JSON.stringify({ t: Date.now() + skew, answers: s.answers, flagged: s.flagged, i: s.position }));
    } catch (e) {}
  }

  function markDirty() {
    state.dirty = true;
    backupLocally();
    clearTimeout(saveTimer);
    saveTimer = setTimeout(function () { save(false); }, 1200);
  }

  function save(keepalive) {
    if (!state.dirty || state.submitting) { return; }
    state.dirty = false;
    el.saving.textContent = 'Saving...';

    fetch(S.endpoints.save, {
      method: 'POST', credentials: 'same-origin', keepalive: !!keepalive,
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify(snapshot())
    }).then(function (r) {
      if (!r.ok) { throw new Error('save failed'); }
      return r.json();
    }).then(function (j) {
      el.saving.textContent = 'Saved';
      setTimeout(function () { if (el.saving.textContent === 'Saved') { el.saving.textContent = ''; } }, 2000);
      if (j.submitted) { window.location.href = S.endpoints.result; }
    }).catch(function () {
      state.dirty = true;
      el.saving.textContent = 'Not saved yet. Your answers are kept on this device. Trying again...';
      clearTimeout(saveTimer);
      saveTimer = setTimeout(function () { save(false); }, 5000);
    });
  }

  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') { save(true); } });
  window.addEventListener('pagehide', function () { save(true); });
  window.addEventListener('online', function () { save(false); });
  setInterval(function () { if (state.dirty) { save(false); } }, 20000);

  // ---------------------------------------------------------------- actions

  function move() { markDirty(); render(); window.scrollTo(0, 0); }

  function choose(k) {
    var id = Q[state.i].id;
    if (state.answers[id] === k) { delete state.answers[id]; }   // tap again to clear
    else { state.answers[id] = k; }
    markDirty();
    render();
  }

  function toggleFlag() {
    var id = Q[state.i].id;
    state.flagged[id] = !state.flagged[id];
    markDirty();
    render();
  }

  function next() {
    if (state.i < N - 1) { state.i++; move(); } else { submit(false); }
  }

  function prev() { if (state.i > 0) { state.i--; move(); } }

  function submit(auto) {
    if (state.submitting) { return; }

    if (!auto) {
      var answered = Object.keys(state.answers).length;
      var left = N - answered;
      var flags = Object.keys(state.flagged).filter(function (k) { return state.flagged[k]; }).length;
      var msg = 'Submit your exam now?\n\n' + answered + ' of ' + N + ' answered' +
        (left ? '\n' + left + ' not answered' : '') + (flags ? '\n' + flags + ' flagged' : '') +
        '\n\nYou cannot change your answers after you submit.';
      if (!window.confirm(msg)) { return; }
    }

    state.submitting = true;
    [el.next, el.prev, el.submitSide].forEach(function (b) { b.disabled = true; });
    el.saving.textContent = auto ? 'Time is up. Submitting...' : 'Submitting...';

    fetch(S.endpoints.submit, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ answers: state.answers })
    }).then(function (r) {
      if (!r.ok) { throw new Error('submit failed'); }
      return r.json();
    }).then(function (j) {
      try { localStorage.removeItem(LOCAL_KEY); } catch (e) {}
      window.location.href = j.result;
    }).catch(function () {
      state.submitting = false;
      [el.next, el.prev, el.submitSide].forEach(function (b) { b.disabled = false; });
      el.saving.textContent = '';
      toast('Could not submit. Check your connection' + (auto ? '. Trying again...' : ' and try again.'));
      if (auto) { setTimeout(function () { submit(true); }, 5000); }
    });
  }

  // ---------------------------------------------------------------- clock

  var warned = {};

  function fmt(total) {
    var hrs = Math.floor(total / 3600), mins = Math.floor((total % 3600) / 60), secs = total % 60;
    var mm = (mins < 10 ? '0' : '') + mins, ss = (secs < 10 ? '0' : '') + secs;
    return hrs ? hrs + ':' + mm + ':' + ss : mm + ':' + ss;
  }

  function tick() {
    var left = Math.max(0, Math.round((deadline - (Date.now() + skew)) / 1000));
    el.timerText.textContent = fmt(left);
    el.timer.classList.toggle('low', left <= 300);

    [[600, '10 minutes left'], [300, '5 minutes left'], [60, '1 minute left']].forEach(function (w) {
      if (left <= w[0] && left > w[0] - 5 && !warned[w[0]]) { warned[w[0]] = true; toast(w[1]); }
    });

    if (left <= 0) { clearInterval(clock); submit(true); }
  }

  var clock = setInterval(tick, 1000);

  // ---------------------------------------------------------------- wiring

  el.next.addEventListener('click', next);
  el.prev.addEventListener('click', prev);
  el.flag.addEventListener('click', toggleFlag);
  el.submitSide.addEventListener('click', function () { submit(false); });

  document.addEventListener('keydown', function (e) {
    if (state.submitting || e.ctrlKey || e.metaKey || e.altKey) { return; }
    if (e.target && /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) { return; }

    var key = e.key.toUpperCase();
    var idx = LETTERS.indexOf(key);
    if (idx > -1 && Q[state.i].options[LETTERS[idx]] !== undefined) { choose(LETTERS[idx]); e.preventDefault(); }
    else if (key === 'F') { toggleFlag(); e.preventDefault(); }
    else if (e.key === 'ArrowRight') { next(); e.preventDefault(); }
    else if (e.key === 'ArrowLeft') { prev(); e.preventDefault(); }
  });

  // Load maths rendering only when the questions contain LaTeX.
  if (/\\\(|\\\[|\$\$/.test(JSON.stringify(Q) + JSON.stringify(P))) {
    window.MathJax = { tex: { inlineMath: [['\\(', '\\)']], displayMath: [['\\[', '\\]'], ['$$', '$$']] }, startup: { typeset: false } };
    var s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js';
    s.async = true;
    s.onload = function () { render(); };
    document.head.appendChild(s);
  }

  tick();
  render();
  if (state.dirty) { save(false); }   // push newer local answers up straight away
})();
