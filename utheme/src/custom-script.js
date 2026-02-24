
document.addEventListener('DOMContentLoaded', function () {
  const scrollToTopBtn = document.getElementById('scrollToTopBtn');

  const toggleButtonVisibility = () => {
    const scrollThreshold = window.innerHeight * 0.8;
    if (window.scrollY > scrollThreshold) {
      if (!scrollToTopBtn.classList.contains('show')) {
        scrollToTopBtn.classList.add('show');
      }
    } else {
      if (scrollToTopBtn.classList.contains('show')) {
        scrollToTopBtn.classList.remove('show');
      }
    }
  };

  const scrollToTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  window.addEventListener('scroll', toggleButtonVisibility);
  scrollToTopBtn.addEventListener('click', scrollToTop);

  // --- FIX: Lazy Load vs Anchor Scroll ---

  const tocLinks = document.querySelectorAll('.page-toc-list a');
  tocLinks.forEach(link => {
    link.addEventListener('click', () => {
      document.querySelectorAll('img[loading="lazy"]').forEach(img => img.setAttribute('loading', 'eager'));
    });
  });

  window.addEventListener('load', () => {
    setTimeout(() => {
      document.querySelectorAll('img[loading="lazy"]').forEach(img => img.setAttribute('loading', 'eager'));
    }, 3000);
  });
});
