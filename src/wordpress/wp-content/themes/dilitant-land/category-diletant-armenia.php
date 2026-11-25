<?php
/**
 * Публичный каталог статей блога
 * «Дилетанты в Армении».
 *
 * Публичный маршрут:
 *
 *     /diletant-armenia/articles/
 *
 * Нормативная основа:
 *     ТП-03 — Editorial, редакция 1.1
 *     EDC-01 — Article Model
 *     AM-D02 — Защита инвариантов WP_Query
 *     AM-D05 — Пагинация каталога
 *
 * Ответственность:
 *     - отображение результатов глобального запроса;
 *     - отображение карточек статей;
 *     - отображение пагинации;
 *     - отображение пустого состояния.
 *
 * Нормативная политика глобального запроса
 * применяется компонентом:
 *
 *     inc/diletant-blog/catalog-query.php
 *
 * Шаблон не создаёт собственный WP_Query
 * и не формирует ограничения выборки.
 *
 * @package Dilitant_Land
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main class="site-main diletant-articles">

    <header class="diletant-articles__header">

        <p class="diletant-articles__back">

            <a href="<?php
                echo esc_url(
                    home_url( '/diletant-armenia/' )
                );
            ?>">
                ← Дилетанты в Армении
            </a>

        </p>

        <h1 class="diletant-articles__title">
            Статьи
        </h1>

    </header>

    <?php if ( have_posts() ) : ?>

        <div class="diletant-articles__list">

            <?php
            while ( have_posts() ) :

                the_post();
                ?>

                <article <?php
                    post_class(
                        'diletant-articles__item'
                    );
                ?>>

                    <?php if ( has_post_thumbnail() ) : ?>

                        <a
                            class="diletant-articles__image-link"
                            href="<?php the_permalink(); ?>"
                        >
                            <?php
                            the_post_thumbnail(
                                'large'
                            );
                            ?>
                        </a>

                    <?php endif; ?>

                    <header class="diletant-articles__item-header">

                        <h2 class="diletant-articles__item-title">

                            <a href="<?php the_permalink(); ?>">
                                <?php the_title(); ?>
                            </a>

                        </h2>

                        <p class="diletant-articles__item-date">

                            <time datetime="<?php
                                echo esc_attr(
                                    get_the_date(
                                        DATE_W3C
                                    )
                                );
                            ?>">

                                <?php
                                echo esc_html(
                                    get_the_date()
                                );
                                ?>

                            </time>

                        </p>

                    </header>

                    <div class="diletant-articles__excerpt">

                        <?php the_excerpt(); ?>

                    </div>

                    <p class="diletant-articles__read-more">

                        <a href="<?php the_permalink(); ?>">
                            Читать статью →
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

                'prev_text' =>
                    '← Предыдущая',

                'next_text' =>
                    'Следующая →',
            )
        );
        ?>

    <?php else : ?>

        <p class="diletant-articles__empty">
            Статей пока нет.
        </p>

    <?php endif; ?>

</main>

<?php
get_footer();