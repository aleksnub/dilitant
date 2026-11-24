<?php
/**
 * Template Name: Юридическая страница
 *
 * Шаблон для простых текстовых страниц без общего хедера и футера лендинга.
 * Используется для юридических документов и служебных страниц.
 *
 * @package Dilitant_Land
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( wp_get_document_title() ); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'legal-page-body' ); ?>>
<main class="legal-page">
    <div class="legal-page__inner">
        <a class="legal-page__back" href="<?php echo esc_url( home_url( '/' ) ); ?>">Назад, на главную страницу</a>

        <?php if ( have_posts() ) : ?>
            <?php while ( have_posts() ) : the_post(); ?>
                <article class="legal-document">
                    <header class="legal-document__header">
                        <p class="legal-document__label">Документ</p>
                        <h1><?php the_title(); ?></h1>
                    </header>

                    <div class="legal-document__content">
                        <?php the_content(); ?>
                    </div>
                </article>
            <?php endwhile; ?>
        <?php endif; ?>

        <a class="legal-page__back legal-page__back--bottom" href="<?php echo esc_url( home_url( '/' ) ); ?>">Назад, на главную страницу</a>
    </div>
</main>
<?php wp_footer(); ?>
</body>
</html>
