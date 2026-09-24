(function () {
    const slides = Array.from(document.querySelectorAll('.hero-slide'));

    if (!slides.length) {
        return;
    }

    const sceneTime = 8200;
    const transitionTime = 700;

    let currentIndex = slides.findIndex((slide) => slide.classList.contains('is-active'));

    if (currentIndex < 0) {
        currentIndex = 0;
    }

    slides.forEach((slide, index) => {
        slide.classList.remove('is-active', 'is-leaving');
        slide.style.zIndex = index === currentIndex ? '2' : '1';
    });

    slides[currentIndex].classList.add('is-active');

    function showNextSlide() {
        const previousSlide = slides[currentIndex];

        currentIndex = (currentIndex + 1) % slides.length;

        const nextSlide = slides[currentIndex];

        previousSlide.classList.add('is-leaving');
        previousSlide.classList.remove('is-active');
        previousSlide.style.zIndex = '2';

        nextSlide.classList.add('is-active');
        nextSlide.classList.remove('is-leaving');
        nextSlide.style.zIndex = '3';

        window.setTimeout(() => {
            previousSlide.classList.remove('is-leaving');
            previousSlide.style.zIndex = '1';
            nextSlide.style.zIndex = '2';
        }, transitionTime);
    }

    window.setInterval(showNextSlide, sceneTime);
})();