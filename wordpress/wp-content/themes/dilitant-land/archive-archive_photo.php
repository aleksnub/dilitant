<?php
/**
 * Общий фотоархив проекта «Дилетанты в Армении».
 *
 * Публичный адрес:
 *
 * /diletant-armenia/archive/
 *
 * @package Dilitant_Land
 */

get_header();
?>

<main class="site-main diletant-photo-archive">

    <header class="diletant-photo-archive__header">

        <p class="diletant-photo-archive__back">
            <a href="<?php echo esc_url( home_url( '/diletant-armenia/' ) ); ?>">
                ← Дилетанты в Армении
            </a>
        </p>

        <h1 class="diletant-photo-archive__title">
            Фотоархив
        </h1>

    </header>

    <?php if ( have_posts() ) : ?>

        <div class="diletant-photo-archive__list">

            <?php
            while ( have_posts() ) :
                the_post();

                $photo_id     = get_the_ID();
                $approx_date  = get_post_meta( $photo_id, '_archive_photo_approx_date', true );
                $place        = get_post_meta( $photo_id, '_archive_photo_place', true );
                $photographer = get_post_meta( $photo_id, '_archive_photo_photographer', true );
                ?>

                <article <?php post_class( 'diletant-photo-archive__item' ); ?>>

                    <?php if ( has_post_thumbnail() ) : ?>
                        <a
                            class="diletant-photo-archive__image-link"
                            href="<?php the_permalink(); ?>"
                        >
                            <?php the_post_thumbnail( 'large' ); ?>
                        </a>
                    <?php endif; ?>

                    <header class="diletant-photo-archive__item-header">

                        <h2 class="diletant-photo-archive__item-title">
                            <a href="<?php the_permalink(); ?>">
                                <?php the_title(); ?>
                            </a>
                        </h2>

                    </header>

                    <?php if ( $approx_date || $place || $photographer ) : ?>

                        <dl class="diletant-photo-archive__meta">

                            <?php if ( $approx_date ) : ?>
                                <div class="diletant-photo-archive__meta-row">
                                    <dt>Дата / период</dt>
                                    <dd><?php echo esc_html( $approx_date ); ?></dd>
                                </div>
                            <?php endif; ?>

                            <?php if ( $place ) : ?>
                                <div class="diletant-photo-archive__meta-row">
                                    <dt>Место</dt>
                                    <dd><?php echo esc_html( $place ); ?></dd>
                                </div>
                            <?php endif; ?>

                            <?php if ( $photographer ) : ?>
                                <div class="diletant-photo-archive__meta-row">
                                    <dt>Фотограф</dt>
                                    <dd><?php echo esc_html( $photographer ); ?></dd>
                                </div>
                            <?php endif; ?>

                        </dl>

                    <?php endif; ?>

                    <p class="diletant-photo-archive__open">
                        <a href="<?php the_permalink(); ?>">
                            Открыть фотографию →
                        </a>
                    </p>

                </article>

                <?php
            endwhile;
            ?>

        </div>

        <?php
        the_posts_pagination(
            array(
                'mid_size'  => 2,
                'prev_text' => '← Предыдущая',
                'next_text' => 'Следующая →',
            )
        );
        ?>

    <?php else : ?>

        <p class="diletant-photo-archive__empty">
            В фотоархиве пока нет опубликованных фотографий.
        </p>

    <?php endif; ?>

</main>

<?php
get_footer();