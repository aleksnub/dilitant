<?php
/**
 * Страница раздела «Дилетанты в Армении».
 *
 * @package Dilitant_Land
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>

<body <?php body_class( 'diletant-armenia-page' ); ?>>

<main class="diletant-armenia">

    <header class="diletant-armenia__hero">
        <div class="diletant-armenia__inner">

            <a
                class="diletant-armenia__back"
                href="<?php echo esc_url( home_url( '/' ) ); ?>"
            >
                Назад, на главную страницу
            </a>

            <h1>Дилетанты в Армении</h1>

            <p class="diletant-armenia__lead">
                Вокруг журнала «Дилетант» возникло небольшое общество людей,
                интересующихся историей Армении. Здесь они публикуют свои статьи
                об истории Армении и старые фотографии страны и её городов.
            </p>

            <nav
                class="diletant-armenia__social"
                aria-label="Каналы проекта «Дилетанты в Армении»"
            >
                <a
                    class="diletant-armenia__social-link"
                    href="https://t.me/diletantinarmenia"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Telegram
                </a>

                <a
                    class="diletant-armenia__social-link"
                    href="https://www.youtube.com/channel/UCJ2zyXCQqCtX-TLAgnpOwUg"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    YouTube
                </a>
            </nav>

        </div>
    </header>


    <section
        class="diletant-armenia__section diletant-armenia__articles"
        aria-labelledby="diletant-armenia-articles-title"
    >
        <div class="diletant-armenia__inner">

            <div class="diletant-armenia__section-header">

                <h2 id="diletant-armenia-articles-title">
                    Статьи
                </h2>

                <a
                    class="diletant-armenia__section-link"
                    href="<?php echo esc_url(
                        home_url( '/diletant-armenia/articles/' )
                    ); ?>"
                >
                    Все статьи
                </a>

            </div>

            <?php
            $articles = new WP_Query(
                array(
                    'post_type'      => 'post',
                    'post_status'    => 'publish',
                    'category_name'  => 'diletant-armenia',
                    'posts_per_page' => 6,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                )
            );
            ?>

            <?php if ( $articles->have_posts() ) : ?>

                <div
                    class="diletant-armenia__carousel"
                    data-carousel
                >

                    <div
                        class="diletant-armenia__carousel-controls"
                        aria-label="Управление каруселью статей"
                    >

                        <button
                            class="
                                diletant-armenia__carousel-button
                                diletant-armenia__carousel-button--previous
                            "
                            type="button"
                            data-carousel-previous
                            aria-label="Предыдущие статьи"
                        >
                            ←
                        </button>

                        <button
                            class="
                                diletant-armenia__carousel-button
                                diletant-armenia__carousel-button--next
                            "
                            type="button"
                            data-carousel-next
                            aria-label="Следующие статьи"
                        >
                            →
                        </button>

                    </div>

                    <div
                        class="
                            diletant-armenia__carousel-track
                            diletant-armenia__article-track
                        "
                        data-carousel-track
                    >

                        <?php
                        while ( $articles->have_posts() ) :
                            $articles->the_post();
                            ?>

                            <article
                                class="
                                    diletant-armenia__carousel-item
                                    diletant-armenia__article-card
                                "
                            >

                                <?php if ( has_post_thumbnail() ) : ?>

                                    <a
                                        href="<?php the_permalink(); ?>"
                                        class="diletant-armenia__article-image"
                                    >
                                        <?php
                                        the_post_thumbnail(
                                            'large',
                                            array(
                                                'loading' => 'lazy',
                                            )
                                        );
                                        ?>
                                    </a>

                                <?php endif; ?>

                                <div class="diletant-armenia__article-content">

                                    <h3>
                                        <a href="<?php the_permalink(); ?>">
                                            <?php the_title(); ?>
                                        </a>
                                    </h3>

                                    <p class="diletant-armenia__article-date">
                                        <?php
                                        echo esc_html(
                                            get_the_date()
                                        );
                                        ?>
                                    </p>

                                    <div
                                        class="diletant-armenia__article-excerpt"
                                    >
                                        <?php the_excerpt(); ?>
                                    </div>

                                </div>

                            </article>

                        <?php endwhile; ?>

                    </div>

                </div>

            <?php else : ?>

                <p class="diletant-armenia__empty">
                    Статьи готовятся к публикации.
                </p>

            <?php endif; ?>

            <?php wp_reset_postdata(); ?>

        </div>
    </section>


    <section
        class="diletant-armenia__section diletant-armenia__archive"
        aria-labelledby="diletant-armenia-archive-title"
    >
        <div class="diletant-armenia__inner">

            <div class="diletant-armenia__section-header">

                <h2 id="diletant-armenia-archive-title">
                    Фотоархив
                </h2>

                <a
                    class="diletant-armenia__section-link"
                    href="<?php echo esc_url(
                        home_url( '/diletant-armenia/archive/' )
                    ); ?>"
                >
                    Весь фотоархив
                </a>

            </div>

            <?php
            $archive_photos = new WP_Query(
                array(
                    'post_type'      => 'archive_photo',
                    'post_status'    => 'publish',
                    'posts_per_page' => 8,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                )
            );
            ?>

            <?php if ( $archive_photos->have_posts() ) : ?>

                <div
                    class="diletant-armenia__carousel"
                    data-carousel
                >

                    <div
                        class="diletant-armenia__carousel-controls"
                        aria-label="Управление каруселью фотоархива"
                    >

                        <button
                            class="
                                diletant-armenia__carousel-button
                                diletant-armenia__carousel-button--previous
                            "
                            type="button"
                            data-carousel-previous
                            aria-label="Предыдущие фотографии"
                        >
                            ←
                        </button>

                        <button
                            class="
                                diletant-armenia__carousel-button
                                diletant-armenia__carousel-button--next
                            "
                            type="button"
                            data-carousel-next
                            aria-label="Следующие фотографии"
                        >
                            →
                        </button>

                    </div>

                    <div
                        class="
                            diletant-armenia__carousel-track
                            diletant-armenia__photo-track
                        "
                        data-carousel-track
                    >

                        <?php
                        while ( $archive_photos->have_posts() ) :
                            $archive_photos->the_post();
                            ?>

                            <article
                                class="
                                    diletant-armenia__carousel-item
                                    diletant-armenia__photo-card
                                "
                            >

                                <a href="<?php the_permalink(); ?>">

                                    <?php if ( has_post_thumbnail() ) : ?>

                                        <div
                                            class="
                                                diletant-armenia__photo-image
                                            "
                                        >
                                            <?php
                                            the_post_thumbnail(
                                                'large',
                                                array(
                                                    'loading' => 'lazy',
                                                )
                                            );
                                            ?>
                                        </div>

                                    <?php endif; ?>

                                    <h3>
                                        <?php the_title(); ?>
                                    </h3>

                                </a>

                                <?php if ( has_excerpt() ) : ?>

                                    <div
                                        class="
                                            diletant-armenia__photo-excerpt
                                        "
                                    >
                                        <?php the_excerpt(); ?>
                                    </div>

                                <?php endif; ?>

                            </article>

                        <?php endwhile; ?>

                    </div>

                </div>

            <?php else : ?>

                <p class="diletant-armenia__empty">
                    Фотографии готовятся к публикации.
                </p>

            <?php endif; ?>

            <?php wp_reset_postdata(); ?>

        </div>
    </section>


    <section
        class="
            diletant-armenia__section
            diletant-armenia__submissions
        "
        aria-labelledby="diletant-armenia-submissions-title"
    >
        <div class="diletant-armenia__inner">

            <header class="diletant-armenia__submissions-header">

                <h2 id="diletant-armenia-submissions-title">
                    Предложить материал
                </h2>

                <p>
                    Мы будем рады старым фотографиям Армении
                    и авторским статьям по истории страны.
                </p>

            </header>

            <div class="diletant-armenia__submission-accordions">

                <details
                    class="
                        diletant-armenia__submission-accordion
                        diletant-armenia__submission-accordion--photo
                    "
                >
                    <summary class="diletant-armenia__submission-summary">
                        Прислать фотографию
                    </summary>

                    <div class="diletant-armenia__submission-content">
                        <?php
                        dilitant_land_photo_submission_render_form();
                        ?>
                    </div>
                </details>

                <details
                    class="
                        diletant-armenia__submission-accordion
                        diletant-armenia__submission-accordion--article
                    "
                >
                    <summary class="diletant-armenia__submission-summary">
                        Прислать статью
                    </summary>

                    <div class="diletant-armenia__submission-content">
                        <?php
                        dilitant_land_article_submission_render_form();
                        ?>
                    </div>
                </details>

            </div>

        </div>
    </section>

</main>

<?php wp_footer(); ?>

</body>
</html>