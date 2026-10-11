(function () {
  'use strict';

  // "Skip to content" points at #primary. If the theme has no element with
  // that id, give it to the main content area so the skip link works.
  if (!document.getElementById('primary')) {
    var main = document.querySelector('main, [role="main"], #content, .site-main');
    if (main) {
      main.id = 'primary';
      if (!main.hasAttribute('tabindex')) main.setAttribute('tabindex', '-1');
    }
  }

  // In-page links whose target is missing scroll nowhere. Send them to a
  // real page instead.
  var fallbacks = {
    'ffnc-how-it-works': '/methodology/'
  };
  Object.keys(fallbacks).forEach(function (id) {
    if (document.getElementById(id)) return;
    document.querySelectorAll('a[href="#' + id + '"]').forEach(function (link) {
      link.setAttribute('href', fallbacks[id]);
    });
  });
})();
