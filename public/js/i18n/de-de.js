/* global UIkit */
(function(UIkit){
  const i18n = {
    close: { label: 'Schließen' },
    totop: { label: 'Nach oben' },
    marker: { label: 'Öffnen' },
    navbarToggleIcon: { label: 'Menü öffnen' },
    paginationPrevious: { label: 'Vorherige Seite' },
    paginationNext: { label: 'Nächste Seite' },
    slider: {
      next: 'Nächste Folie',
      previous: 'Vorherige Folie',
      slideX: 'Folie %s',
      slideLabel: '%s von %s'
    },
    slideshow: {
      next: 'Nächste Folie',
      previous: 'Vorherige Folie',
      slideX: 'Folie %s',
      slideLabel: '%s von %s'
    },
    lightboxPanel: {
      next: 'Nächste Folie',
      previous: 'Vorherige Folie',
      slideLabel: '%s von %s',
      close: 'Schließen'
    }
  };

  for (const component in i18n) {
    UIkit.mixin({ i18n: i18n[component] }, component);
  }
})(UIkit);
