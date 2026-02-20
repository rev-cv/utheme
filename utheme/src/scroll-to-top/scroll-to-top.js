document.addEventListener('DOMContentLoaded', function () {
    const scrollToTopBtn = document.createElement('button');
    scrollToTopBtn.id = 'scrollToTopBtn';
    scrollToTopBtn.title = 'Вернуться наверх';
    scrollToTopBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M9 23v-3H4l8-9l8 9h-5v3zm-5-8l8-9l8 9h-2.675L12 9l-5.325 6zm0-5l8-9l8 9h-2.675L12 4l-5.325 6z"/></svg>`;

    document.body.appendChild(scrollToTopBtn);

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
        const tocElement = document.querySelector('.toc');

        if (tocElement) {
            tocElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    };

    window.addEventListener('scroll', toggleButtonVisibility);
    scrollToTopBtn.addEventListener('click', scrollToTop);
});