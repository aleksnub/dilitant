<?php
/**
 * Шаблон отдельной записи WordPress.
 *
 * Для записей нормативного дерева категорий
 * diletant-armenia используется структура
 * блога «Дилетанты в Армении».
 *
 * Остальные записи продолжают выводиться
 * как стандартные записи темы.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D01 — Идентификация статьи
 *     AM-D03 — Публичная доступность
 *
 * Принадлежность записи к блогу
 * не является разрешением её отображения.
 *
 * Основная проверка доступности выполняется
 * обработчиком template_redirect.
 *
 * Шаблон обеспечивает дополнительный
 * контроль на границе рендеринга.
 *
 * @package Dilitant_Land
 */

defined( 'ABSPATH' ) || exit;


/*
 * Проверяем наличие обязательных интерфейсов
 * Article Model.
 *
 * Отсутствие механизма контроля доступности
 * не должно приводить к отображению записи
 * без нормативной проверки.
 */
if (
    ! function_exists( 'dilitant_land_is_blog_article' )
    || ! function_exists( 'dilitant_land_can_render_blog_article' )
    || ! function_exists( 'dilitant_land_reject_blog_article' )
) {
    wp_die(
        esc_html( 'Отображение публикации временно недоступно.' ),
        '',
        array(
            'response' => 503,
        )
    );
}


/*
 * Получаем непосредственно запрошенную запись.
 *
 * Проверка выполняется до формирования HTML.
 */
$queried_post = get_queried_object();

if ( ! $queried_post instanceof WP_Post ) {
    dilitant_land_reject_blog_article();

    wp_die(
        esc_html( 'Запрашиваемая публикация недоступна.' ),
        '',
        array(
            'response' => 404,
        )
    );
}


/*
 * Определяем принадлежность записи к блогу.
 *
 * Статус публикации и парольная защита
 * не влияют на принадлежность.
 */
$is_queried_blog_article =
    dilitant_land_is_blog_article(
        $queried_post
    );


/*
 * Проверяем допустимость отображения статьи.
 *
 * Допустимыми основаниями являются:
 *
 *     - публичная доступность;
 *     - авторизованный предварительный просмотр.
 *
 * Недоступная статья не должна попадать
 * в стандартную ветку отображения WordPress.
 */
if (
    $is_queried_blog_article
    && ! dilitant_land_can_render_blog_article(
        $queried_post
    )
) {
    dilitant_land_reject_blog_article();

    wp_die(
        esc_html( 'Запрашиваемая публикация недоступна.' ),
        '',
        array(
            'response' => 404,
        )
    );
}


/*
 * Сохраняем идентификатор проверенной записи.
 */
$authorized_post_id = (int) $queried_post->ID;


/*
 * Предварительный просмотр не должен
 * публично кэшироваться.
 */
if (
    $is_queried_blog_article
    && is_preview()
) {
    nocache_headers();
}


/*
 * Формирование HTML допускается только
 * после проверки доступности.
 */
get_header();


while ( have_posts() ) :
    the_post();

    /*
     * Проверяем фактически отображаемую запись.
     */
    $current_post = get_post();

    if ( ! $current_post instanceof WP_Post ) {
        continue;
    }

    /*
     * Не допускаем подмену проверенной записи
     * другой записью внутри цикла.
     */
    if (
        $authorized_post_id
        !== (int) $current_post->ID
    ) {
        continue;
    }


    /*
     * Идентификация статьи.
     *
     * Не подменяется проверкой публичности.
     */
    $is_diletant_article =
        dilitant_land_is_blog_article(
            $current_post
        );


    /*
     * Повторная проверка допустимости
     * непосредственного отображения.
     */
    if (
        $is_queried_blog_article
        || $is_diletant_article
    ) {
        if (
            ! dilitant_land_can_render_blog_article(
                $current_post
            )
        ) {
            continue;
        }
    }


    /*
     * Основная иллюстрация статьи.
     */
    $featured_archive_photo = null;

    $featured_attachment_id = 0;

    if (
        $is_diletant_article
        && function_exists(
            'dilitant_land_get_featured_archive_photo'
        )
        && function_exists(
            'dilitant_land_get_featured_archive_photo_attachment_id'
        )
    ) {
        $featured_archive_photo =
            dilitant_land_get_featured_archive_photo(
                $current_post->ID
            );

        $featured_attachment_id =
            dilitant_land_get_featured_archive_photo_attachment_id(
                $current_post->ID
            );
    }
    ?>

    <main class="site-main">

        <?php if ( $is_diletant_article ) : ?>

            <article <?php post_class( 'diletant-article' ); ?>>

                <p class="diletant-article__back">

                    <a href="<?php echo esc_url( dilitant_land_get_blog_url() ); ?>">
                        ← Все статьи
                    </a>

                </p>


                <?php if ( is_preview() ) : ?>

                    <p
                        class="diletant-article__preview-notice"
                        role="status"
                    >
                        Предварительный просмотр
                    </p>

                <?php endif; ?>


                <header class="diletant-article__header">

                    <h1 class="diletant-article__title">
                        <?php the_title(); ?>
                    </h1>

                    <p class="diletant-article__date">

                        <time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>">
                            <?php echo esc_html( get_the_date() ); ?>
                        </time>

                    </p>

                </header>


                <?php if (
                    $featured_archive_photo
                    && $featured_attachment_id > 0
                ) : ?>

                    <figure class="diletant-article__featured-image diletant-article__featured-archive-photo">

                        <a href="<?php echo esc_url( get_permalink( $featured_archive_photo ) ); ?>">

                            <?php
                            echo wp_get_attachment_image(
                                $featured_attachment_id,
                                'full'
                            );
                            ?>

                        </a>

                    </figure>


                <?php elseif ( has_post_thumbnail() ) : ?>

                    <figure class="diletant-article__featured-image">

                        <?php the_post_thumbnail( 'full' ); ?>

                    </figure>

                <?php endif; ?>


                <div class="diletant-article__content">

                    <?php the_content(); ?>

                </div>


                <?php
                /*
                 * Связанные архивные фотографии
                 * запрашиваются после подтверждения
                 * доступности статьи.
                 */
                $archive_photos = function_exists(
                    'dilitant_land_get_article_archive_photos'
                )
                    ? dilitant_land_get_article_archive_photos(
                        $current_post->ID
                    )
                    : null;
                ?>


                <?php if (
                    $archive_photos instanceof WP_Query
                    && $archive_photos->have_posts()
                ) : ?>

                    <section
                        class="diletant-article__archive-photos"
                        aria-labelledby="diletant-article-archive-photos-title"
                    >

                        <h2 id="diletant-article-archive-photos-title">
                            Фотографии из архива
                        </h2>

                        <div class="diletant-article__archive-photos-list">

                            <?php
                            try {
                                while ( $archive_photos->have_posts() ) :
                                    $archive_photos->the_post();
                                    ?>

                                    <article class="diletant-article__archive-photo">

                                        <a href="<?php the_permalink(); ?>">

                                            <?php if ( has_post_thumbnail() ) : ?>

                                                <?php the_post_thumbnail( 'large' ); ?>

                                            <?php endif; ?>

                                            <h3>
                                                <?php the_title(); ?>
                                            </h3>

                                        </a>

                                    </article>

                                    <?php
                                endwhile;

                            } finally {
                                /*
                                 * Восстанавливаем контекст
                                 * основной записи после
                                 * вложенного WP_Query.
                                 */
                                wp_reset_postdata();
                            }
                            ?>

                        </div>

                    </section>

                <?php endif; ?>

            </article>


        <?php else : ?>

            <article <?php post_class(); ?>>

                <header class="entry-header">

                    <h1 class="entry-title">
                        <?php the_title(); ?>
                    </h1>

                </header>


                <?php if ( has_post_thumbnail() ) : ?>

                    <div class="entry-thumbnail">

                        <?php the_post_thumbnail( 'full' ); ?>

                    </div>

                <?php endif; ?>


                <div class="entry-content">

                    <?php the_content(); ?>

                </div>

            </article>

        <?php endif; ?>

    </main>

    <?php
endwhile;


get_footer();