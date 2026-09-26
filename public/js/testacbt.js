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

    var confirmer = e.target.closest('[data-confirm]');
    if (confirmer && !window.confirm(confirmer.getAttribute('data-confirm'))) { e.preventDefault(); }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { document.querySelectorAll('.usermenu.open').forEach(function (m) { m.classList.remove('open'); }); }
  });

  syncThemeButtons();
})();
