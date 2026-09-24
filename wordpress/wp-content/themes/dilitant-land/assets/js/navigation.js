document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.querySelector('.site-menu-toggle');
    const panel = document.querySelector('.site-menu-panel');
    const backdrop = document.querySelector('.site-menu-backdrop');
    const closeButton = document.querySelector('.site-menu-close');

    if (!toggle || !panel || !backdrop || !closeButton) {
        return;
    }

    function openMenu() {
        panel.classList.add('is-open');
        backdrop.classList.add('is-open');
        document.body.classList.add('site-menu-open');

        toggle.setAttribute('aria-expanded', 'true');
        panel.setAttribute('aria-hidden', 'false');

        closeButton.focus();
    }

    function closeMenu() {
        panel.classList.remove('is-open');
        backdrop.classList.remove('is-open');
        document.body.classList.remove('site-menu-open');

        toggle.setAttribute('aria-expanded', 'false');
        panel.setAttribute('aria-hidden', 'true');

        toggle.focus();
    }

    toggle.addEventListener('click', openMenu);
    closeButton.addEventListener('click', closeMenu);
    backdrop.addEventListener('click', closeMenu);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && panel.classList.contains('is-open')) {
            closeMenu();
        }
    });

    panel.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', closeMenu);
    });
});