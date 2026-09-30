// Minimal progressive enhancement. The application works without JavaScript.
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
})();
