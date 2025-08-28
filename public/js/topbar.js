document.addEventListener('DOMContentLoaded', function () {
  const topbar = document.querySelector('.topbar');
  const navPlaceholder = document.querySelector('.nav-placeholder');

  function updateNavPlaceholder() {
    let height = topbar.offsetHeight;
    const headerBar = document.querySelector('.event-header-bar');
    if (headerBar) {
      height += headerBar.offsetHeight;
    }
    navPlaceholder.style.height = height + 'px';
  }

  if (topbar && navPlaceholder) {
    updateNavPlaceholder();
    if (window.getComputedStyle(topbar).flexWrap !== 'nowrap') {
      window.addEventListener('resize', updateNavPlaceholder);
    }
  }
});
