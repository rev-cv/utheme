
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
});
