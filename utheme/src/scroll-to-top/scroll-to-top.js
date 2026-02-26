document.addEventListener('DOMContentLoaded', function () {
    // Добавляем CSS для корректной работы якорей и изображений

    const toc = document.querySelectorAll('.toc');
    if (toc.length === 0) {
        return
    }

    const style = document.createElement('style');
    style.textContent = `
        h2[id] {
            scroll-margin-top: 90px;
        }
        
        /* Резервируем место для lazy-изображений ТОЛЬКО на десктопах ( > 850px) */
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

    // Обработка кликов по TOC (оглавлению)
    const tocLinks = document.querySelectorAll('.page-toc-list a');
    tocLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            const targetId = this.getAttribute('href');
            if (!targetId || !targetId.startsWith('#')) return;

            const targetElement = document.querySelector(targetId);
            if (!targetElement) return;

            // scroll-margin-top в CSS учтёт отступ
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