document.addEventListener('DOMContentLoaded', function () {
    const toc = document.querySelectorAll('.toc');
    if (toc.length === 0) {
        return
    }

    const style = document.createElement('style');
    style.textContent = `
        h2[id] {
            scroll-margin-top: 100px;
        }
        @media (min-width: 851px) {
            article img[loading="lazy"]:not([width]):not([height]) {
                min-height: 551px;
                background: rgba(0,0,0,0.03);
            }
            article figure img[loading="lazy"] {
                min-height: 551px;
            }
        }
    `;
    document.head.appendChild(style);

    const scrollToTopBtn = document.createElement('button');
    scrollToTopBtn.id = 'scrollToTopBtn';
    scrollToTopBtn.setAttribute('aria-label', 'Scroll to top');

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

    const tocLinks = document.querySelectorAll('.page-toc-list a');
    tocLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            const rawHref = this.getAttribute('href');
            if (!rawHref || !rawHref.startsWith('#')) return;

            let id;
            try { id = decodeURIComponent(rawHref.slice(1)); } catch (_) { id = rawHref.slice(1); }
            const targetElement = document.getElementById(id);
            if (!targetElement) return;

            targetElement.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });

            e.preventDefault();
        });
    });

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