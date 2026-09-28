/* TestaCBT web: theme, menus and small helpers. No framework. */
(function () {
  'use strict';

  var root = document.documentElement;

  function storedTheme() {
    try { return localStorage.getItem('tc-theme'); } catch (e) { return null; }
  }

  function applyTheme(value) {
    if (value === 'light' || value === 'dark') { root.setAttribute('data-theme', value); }
    else { root.removeAttribute('data-theme'); }
    try { value ? localStorage.setItem('tc-theme', value) : localStorage.removeItem('tc-theme'); } catch (e) {}
    syncThemeButtons();
  }

  // A group with a "System" button shows what is chosen (system, light or dark);
  // a group with only Light/Dark shows the theme currently in effect.
  function syncThemeButtons() {
    var explicit = storedTheme();
    var effective = explicit || (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

    document.querySelectorAll('[data-theme-group]').forEach(function (group) {
      var hasSystem = !!group.querySelector('[data-theme-val="system"]');
      group.querySelectorAll('[data-theme-val]').forEach(function (b) {
        var v = b.getAttribute('data-theme-val');
        b.classList.toggle('on', hasSystem ? (v === 'system' ? !explicit : v === explicit) : v === effective);
      });
    });
  }

  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-theme-val]');
    if (t) { applyTheme(t.getAttribute('data-theme-val') === 'system' ? null : t.getAttribute('data-theme-val')); return; }

    var toggle = e.target.closest('[data-menu-toggle]');
    document.querySelectorAll('.usermenu.open').forEach(function (m) { if (!toggle || !m.contains(toggle)) { m.classList.remove('open'); } });
    if (toggle) { toggle.closest('.usermenu').classList.toggle('open'); }

    var play = e.target.closest('.video-play');
    if (play) { loadVideo(play.closest('.video-frame')); return; }

    var copier = e.target.closest('[data-copy]');
    if (copier) { copyText(copier.getAttribute('data-copy'), copier); return; }

    var sharer = e.target.closest('[data-native-share]');
    if (sharer && navigator.share) {
      navigator.share({ title: sharer.getAttribute('data-title'), url: sharer.getAttribute('data-url') }).catch(function () {});
      return;
    }

    var confirmer = e.target.closest('[data-confirm]');
    if (confirmer && !window.confirm(confirmer.getAttribute('data-confirm'))) { e.preventDefault(); }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { document.querySelectorAll('.usermenu.open').forEach(function (m) { m.classList.remove('open'); }); }
  });

  // Videos load only when played: the page stays fast and no other site is contacted before then.
  // The address comes from the server (built from the video id for YouTube, Facebook, X or TikTok only).
  // Every card is the same size. Wide videos (YouTube, Facebook) play inside the card; TikTok and X posts are not wide,
  // so they play in a popup shaped to fit them.
  function loadVideo(frame) {
    if (!frame) { return; }
    var src = frame.getAttribute('data-embed') || '';
    if (!/^https:\/\/(www\.youtube-nocookie\.com|www\.facebook\.com|platform\.twitter\.com|www\.tiktok\.com)\//.test(src)) { return; }
    var iframe = buildPlayer(src, frame.getAttribute('data-title'));
    var shape = frame.getAttribute('data-modal');
    if (shape) { openVideoDialog(iframe, shape); return; }
    if (frame.querySelector('iframe')) { return; }
    frame.innerHTML = '';
    frame.appendChild(iframe);
  }

  function buildPlayer(src, title) {
    var iframe = document.createElement('iframe');
    iframe.src = src;
    iframe.title = title || 'Video';
    iframe.loading = 'lazy';
    iframe.allow = 'autoplay; encrypted-media; fullscreen; picture-in-picture; clipboard-write';
    iframe.allowFullscreen = true;
    iframe.referrerPolicy = 'strict-origin-when-cross-origin';
    iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-presentation allow-popups allow-popups-to-escape-sandbox');
    return iframe;
  }

  function openVideoDialog(iframe, shape) {
    var dialog = document.getElementById('video-dialog');
    if (!dialog) {
      dialog = document.createElement('dialog');
      dialog.id = 'video-dialog';
      dialog.className = 'video-dialog';
      dialog.innerHTML = '<button type="button" class="video-close" aria-label="Close video">&times;</button><div class="video-stage"></div>';
      dialog.addEventListener('click', function (e) { if (e.target === dialog || e.target.closest('.video-close')) { dialog.close(); } });
      dialog.addEventListener('close', function () { dialog.querySelector('.video-stage').innerHTML = ''; });
      document.body.appendChild(dialog);
    }
    dialog.classList.toggle('post', shape === 'post');
    var stage = dialog.querySelector('.video-stage');
    stage.innerHTML = '';
    stage.appendChild(iframe);
    if (typeof dialog.showModal === 'function') { dialog.showModal(); } else { dialog.setAttribute('open', ''); }
  }

  // Copy a link and say so. Falls back to a prompt where the clipboard is blocked (older browsers, plain http).
  function copyText(text, button) {
    function done() {
      var old = button.getAttribute('data-label') || button.textContent.trim();
      button.setAttribute('data-label', old);
      button.classList.add('done');
      button.lastChild.nodeType === 3 ? (button.lastChild.textContent = 'Copied!') : null;
      setTimeout(function () { button.classList.remove('done'); button.lastChild.nodeType === 3 ? (button.lastChild.textContent = old) : null; }, 2000);
    }
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done, function () { window.prompt('Copy this link:', text); });
    } else {
      var ta = document.createElement('textarea');
      ta.value = text; ta.setAttribute('readonly', ''); ta.style.position = 'fixed'; ta.style.opacity = '0';
      document.body.appendChild(ta); ta.select();
      var ok = false;
      try { ok = document.execCommand('copy'); } catch (err) {}
      document.body.removeChild(ta);
      ok ? done() : window.prompt('Copy this link:', text);
    }
  }

  // Phones that can share natively get a "More" button (Instagram, Telegram, SMS and so on).
  if (navigator.share) {
    document.querySelectorAll('[data-native-share]').forEach(function (b) { b.hidden = false; });
  }

  // "How to install" links open their instructions.
  function openInstallHelp(id) {
    var el = id && document.getElementById(id.replace('#', ''));
    if (el && el.tagName === 'DETAILS') { el.open = true; }
  }
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href^="#install-"]');
    if (a) { openInstallHelp(a.getAttribute('href')); }
  });
  openInstallHelp(location.hash);

  syncThemeButtons();
})();
