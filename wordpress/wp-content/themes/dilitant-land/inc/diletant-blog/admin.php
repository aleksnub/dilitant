<?php
/**
 * Редакторский интерфейс блога «Дилетанты в Армении».
 *
 * Позволяет выбрать опубликованный archive_photo
 * в качестве обложки статьи.
 *
 * В статье сохраняется ID archive_photo.
 * JPEG и attachment не копируются.
 *
 * @package Dilitant_Land
 */


/**
 * Добавляет блок выбора архивной фотографии
 * в редактор обычных записей WordPress.
 */
function dilitant_land_add_featured_archive_photo_meta_box() {
    add_meta_box(
        'dilitant-featured-archive-photo',
        'Обложка из фотоархива',
        'dilitant_land_render_featured_archive_photo_meta_box',
        'post',
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes_post', 'dilitant_land_add_featured_archive_photo_meta_box' );


/**
 * Выводит интерфейс выбора архивной фотографии.
 *
 * Блок показывается только для статьи категории
 * diletant-armenia.
 *
 * @param WP_Post $post Редактируемая запись.
 */
function dilitant_land_render_featured_archive_photo_meta_box( $post ) {
    if (
        ! function_exists( 'dilitant_land_is_blog_article' )
        || ! dilitant_land_is_blog_article( $post )
    ) {
        echo '<p>Обложка из фотоархива доступна только для статей проекта «Дилетанты в Армении».</p>';
        return;
    }

    wp_nonce_field(
        'dilitant_land_save_featured_archive_photo',
        'dilitant_land_featured_archive_photo_nonce'
    );

    $selected_photo_id = (int) get_post_meta(
        $post->ID,
        DILITANT_LAND_FEATURED_ARCHIVE_PHOTO_META_KEY,
        true
    );

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

    <p>
        <label for="dilitant-featured-archive-photo-select">
            Архивная фотография
        </label>
    </p>

    <select
        id="dilitant-featured-archive-photo-select"
        name="dilitant_featured_archive_photo"
        style="width: 100%;"
    >
        <option value="">— Не выбрана —</option>

        <?php foreach ( $photos as $photo ) : ?>
            <option
                value="<?php echo esc_attr( $photo->ID ); ?>"
                <?php selected( $selected_photo_id, $photo->ID ); ?>
            >
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

    <?php if ( $selected_photo_id > 0 ) : ?>

        <?php
        $selected_photo = get_post( $selected_photo_id );
        ?>

        <?php
        if (
            $selected_photo instanceof WP_Post
            && 'archive_photo' === $selected_photo->post_type
            && 'publish' === $selected_photo->post_status
            && has_post_thumbnail( $selected_photo->ID )
        ) :
            ?>

            <p>
                <?php
                echo get_the_post_thumbnail(
                    $selected_photo->ID,
                    'medium',
                    array(
                        'style' => 'max-width:100%;height:auto;',
                    )
                );
                ?>
            </p>

        <?php endif; ?>

    <?php endif; ?>

    <p>
        Выбирается существующая опубликованная фотография
        из фотоархива. Новый файл не создаётся.
    </p>

    <?php
}


/**
 * Сохраняет выбранную архивную фотографию
 * как обложку статьи.
 *
 * @param int $post_id ID статьи.
 */
function dilitant_land_save_featured_archive_photo( $post_id ) {
    if (
        ! isset( $_POST['dilitant_land_featured_archive_photo_nonce'] )
        || ! wp_verify_nonce(
            sanitize_text_field(
                wp_unslash(
                    $_POST['dilitant_land_featured_archive_photo_nonce']
                )
            ),
            'dilitant_land_save_featured_archive_photo'
        )
    ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $post = get_post( $post_id );

    if (
        ! $post instanceof WP_Post
        || 'post' !== $post->post_type
        || ! function_exists( 'dilitant_land_is_blog_article' )
        || ! dilitant_land_is_blog_article( $post )
    ) {
        return;
    }

    $archive_photo_id = isset( $_POST['dilitant_featured_archive_photo'] )
        ? absint( $_POST['dilitant_featured_archive_photo'] )
        : 0;

    /*
     * Пустой выбор означает удаление обложки.
     */
    if ( 0 === $archive_photo_id ) {
        delete_post_meta(
            $post_id,
            DILITANT_LAND_FEATURED_ARCHIVE_PHOTO_META_KEY
        );

        return;
    }

    /*
     * Нельзя назначить черновик, чужой тип записи
     * или несуществующий объект.
     */
    $archive_photo = get_post( $archive_photo_id );

    if (
        ! $archive_photo instanceof WP_Post
        || 'archive_photo' !== $archive_photo->post_type
        || 'publish' !== $archive_photo->post_status
    ) {
        return;
    }

    /*
     * Обложкой может быть только archive_photo,
     * у которого действительно есть публичное изображение.
     */
    if ( ! has_post_thumbnail( $archive_photo_id ) ) {
        return;
    }

    update_post_meta(
        $post_id,
        DILITANT_LAND_FEATURED_ARCHIVE_PHOTO_META_KEY,
        $archive_photo_id
    );
}
add_action( 'save_post_post', 'dilitant_land_save_featured_archive_photo' );
require_once __DIR__ . '/admin/insert-archive-photo.php';