<?php
/**
 * Публичная форма отправки фотографии.
 *
 * Выводит форму и понятные посетителю
 * сообщения о результате отправки.
 *
 * @package Dilitant_Land
 */


/**
 * Возвращает публичное сообщение об ошибке отправки.
 *
 * Внутренние технические сведения посетителю
 * не показываются.
 *
 * @param string $error_code Публичный код ошибки.
 *
 * @return string
 */
function dilitant_land_photo_submission_error_message(
    $error_code
) {
    $messages = array(
        'invalid_request' =>
            'Не удалось обработать запрос. Попробуйте отправить форму ещё раз.',

        'invalid_nonce' =>
            'Срок действия формы истёк. Обновите страницу и попробуйте ещё раз.',

        'submitter_name_missing' =>
            'Укажите ваше имя.',

        'submitter_email_missing' =>
            'Укажите адрес электронной почты.',

        'invalid_email' =>
            'Проверьте адрес электронной почты.',

        'publication_basis_missing' =>
            'Укажите основание для публикации фотографии.',

        'rights_not_confirmed' =>
            'Подтвердите, что вы имеете право передать фотографию редакции.',

        'file_missing' =>
            'Выберите фотографию для отправки.',

        'upload_failed' =>
            'Не удалось загрузить фотографию.',

        'invalid_file' =>
            'Не удалось принять файл. Используйте фотографию в формате JPEG, PNG или WebP размером не более 10 МБ.',

        'processing_failed' =>
            'Не удалось обработать фотографию.',

        'save_failed' =>
            'Не удалось сохранить заявку.',
    );

    if ( isset( $messages[ $error_code ] ) ) {
        return $messages[ $error_code ];
    }

    return 'Не удалось отправить фотографию.';
}


/**
 * Определяет, нужно ли предложить отправку по электронной почте.
 *
 * Почта предлагается при проблемах с загрузкой,
 * файлом, обработкой или сохранением заявки.
 *
 * Обычные ошибки заполнения формы
 * пользователь должен исправить в самой форме.
 *
 * @param string $error_code Публичный код ошибки.
 *
 * @return bool
 */
function dilitant_land_photo_submission_offer_email(
    $error_code
) {
    $email_fallback_errors = array(
        'upload_failed',
        'invalid_file',
        'processing_failed',
        'save_failed',
    );

    return in_array(
        $error_code,
        $email_fallback_errors,
        true
    );
}


/**
 * Выводит форму отправки фотографии редакции.
 */
function dilitant_land_photo_submission_render_form() {
    $success = false;
    $error_code = '';

    if ( isset( $_GET['photo_submission'] ) ) {
        $success = (
            'success' === sanitize_key(
                wp_unslash( $_GET['photo_submission'] )
            )
        );
    }

    if ( isset( $_GET['photo_submission_error'] ) ) {
        $error_code = sanitize_key(
            wp_unslash(
                $_GET['photo_submission_error']
            )
        );
    }

    ?>
    <section
        id="photo-submission"
        class="photo-submission"
    >
        <div class="photo-submission__inner">

            <h2>Прислать фотографию</h2>

            <?php if ( $success ) : ?>

                <p
                    class="photo-submission__message
                           photo-submission__message--success"
                    role="status"
                >
                    Фотография отправлена редакции.
                </p>

            <?php endif; ?>

            <?php if ( '' !== $error_code ) : ?>

                <div
                    class="photo-submission__message
                           photo-submission__message--error"
                    role="alert"
                >
                    <p>
                        <?php
                        echo esc_html(
                            dilitant_land_photo_submission_error_message(
                                $error_code
                            )
                        );
                        ?>
                    </p>

                    <?php
                    if (
                        dilitant_land_photo_submission_offer_email(
                            $error_code
                        )
                    ) :
                        ?>

                        <p>
                            Если проблема повторится,
                            отправьте фотографию письмом на
                            <a href="mailto:relokantinc@gmail.com">
                                relokantinc@gmail.com
                            </a>.
                        </p>

                    <?php endif; ?>
                </div>

            <?php endif; ?>

            <p>
                Вы можете прислать фотографию,
                связанную с историей Армении.
            </p>

            <form
                class="photo-submission__form"
                method="post"
                enctype="multipart/form-data"
            >
                <?php
                wp_nonce_field(
                    'dilitant_land_photo_submission',
                    'dilitant_land_photo_submission_nonce'
                );
                ?>

                <p>
                    <label for="photo-submission-submitter-name">
                        Ваше имя
                    </label>

                    <input
                        type="text"
                        id="photo-submission-submitter-name"
                        name="submitter_name"
                        autocomplete="name"
                        required
                    >
                </p>

                <p>
                    <label for="photo-submission-submitter-email">
                        Ваш email
                    </label>

                    <input
                        type="email"
                        id="photo-submission-submitter-email"
                        name="submitter_email"
                        autocomplete="email"
                        required
                    >
                </p>

                <p>
                    <label for="photo-submission-author-name">
                        Фотограф
                    </label>

                    <input
                        type="text"
                        id="photo-submission-author-name"
                        name="author_name"
                    >

                    <small>
                        Если фотограф неизвестен,
                        оставьте поле пустым.
                    </small>
                </p>

                <p>
                    <label for="photo-submission-approx-date">
                        Примерная дата фотографии
                    </label>

                    <input
                        type="text"
                        id="photo-submission-approx-date"
                        name="approx_date"
                        placeholder="Например: 1960-е годы"
                    >
                </p>

                <p>
                    <label for="photo-submission-place">
                        Место съёмки
                    </label>

                    <input
                        type="text"
                        id="photo-submission-place"
                        name="place"
                    >
                </p>

                <p>
                    <label for="photo-submission-source">
                        Источник фотографии
                    </label>

                    <input
                        type="text"
                        id="photo-submission-source"
                        name="source"
                        placeholder="Например: семейный архив"
                    >
                </p>

                <p>
                    <label for="photo-submission-description">
                        Что изображено на фотографии
                    </label>

                    <textarea
                        id="photo-submission-description"
                        name="description"
                        rows="6"
                    ></textarea>
                </p>

                <p>
                    <label for="photo-submission-publication-basis">
                        Основание для публикации
                    </label>

                    <textarea
                        id="photo-submission-publication-basis"
                        name="publication_basis"
                        rows="4"
                        required
                    ></textarea>

                    <small>
                        Например: фотография принадлежит вам,
                        получено разрешение владельца
                        или правообладателя.
                    </small>
                </p>

                <p>
                    <label>
                        <input
                            type="checkbox"
                            name="rights_confirmation"
                            value="1"
                            required
                        >

                        Я подтверждаю, что имею право
                        передать эту фотографию редакции
                        для рассмотрения и возможной публикации.
                    </label>
                </p>

                <p>
                    <label>
                        <input
                            type="checkbox"
                            name="credit_submitter"
                            value="1"
                        >

                        Указать моё имя при публикации
                        фотографии.
                    </label>
                </p>

                <p>
                    <label for="photo-submission-file">
                        Фотография
                    </label>

                    <input
                        type="file"
                        id="photo-submission-file"
                        name="photo"
                        accept="image/jpeg,image/png,image/webp"
                        required
                    >

                    <small>
                        JPEG, PNG или WebP.
                        Максимальный размер файла — 10 МБ.
                    </small>
                </p>

                <p>
                    <button
                        type="submit"
                        name="dilitant_land_photo_submission_submit"
                        value="1"
                    >
                        Отправить фотографию
                    </button>
                </p>

            </form>

        </div>
    </section>
    <?php
}