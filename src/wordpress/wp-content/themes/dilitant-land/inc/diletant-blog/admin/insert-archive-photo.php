<?php
/**
 * Редакторский интерфейс вставки archive_photo
 * внутрь текста статьи.
 *
 * В текст вставляется:
 *
 * [archive_photo id="46"]
 *
 * @package Dilitant_Land
 */


/**
 * Добавляет блок «Вставить из фотоархива».
 */
function dilitant_land_add_insert_archive_photo_meta_box() {
    add_meta_box(
        'dilitant-insert-archive-photo',
        'Вставить из фотоархива',
        'dilitant_land_render_insert_archive_photo_meta_box',
        'post',
        'side',
        'default'
    );
}
add_action(
    'add_meta_boxes_post',
    'dilitant_land_add_insert_archive_photo_meta_box'
);


/**
 * Выводит интерфейс выбора фотографии.
 *
 * @param WP_Post $post Редактируемая запись.
 */
function dilitant_land_render_insert_archive_photo_meta_box( $post ) {
    if (
        ! function_exists( 'dilitant_land_is_blog_article' )
        || ! dilitant_land_is_blog_article( $post )
    ) {
        echo '<p>Вставка из фотоархива доступна только для статей проекта «Дилетанты в Армении».</p>';
        return;
    }

    $photos = get_posts(
        array(
            'post_type'      => 'archive_photo',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        )
    );
    ?>

    <?php if ( ! $photos ) : ?>

        <p>
            В фотоархиве нет опубликованных фотографий.
        </p>

        <?php
        return;
    endif;
    ?>

    <p>
        <label for="dilitant-insert-archive-photo-select">
            Архивная фотография
        </label>
    </p>

    <select
        id="dilitant-insert-archive-photo-select"
        style="width: 100%;"
    >
        <option value="">— Выберите фотографию —</option>

        <?php foreach ( $photos as $photo ) : ?>

            <option value="<?php echo esc_attr( $photo->ID ); ?>">
                <?php
                echo esc_html(
                    sprintf(
                        '#%d — %s',
                        $photo->ID,
                        $photo->post_title
                    )
                );
                ?>
            </option>

        <?php endforeach; ?>

    </select>

    <p>
        <button
            type="button"
            class="button"
            id="dilitant-insert-archive-photo-button"
        >
            Вставить в статью
        </button>
    </p>

    <p>
        В текст будет вставлена ссылка на объект фотоархива.
        JPEG не копируется.
    </p>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const select = document.getElementById(
            'dilitant-insert-archive-photo-select'
        );

        const button = document.getElementById(
            'dilitant-insert-archive-photo-button'
        );

        if (!select || !button) {
            return;
        }

        button.addEventListener('click', function () {
            const photoId = parseInt(select.value, 10);

            if (!photoId) {
                window.alert('Сначала выберите фотографию.');
                return;
            }

            const shortcode = '[archive_photo id="' + photoId + '"]';

            /*
             * Gutenberg / блочный редактор:
             * вставляем shortcode отдельным блоком Shortcode.
             */
            if (
                window.wp
                && wp.data
                && wp.blocks
                && wp.data.dispatch('core/block-editor')
            ) {
                const block = wp.blocks.createBlock(
                    'core/shortcode',
                    {
                        text: shortcode
                    }
                );

                wp.data
                    .dispatch('core/block-editor')
                    .insertBlocks(block);

                return;
            }

            /*
             * Классический редактор TinyMCE.
             */
            if (
                window.tinyMCE
                && tinyMCE.activeEditor
                && !tinyMCE.activeEditor.isHidden()
            ) {
                tinyMCE.activeEditor.execCommand(
                    'mceInsertContent',
                    false,
                    shortcode
                );

                return;
            }

            /*
             * Текстовый режим классического редактора.
             */
            const content = document.getElementById('content');

            if (!content) {
                window.alert(
                    'Не удалось определить поле текста статьи.'
                );
                return;
            }

            const start = content.selectionStart;
            const end   = content.selectionEnd;

            content.value =
                content.value.substring(0, start)
                + shortcode
                + content.value.substring(end);

            content.selectionStart =
                content.selectionEnd =
                start + shortcode.length;

            content.focus();
        });
    });
    </script>

    <?php
}