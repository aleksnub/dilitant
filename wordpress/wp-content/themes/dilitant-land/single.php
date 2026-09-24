<?php
/**
 * Шаблон отдельной обычной записи WordPress.
 *
 * Для записей категории diletant-armenia используется
 * структура блога «Дилетанты в Армении».
 *
 * Остальные обычные записи продолжают выводиться
 * как стандартные записи темы.
 *
 * @package Dilitant_Land
 */

get_header();

while ( have_posts() ) :
    the_post();

    $is_diletant_article = function_exists( 'dilitant_land_is_blog_article' )
        && dilitant_land_is_blog_article( get_the_ID() );

    $featured_archive_photo = null;
    $featured_attachment_id = 0;

    if (
        $is_diletant_article
        && function_exists( 'dilitant_land_get_featured_archive_photo' )
        && function_exists( 'dilitant_land_get_featured_archive_photo_attachment_id' )
    ) {
        $featured_archive_photo = dilitant_land_get_featured_archive_photo(
            get_the_ID()
        );

        $featured_attachment_id = dilitant_land_get_featured_archive_photo_attachment_id(
            get_the_ID()
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

                <?php if ( $featured_archive_photo && $featured_attachment_id > 0 ) : ?>

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
                $archive_photos = function_exists( 'dilitant_land_get_article_archive_photos' )
                    ? dilitant_land_get_article_archive_photos( get_the_ID() )
                    : null;
                ?>

                <?php if ( $archive_photos && $archive_photos->have_posts() ) : ?>

                    <section
                        class="diletant-article__archive-photos"
                        aria-labelledby="diletant-article-archive-photos-title"
                    >

                        <h2 id="diletant-article-archive-photos-title">
                            Фотографии из архива
                        </h2>

                        <div class="diletant-article__archive-photos-list">

                            <?php
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
                            ?>

                        </div>

                    </section>

                    <?php wp_reset_postdata(); ?>

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