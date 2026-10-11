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

  // The sticky site header (and the admin bar when logged in) covers the top
  // of a section after an in-page jump. Keep anchor targets below them.
  function updateScrollOffset() {
    var offset = 0;
    var adminBar = document.getElementById('wpadminbar');
    if (adminBar && getComputedStyle(adminBar).position === 'fixed') {
      offset += adminBar.offsetHeight;
    }
    var headers = document.querySelectorAll('header, .site-header, #masthead');
    for (var i = 0; i < headers.length; i++) {
      // The theme may make the header itself sticky, or a wrapper around it.
      var el = headers[i];
      var stuck = null;
      for (var depth = 0; el && el !== document.body && depth < 3; depth++) {
        var pos = getComputedStyle(el).position;
        if (pos === 'sticky' || pos === 'fixed') { stuck = el; break; }
        el = el.parentElement;
      }
      if (stuck) {
        offset += stuck.offsetHeight;
        break;
      }
    }
    document.documentElement.style.scrollPaddingTop = offset ? (offset + 16) + 'px' : '';
  }
  updateScrollOffset();
  window.addEventListener('resize', updateScrollOffset);

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
