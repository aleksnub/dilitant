<?php
/**
 * Административный интерфейс фотоархива.
 *
 * Мета-бокс «Сведения о фотографии»
 * и сохранение введённых редактором данных.
 *
 * @package Dilitant_Land
 */


/**
 * Регистрация панели дополнительных полей фотографии.
 */
function dilitant_land_archive_photo_meta_boxes() {
    add_meta_box(
        'dilitant-land-archive-photo-details',
        'Сведения о фотографии',
        'dilitant_land_archive_photo_meta_box',
        'archive_photo',
        'normal',
        'high'
    );
}

add_action( 'add_meta_boxes', 'dilitant_land_archive_photo_meta_boxes' );


/**
 * Вывод дополнительных полей карточки архивной фотографии.
 *
 * @param WP_Post $post Текущая запись archive_photo.
 */
function dilitant_land_archive_photo_meta_box( $post ) {
    wp_nonce_field(
        'dilitant_land_save_archive_photo_meta',
        'dilitant_land_archive_photo_nonce'
    );

    $approx_date = get_post_meta(
        $post->ID,
        '_archive_photo_approx_date',
        true
    );

    $place = get_post_meta(
        $post->ID,
        '_archive_photo_place',
        true
    );

    $photographer = get_post_meta(
        $post->ID,
        '_archive_photo_photographer',
        true
    );

    $source = get_post_meta(
        $post->ID,
        '_archive_photo_source',
        true
    );

    $rights = get_post_meta(
        $post->ID,
        '_archive_photo_rights',
        true
    );

    $provided_by = get_post_meta(
        $post->ID,
        '_archive_photo_provided_by',
        true
    );

    $related_article = get_post_meta(
        $post->ID,
        '_archive_photo_related_article',
        true
    );
    ?>

    <p>
        <label for="archive_photo_approx_date">
            <strong>Примерная дата / период</strong>
        </label>
        <br>

        <input
            type="text"
            id="archive_photo_approx_date"
            name="archive_photo_approx_date"
            value="<?php echo esc_attr( $approx_date ); ?>"
            class="widefat"
            placeholder="Например: 1960-е годы или около 1910 года"
        >
    </p>

    <p>
        <label for="archive_photo_place">
            <strong>Место</strong>
        </label>
        <br>

        <input
            type="text"
            id="archive_photo_place"
            name="archive_photo_place"
            value="<?php echo esc_attr( $place ); ?>"
            class="widefat"
            placeholder="Например: Ереван, площадь Республики"
        >
    </p>

    <p>
        <label for="archive_photo_photographer">
            <strong>Фотограф / автор</strong>
        </label>
        <br>

        <input
            type="text"
            id="archive_photo_photographer"
            name="archive_photo_photographer"
            value="<?php echo esc_attr( $photographer ); ?>"
            class="widefat"
            placeholder="Если известен"
        >
    </p>

    <p>
        <label for="archive_photo_source">
            <strong>Источник</strong>
        </label>
        <br>

        <textarea
            id="archive_photo_source"
            name="archive_photo_source"
            class="widefat"
            rows="3"
        ><?php echo esc_textarea( $source ); ?></textarea>
    </p>

    <p>
        <label for="archive_photo_rights">
            <strong>Сведения о правах / основании публикации</strong>
        </label>
        <br>

        <textarea
            id="archive_photo_rights"
            name="archive_photo_rights"
            class="widefat"
            rows="4"
        ><?php echo esc_textarea( $rights ); ?></textarea>
    </p>

    <p>
        <label for="archive_photo_provided_by">
            <strong>Кто предоставил фотографию</strong>
        </label>
        <br>

        <input
            type="text"
            id="archive_photo_provided_by"
            name="archive_photo_provided_by"
            value="<?php echo esc_attr( $provided_by ); ?>"
            class="widefat"
            placeholder="Заполняется только если имя можно публиковать"
        >
    </p>

    <p>
        <label for="archive_photo_related_article">
            <strong>Связанная статья</strong>
        </label>
        <br>

        <select
            id="archive_photo_related_article"
            name="archive_photo_related_article"
            class="widefat"
        >
            <option value="">— Нет —</option>

            <?php
            $articles = get_posts(
                array(
                    'post_type'      => 'post',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'category_name'  => 'diletant-armenia',
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                )
            );

            foreach ( $articles as $article ) :
                ?>
                <option
                    value="<?php echo esc_attr( $article->ID ); ?>"
                    <?php selected( (int) $related_article, $article->ID ); ?>
                >
                    <?php echo esc_html( $article->post_title ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <?php
}


/**
 * Сохранение дополнительных полей архивной фотографии.
 *
 * @param int $post_id ID сохраняемой записи.
 */
function dilitant_land_save_archive_photo_meta( $post_id ) {

    /*
     * Проверяем nonce — одноразовый защитный токен WordPress.
     */
    if (
        ! isset( $_POST['dilitant_land_archive_photo_nonce'] )
        || ! wp_verify_nonce(
            sanitize_text_field(
                wp_unslash(
                    $_POST['dilitant_land_archive_photo_nonce']
                )
            ),
            'dilitant_land_save_archive_photo_meta'
        )
    ) {
        return;
    }

    /*
     * Не сохраняем метаданные во время автосохранения WordPress.
     */
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    /*
     * Обработчик предназначен только для archive_photo.
     */
    if ( get_post_type( $post_id ) !== 'archive_photo' ) {
        return;
    }

    /*
     * Проверяем право пользователя редактировать эту запись.
     */
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    /*
     * Однострочные текстовые поля.
     */
    $text_fields = array(
        'archive_photo_approx_date'  => '_archive_photo_approx_date',
        'archive_photo_place'        => '_archive_photo_place',
        'archive_photo_photographer' => '_archive_photo_photographer',
        'archive_photo_provided_by'  => '_archive_photo_provided_by',
    );

    foreach ( $text_fields as $field => $meta_key ) {
        if ( isset( $_POST[ $field ] ) ) {
            update_post_meta(
                $post_id,
                $meta_key,
                sanitize_text_field(
                    wp_unslash( $_POST[ $field ] )
                )
            );
        }
    }

    /*
     * Многострочные текстовые поля.
     */
    $textarea_fields = array(
        'archive_photo_source' => '_archive_photo_source',
        'archive_photo_rights' => '_archive_photo_rights',
    );

    foreach ( $textarea_fields as $field => $meta_key ) {
        if ( isset( $_POST[ $field ] ) ) {
            update_post_meta(
                $post_id,
                $meta_key,
                sanitize_textarea_field(
                    wp_unslash( $_POST[ $field ] )
                )
            );
        }
    }

    /*
     * Связанная статья.
     */
    if ( isset( $_POST['archive_photo_related_article'] ) ) {
        $related_article = absint(
            wp_unslash(
                $_POST['archive_photo_related_article']
            )
        );

        if (
            $related_article > 0
            && get_post_type( $related_article ) === 'post'
        ) {
            update_post_meta(
                $post_id,
                '_archive_photo_related_article',
                $related_article
            );
        } else {
            delete_post_meta(
                $post_id,
                '_archive_photo_related_article'
            );
        }
    }
}

add_action(
    'save_post_archive_photo',
    'dilitant_land_save_archive_photo_meta'
);