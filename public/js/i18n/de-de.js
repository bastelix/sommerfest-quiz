/* global UIkit */
(function(UIkit){
  const i18n = {
    close: { label: 'Schließen' },
    totop: { label: 'Nach oben' },
    marker: { label: 'Öffnen' },
    navbarToggleIcon: { label: 'Menü öffnen' },
    paginationPrevious: { label: 'Zurück' },
    paginationNext: { label: 'Weiter' },
    slider: {
      next: 'Weiter',
      previous: 'Zurück',
      slideX: 'Folie %s',
      slideLabel: '%s von %s'
    },
    slideshow: {
      next: 'Weiter',
      previous: 'Zurück',
      slideX: 'Folie %s',
      slideLabel: '%s von %s'
    },
    lightboxPanel: {
      next: 'Weiter',
      previous: 'Zurück',
      slideLabel: '%s von %s',
      close: 'Schließen'
    }
  };

  for (const component in i18n) {
    UIkit.mixin({ i18n: i18n[component] }, component);
  }
})(UIkit);
