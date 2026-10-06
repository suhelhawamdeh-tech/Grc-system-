// Progressive enhancement. The application works without JavaScript; every button below is also a normal link.
(function () {
  document.documentElement.classList.add('js');
  var btn = document.getElementById('menu-toggle');
  var nav = document.getElementById('sidenav');
  if (btn && nav) {
    var mq = window.matchMedia('(max-width: 860px)');
    var sync = function () { nav.hidden = mq.matches; btn.setAttribute('aria-expanded', String(!mq.matches)); };
    sync();
    mq.addEventListener('change', sync);
    btn.addEventListener('click', function () {
      nav.hidden = !nav.hidden;
      btn.setAttribute('aria-expanded', String(!nav.hidden));
    });
  }
  document.querySelectorAll('[data-print]').forEach(function (b) {
    b.addEventListener('click', function () { window.print(); });
  });
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); } });
  });

  // Slide-over panel: contextual help and quick notes without leaving the page.
  var panel = document.getElementById('side-panel');
  var body = panel && panel.querySelector('[data-panel-body]');
  var opener = null;
  var base = (panel && panel.getAttribute('data-base')) || '';
  function closePanel() {
    if (!panel || panel.hidden) { return; }
    panel.hidden = true;
    if (opener) { opener.focus(); }
  }
  function openPanel(kind, trigger) {
    var path = location.pathname.slice(base.length) || '/';
    var url = base + '/' + (kind === 'notes' ? 'notes/panel' : 'help/panel?path=' + encodeURIComponent(path));
    opener = trigger;
    fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.text(); })
      .then(function (html) {
        body.innerHTML = html;
        body.querySelectorAll('[data-current-path]').forEach(function (i) { i.value = location.pathname.slice(base.length) + location.search; });
        panel.hidden = false;
        panel.focus();
      })
      .catch(function () { window.location.href = trigger.href; });
  }
  if (panel && body) {
    document.querySelectorAll('[data-panel]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        if (e.ctrlKey || e.metaKey || e.shiftKey) { return; }
        e.preventDefault();
        if (!panel.hidden && opener === a) { closePanel(); } else { openPanel(a.getAttribute('data-panel'), a); }
      });
    });
    panel.querySelector('[data-panel-close]').addEventListener('click', closePanel);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closePanel(); } });
  }

  // Instant filter for help cards
  document.querySelectorAll('[data-filter-target]').forEach(function (inp) {
    inp.addEventListener('input', function () {
      var q = inp.value.trim().toLowerCase();
      document.querySelectorAll(inp.getAttribute('data-filter-target')).forEach(function (c) {
        c.hidden = q !== '' && c.textContent.toLowerCase().indexOf(q) === -1;
      });
    });
  });
})();
