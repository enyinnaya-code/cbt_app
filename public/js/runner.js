/* TestaCBT question runner: Practice (and, later, Mock). Vanilla JS.
   Reads its questions from <script id="session-data">. Answers are sent to the server as the student goes,
   and kept in localStorage if the network drops so nothing is lost. */
(function () {
  'use strict';

  var dataEl = document.getElementById('session-data');
  if (!dataEl) { return; }

  var S = JSON.parse(dataEl.textContent);
  var Q = S.questions;
  var P = S.passages || {};
  var N = Q.length;
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var LETTERS = ['A', 'B', 'C', 'D', 'E'];
  var INSTANT = S.mode === 'instant';

  var state = { i: 0, answers: {}, revealed: {}, shownAt: {}, review: false, finished: false, lang: S.lang || 'en' };

  var $ = function (id) { return document.getElementById(id); };
  var el = {
    count: $('q-count'), topic: $('q-topic'), bar: $('q-bar'), passage: $('q-passage'), text: $('q-text'),
    options: $('q-options'), feedback: $('q-feedback'), explain: $('q-explain'), sheet: $('sheet'), legend: $('legend'),
    note: $('sheet-note'), prev: $('btn-prev'), next: $('btn-next'), mark: $('btn-bookmark'), results: $('results'),
    runner: $('runner'), foot: $('foot')
  };

  var ICON = {
    check: '<svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg>',
    x: '<svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>'
  };

  // ---------------------------------------------------------------- helpers

  function uuid() {
    if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16);
    });
  }

  function h(tag, cls, html) {
    var n = document.createElement(tag);
    if (cls) { n.className = cls; }
    if (html != null) { n.innerHTML = html; }
    return n;
  }

  function q() { return Q[state.i]; }

  function isCorrect(question) { return state.answers[question.id] === question.answer; }

  function showsAnswer(question) { return state.review || (INSTANT && state.revealed[question.id]); }

  function toast(message) {
    var t = h('div', 'toast', null);
    t.textContent = message;
    t.setAttribute('role', 'status');
    document.body.appendChild(t);
    setTimeout(function () { t.remove(); }, 2600);
  }

  // ---------------------------------------------------------------- sending answers

  var QKEY = 'tc-attempt-queue';
  var queue = [];
  var flushing = false;

  try { queue = JSON.parse(localStorage.getItem(QKEY) || '[]') || []; } catch (e) { queue = []; }

  function saveQueue() { try { localStorage.setItem(QKEY, JSON.stringify(queue)); } catch (e) {} }

  function enqueue(question, selected) {
    queue.push({
      client_uuid: uuid(),
      question_id: question.id,
      mode: 'practice',
      selected: selected,
      time_ms: Math.min(3600000, Date.now() - (state.shownAt[question.id] || Date.now())),
      answered_at: new Date().toISOString()
    });
    saveQueue();
    flush();
  }

  function flush() {
    if (flushing || !queue.length || !S.endpoints || !S.endpoints.attempts) { return; }
    flushing = true;
    var batch = queue.slice(0, 50);

    fetch(S.endpoints.attempts, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ attempts: batch })
    }).then(function (r) {
      // 422 means the server will never accept this batch; drop it rather than retry forever.
      if (r.ok || r.status === 422) { queue.splice(0, batch.length); saveQueue(); return true; }
      return false;
    }).catch(function () { return false; }).then(function (ok) {
      flushing = false;
      if (ok && queue.length) { flush(); }
    });
  }

  window.addEventListener('online', flush);
  flush();   // anything left over from an earlier session

  // ---------------------------------------------------------------- rendering

  function typeset(node) {
    if (window.MathJax && MathJax.typesetPromise) { MathJax.typesetPromise([node]).catch(function () {}); }
  }

  function needsMaths() {
    var s = JSON.stringify(Q) + JSON.stringify(P);
    return /\\\(|\\\[|\$\$/.test(s);
  }

  function loadMaths() {
    if (!needsMaths() || window.MathJax) { return; }
    window.MathJax = { tex: { inlineMath: [['\\(', '\\)']], displayMath: [['\\[', '\\]'], ['$$', '$$']] }, startup: { typeset: false } };
    var s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js';
    s.async = true;
    s.onload = function () { typeset(document.getElementById('runner')); };
    document.head.appendChild(s);
  }

  function render() {
    var question = q();
    if (!state.shownAt[question.id]) { state.shownAt[question.id] = Date.now(); }

    el.count.textContent = 'Question ' + (state.i + 1) + ' of ' + N;
    el.topic.textContent = question.topic || (question.year ? String(question.year) : '');
    el.bar.style.width = ((state.i + 1) / N * 100) + '%';

    var passage = question.passage_id ? P[question.passage_id] : null;
    if (passage) { el.passage.innerHTML = '<p class="h3" style="margin-bottom:8px">Read this first</p>' + passage; el.passage.hidden = false; }
    else { el.passage.hidden = true; el.passage.innerHTML = ''; }

    el.text.innerHTML = question.html;
    renderOptions(question);
    renderFeedback(question);
    renderSheet();
    renderBookmark(question);

    el.prev.disabled = state.i === 0;
    el.next.textContent = state.review
      ? (state.i < N - 1 ? 'Next question' : 'Back to results')
      : (state.i < N - 1 ? 'Next question' : 'Finish');

    typeset(document.getElementById('runner'));
  }

  function renderOptions(question) {
    el.options.innerHTML = '';
    var reveal = showsAnswer(question);
    var chosen = state.answers[question.id];

    Object.keys(question.options).sort().forEach(function (k) {
      var b = h('button', 'opt');
      b.type = 'button';
      b.dataset.k = k;
      b.setAttribute('aria-pressed', chosen === k ? 'true' : 'false');
      b.appendChild(h('span', 'bub', k));
      b.appendChild(h('span', null, question.options[k]));

      if (reveal) {
        b.disabled = true;
        if (k === question.answer) { b.classList.add('correct'); b.appendChild(h('span', 'end', ICON.check)); }
        else if (k === chosen) { b.classList.add('wrong'); b.appendChild(h('span', 'end', ICON.x)); }
      } else if (chosen === k) {
        b.classList.add('sel');
      }
      b.addEventListener('click', function () { choose(k); });
      el.options.appendChild(b);
    });
  }

  function renderFeedback(question) {
    var reveal = showsAnswer(question);
    el.feedback.hidden = !reveal;
    el.explain.hidden = true;
    if (!reveal) { return; }

    var chosen = state.answers[question.id];
    var ok = chosen === question.answer;
    el.feedback.className = 'alert ' + (ok ? 'ok' : (chosen ? 'err' : 'info'));
    el.feedback.textContent = ok ? 'Correct!' : (chosen ? 'Not quite. The correct answer is ' + question.answer + '.' : 'You skipped this one. The correct answer is ' + question.answer + '.');

    var en = question.explanation_en, pcm = question.explanation_pcm;
    if (!en && !pcm) { return; }

    var lang = state.lang === 'pcm' ? (pcm ? 'pcm' : 'en') : (en ? 'en' : 'pcm');
    el.explain.innerHTML = '';
    var head = h('div', 'row between');
    head.appendChild(h('p', 'h3', 'Explanation'));
    if (en && pcm) {
      var seg = h('div', 'seg');
      seg.style.padding = '3px';
      [['en', 'English'], ['pcm', 'Pidgin']].forEach(function (pair) {
        var b = h('button', lang === pair[0] ? 'on' : '', pair[1]);
        b.type = 'button';
        b.style.height = '30px';
        b.addEventListener('click', function () { state.lang = pair[0]; renderFeedback(question); });
        seg.appendChild(b);
      });
      head.appendChild(seg);
    }
    el.explain.appendChild(head);
    el.explain.appendChild(h('div', 'prose small', lang === 'pcm' ? pcm : en));
    el.explain.hidden = false;
  }

  function renderSheet() {
    el.sheet.innerHTML = '';
    var answered = 0;

    Q.forEach(function (question, idx) {
      var b = h('button', 'n', String(idx + 1));
      b.type = 'button';
      b.setAttribute('aria-label', 'Go to question ' + (idx + 1));
      var a = state.answers[question.id];
      if (a) { answered++; }

      if (showsAnswer(question) && a) { b.classList.add(a === question.answer ? 'right' : 'bad'); }
      else if (a) { b.classList.add('a'); }
      if (idx === state.i) { b.classList.add('cur'); }

      b.addEventListener('click', function () { state.i = idx; render(); });
      el.sheet.appendChild(b);
    });

    el.note.textContent = answered + ' of ' + N + ' answered';

    el.legend.innerHTML = state.review || INSTANT
      ? '<span><b style="background:var(--primary);border-color:var(--primary)"></b>Right</span><span><b style="background:var(--danger);border-color:var(--danger)"></b>Wrong</span><span><b></b>Not answered</span>'
      : '<span><b style="background:var(--text);border-color:var(--text)"></b>Answered</span><span><b></b>Not answered</span>';
  }

  function renderBookmark(question) {
    var on = !!question.bookmarked;
    el.mark.setAttribute('aria-pressed', on ? 'true' : 'false');
    el.mark.style.color = on ? 'var(--accent)' : '';
    el.mark.querySelector('svg').style.fill = on ? 'currentColor' : 'none';
  }

  // ---------------------------------------------------------------- actions

  function choose(k) {
    var question = q();
    if (showsAnswer(question) || state.finished) { return; }

    state.answers[question.id] = k;

    if (INSTANT) {
      state.revealed[question.id] = true;
      enqueue(question, k);
    }
    render();
  }

  function goNext() {
    if (state.review) {
      if (state.i < N - 1) { state.i++; render(); } else { showResults(); }
      return;
    }
    if (state.i < N - 1) { state.i++; render(); return; }
    finish();
  }

  function goPrev() { if (state.i > 0) { state.i--; render(); } }

  function finish() {
    var unanswered = Q.filter(function (x) { return !state.answers[x.id]; }).length;
    if (unanswered && !window.confirm('You have ' + unanswered + ' unanswered ' + (unanswered === 1 ? 'question' : 'questions') + '. Finish anyway?')) { return; }

    if (!INSTANT) {
      Q.forEach(function (question) { if (state.answers[question.id]) { enqueue(question, state.answers[question.id]); } });
    }
    state.finished = true;
    showResults();
  }

  function showResults() {
    var total = N;
    var right = Q.filter(isCorrect).length;
    var answered = Q.filter(function (x) { return state.answers[x.id]; }).length;
    var pct = total ? Math.round(right / total * 100) : 0;
    var circ = 2 * Math.PI * 58;

    el.runner.hidden = true;
    el.foot.hidden = true;
    el.mark.hidden = true;
    el.results.hidden = false;
    el.results.innerHTML =
      '<div class="row between"><p class="h2">Your result</p></div>' +
      '<div class="col" style="align-items:center;gap:10px;padding:6px 0">' +
        '<svg width="170" height="170" viewBox="0 0 140 140" role="img" aria-label="' + right + ' out of ' + total + ' correct">' +
          '<circle cx="70" cy="70" r="58" fill="none" stroke="var(--surface-2)" stroke-width="12"/>' +
          '<circle cx="70" cy="70" r="58" fill="none" stroke="var(--primary)" stroke-width="12" stroke-linecap="round" stroke-dasharray="' + (circ * pct / 100).toFixed(1) + ' ' + circ.toFixed(1) + '" transform="rotate(-90 70 70)"/>' +
          '<text x="70" y="70" text-anchor="middle" font-family="Bricolage Grotesque, sans-serif" font-weight="800" font-size="34" fill="var(--text)">' + right + '</text>' +
          '<text x="70" y="92" text-anchor="middle" font-family="Figtree, sans-serif" font-size="12" fill="var(--muted)">out of ' + total + '</text>' +
        '</svg>' +
        '<span class="badge g">' + pct + '% correct</span>' +
      '</div>' +
      '<div class="grid3"><div class="stat"><span class="tiny muted">Correct</span><span class="h3">' + right + '</span></div>' +
      '<div class="stat"><span class="tiny muted">Wrong</span><span class="h3">' + (answered - right) + '</span></div>' +
      '<div class="stat"><span class="tiny muted">Skipped</span><span class="h3">' + (total - answered) + '</span></div></div>' +
      '<div class="row wrap" style="justify-content:center">' +
        '<button class="btn btn-o" id="btn-review" type="button">Review answers</button>' +
        '<a class="btn btn-p" href="' + S.endpoints.exit + '">Practise again</a>' +
        '<a class="btn btn-t" href="' + S.endpoints.home + '">Home</a>' +
      '</div>';

    $('btn-review').addEventListener('click', function () {
      state.review = true; state.i = 0;
      el.results.hidden = true; el.runner.hidden = false; el.foot.hidden = false; el.mark.hidden = false;
      render();
    });
    window.scrollTo(0, 0);
  }

  function toggleBookmark() {
    var question = q();
    var want = !question.bookmarked;
    question.bookmarked = want;
    renderBookmark(question);

    fetch(S.endpoints.toggle, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ question_id: question.id, bookmarked: want })
    }).then(function (r) {
      if (!r.ok) { throw new Error('failed'); }
      toast(want ? 'Saved. Find it under Saved.' : 'Removed from saved.');
    }).catch(function () {
      question.bookmarked = !want;   // put it back
      renderBookmark(question);
      toast('Could not save. Check your connection.');
    });
  }

  // ---------------------------------------------------------------- wiring

  el.next.addEventListener('click', goNext);
  el.prev.addEventListener('click', goPrev);
  el.mark.addEventListener('click', toggleBookmark);

  document.addEventListener('keydown', function (e) {
    if (state.finished && !state.review) { return; }
    if (e.target && /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) { return; }
    if (e.ctrlKey || e.metaKey || e.altKey) { return; }

    var key = e.key.toUpperCase();
    var idx = LETTERS.indexOf(key);
    if (idx === -1 && /^[1-5]$/.test(key)) { idx = parseInt(key, 10) - 1; }

    if (idx > -1 && q().options[LETTERS[idx]] !== undefined) { choose(LETTERS[idx]); e.preventDefault(); }
    else if (e.key === 'ArrowRight' || e.key === 'Enter') { if (e.target.tagName !== 'BUTTON' || e.key === 'ArrowRight') { goNext(); e.preventDefault(); } }
    else if (e.key === 'ArrowLeft') { goPrev(); e.preventDefault(); }
  });

  window.addEventListener('beforeunload', function (e) {
    var started = Object.keys(state.answers).length > 0;
    if (!INSTANT && started && !state.finished) { e.preventDefault(); e.returnValue = ''; }
  });

  loadMaths();
  render();
})();
