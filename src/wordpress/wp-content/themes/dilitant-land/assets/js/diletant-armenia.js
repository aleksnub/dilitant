/**
 * Карусели страницы «Дилетанты в Армении».
 */

document.addEventListener( 'DOMContentLoaded', function () {

    const carousels = document.querySelectorAll( '[data-carousel]' );

    carousels.forEach( function ( carousel ) {

        const track = carousel.querySelector( '[data-carousel-track]' );
        const previousButton = carousel.querySelector(
            '[data-carousel-previous]'
        );
        const nextButton = carousel.querySelector(
            '[data-carousel-next]'
        );

        if ( ! track || ! previousButton || ! nextButton ) {
            return;
        }

        function getScrollDistance() {
            const firstItem = track.querySelector(
                '.diletant-armenia__carousel-item'
            );

            if ( ! firstItem ) {
                return track.clientWidth;
            }

            const trackStyles = window.getComputedStyle( track );
            const gap = parseFloat( trackStyles.columnGap ) || 0;

            return firstItem.getBoundingClientRect().width + gap;
        }

        previousButton.addEventListener( 'click', function () {
            track.scrollBy(
                {
                    left: -getScrollDistance(),
                    behavior: 'smooth',
                }
            );
        } );

        nextButton.addEventListener( 'click', function () {
            track.scrollBy(
                {
                    left: getScrollDistance(),
                    behavior: 'smooth',
                }
            );
        } );

    } );

} );