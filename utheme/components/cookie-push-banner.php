<?php if (!utheme_cookie_push_should_render()) return; ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const banner = document.getElementById('ut-cookie-push');
        if (!banner) return;

        const legacy = localStorage.getItem('cookie_consent_status');
        if (legacy) {
            setConsent(legacy);
            banner.remove();
            return;
        }

        const acceptBtn = banner.querySelector('.ut-cookie__btn--accept');
        const rejectBtn = banner.querySelector('.ut-cookie__btn--reject');
        const header    = document.querySelector('header');
        const baseTop   = header ? parseFloat(header.getAttribute('data-ut-cookie-base-top') || '0') : 0;

        document.body.style.transition = 'padding-top 0.35s ease';
        if (header) header.style.transition = 'top 0.35s ease';

        let resizeTimer = null;
        function reapply() {
            const h = banner.offsetHeight;
            document.body.style.paddingTop = h + 'px';
            if (header) header.style.top = (baseTop + h) + 'px';
        }
        function onResize() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(reapply, 100);
        }
        window.addEventListener('resize', onResize);

        function setConsent(value) {
            const maxAge = 180 * 24 * 60 * 60;
            document.cookie = 'cookie_consent_status=' + value + '; path=/; max-age=' + maxAge +
                '; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
        }

        function closeBanner() {
            window.removeEventListener('resize', onResize);
            clearTimeout(resizeTimer);

            banner.style.transform = 'translateY(-100%)';
            document.body.style.paddingTop = '0px';
            if (header) header.style.top = baseTop + 'px';

            banner.addEventListener('transitionend', function onEnd(e) {
                if (e.target !== banner || e.propertyName !== 'transform') return;
                banner.removeEventListener('transitionend', onEnd);
                banner.remove();
                document.body.style.paddingTop = '';
                document.body.style.transition = '';
                if (header) {
                    header.style.removeProperty('top');
                    header.style.removeProperty('transition');
                    header.removeAttribute('data-ut-cookie-base-top');
                }
            });
        }

        acceptBtn.addEventListener('click', function() { setConsent('accepted'); closeBanner(); });
        rejectBtn.addEventListener('click', function() { setConsent('rejected'); closeBanner(); });
    });
</script>
