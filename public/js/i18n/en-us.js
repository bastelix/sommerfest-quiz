/* global UIkit */
(function(UIkit){
  const i18n = {
    close: { label: 'Close' },
    totop: { label: 'To top' },
    marker: { label: 'Open' },
    navbarToggleIcon: { label: 'Open menu' },
    paginationPrevious: { label: 'Previous page' },
    paginationNext: { label: 'Next page' },
    slider: {
      next: 'Next slide',
      previous: 'Previous slide',
      slideX: 'Slide %s',
      slideLabel: '%s of %s'
    },
    slideshow: {
      next: 'Next slide',
      previous: 'Previous slide',
      slideX: 'Slide %s',
      slideLabel: '%s of %s'
    },
    lightboxPanel: {
      next: 'Next slide',
      previous: 'Previous slide',
      slideLabel: '%s of %s',
      close: 'Close'
    }
  };

  for (const component in i18n) {
    UIkit.mixin({ i18n: i18n[component] }, component);
  }
})(UIkit);
