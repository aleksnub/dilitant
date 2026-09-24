<?php
/**
 * Публичная страница архивной фотографии.
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

<body <?php body_class( 'archive-photo-page-body' ); ?>>

<main class="archive-photo-page">
    <div class="archive-photo-page__inner">

        <a
            class="archive-photo-page__back"
            href="<?php echo esc_url( home_url( '/diletant-armenia/archive/' ) ); ?>"
        >
            ← Назад в фотоархив
        </a>

        <?php if ( have_posts() ) : ?>
            <?php while ( have_posts() ) : the_post(); ?>

                <?php
                $post_id = get_the_ID();

                $approx_date = get_post_meta(
                    $post_id,
                    '_archive_photo_approx_date',
                    true
                );

                $place = get_post_meta(
                    $post_id,
                    '_archive_photo_place',
                    true
                );

                $photographer = get_post_meta(
                    $post_id,
                    '_archive_photo_photographer',
                    true
                );

                $source = get_post_meta(
                    $post_id,
                    '_archive_photo_source',
                    true
                );

                $rights = get_post_meta(
                    $post_id,
                    '_archive_photo_rights',
                    true
                );

                $provided_by = get_post_meta(
                    $post_id,
                    '_archive_photo_provided_by',
                    true
                );

                $related_article_id = absint(
                    get_post_meta(
                        $post_id,
                        '_archive_photo_related_article',
                        true
                    )
                );
                ?>

                <article class="archive-photo">

                    <header class="archive-photo__header">
                        <p class="archive-photo__label">
                            Фотоархив
                        </p>

                        <h1 class="archive-photo__title">
                            <?php the_title(); ?>
                        </h1>
                    </header>

                    <?php if ( has_post_thumbnail() ) : ?>
                        <figure class="archive-photo__figure">

                            <?php
                            the_post_thumbnail(
                                'full',
                                array(
                                    'class' => 'archive-photo__image',
                                )
                            );
                            ?>

                            <?php if ( $photographer ) : ?>
                                <figcaption class="archive-photo__caption">
                                    <?php
                                    echo esc_html(
                                        'Фотограф: ' . $photographer
                                    );
                                    ?>
                                </figcaption>
                            <?php endif; ?>

                        </figure>
                    <?php endif; ?>

                    <?php if ( get_the_content() ) : ?>
                        <div class="archive-photo__description">
                            <?php the_content(); ?>
                        </div>
                    <?php endif; ?>

                    <?php
                    $has_details =
                        $approx_date
                        || $place
                        || $photographer
                        || $source
                        || $rights
                        || $provided_by;
                    ?>

                    <?php if ( $has_details ) : ?>

                        <section
                            class="archive-photo__details"
                            aria-labelledby="archive-photo-details-title"
                        >
                            <h2 id="archive-photo-details-title">
                                Сведения о фотографии
                            </h2>

                            <dl class="archive-photo__metadata">

                                <?php if ( $approx_date ) : ?>
                                    <div class="archive-photo__metadata-row">
                                        <dt>Дата / период</dt>
                                        <dd>
                                            <?php echo esc_html( $approx_date ); ?>
                                        </dd>
                                    </div>
                                <?php endif; ?>

                                <?php if ( $place ) : ?>
                                    <div class="archive-photo__metadata-row">
                                        <dt>Место</dt>
                                        <dd>
                                            <?php echo esc_html( $place ); ?>
                                        </dd>
                                    </div>
                                <?php endif; ?>

                                <?php if ( $photographer ) : ?>
                                    <div class="archive-photo__metadata-row">
                                        <dt>Фотограф / автор</dt>
                                        <dd>
                                            <?php echo esc_html( $photographer ); ?>
                                        </dd>
                                    </div>
                                <?php endif; ?>

                                <?php if ( $source ) : ?>
                                    <div class="archive-photo__metadata-row">
                                        <dt>Источник</dt>
                                        <dd>
                                            <?php echo nl2br( esc_html( $source ) ); ?>
                                        </dd>
                                    </div>
                                <?php endif; ?>

                                <?php if ( $provided_by ) : ?>
                                    <div class="archive-photo__metadata-row">
                                        <dt>Предоставил</dt>
                                        <dd>
                                            <?php echo esc_html( $provided_by ); ?>
                                        </dd>
                                    </div>
                                <?php endif; ?>

                                <?php if ( $rights ) : ?>
                                    <div class="archive-photo__metadata-row">
                                        <dt>Права / основание публикации</dt>
                                        <dd>
                                            <?php echo nl2br( esc_html( $rights ) ); ?>
                                        </dd>
                                    </div>
                                <?php endif; ?>

                            </dl>
                        </section>

                    <?php endif; ?>

                    <?php
                    if (
                        $related_article_id
                        && get_post_type( $related_article_id ) === 'post'
                        && get_post_status( $related_article_id ) === 'publish'
                    ) :
                        ?>

                        <section class="archive-photo__related">
                            <p class="archive-photo__related-label">
                                Связанная статья
                            </p>

                            <a
                                class="archive-photo__related-link"
                                href="<?php echo esc_url(
                                    get_permalink( $related_article_id )
                                ); ?>"
                            >
                                <?php echo esc_html(
                                    get_the_title( $related_article_id )
                                ); ?>
                            </a>
                        </section>

                    <?php endif; ?>

                </article>

            <?php endwhile; ?>
        <?php endif; ?>

        <a
            class="archive-photo-page__back archive-photo-page__back--bottom"
           href="<?php echo esc_url( home_url( '/diletant-armenia/archive/' ) ); ?>"
        >
            ← Назад в фотоархив
        </a>

    </div>
</main>

<?php wp_footer(); ?>

</body>
</html>