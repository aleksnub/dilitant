<?php
/**
 * Публичная форма отправки статьи.
 *
 * Выводит форму и понятные посетителю
 * сообщения о результате отправки.
 *
 * @package Dilitant_Land
 */


/**
 * Возвращает публичное сообщение об ошибке отправки.
 *
 * @param string $error_code Публичный код ошибки.
 *
 * @return string
 */
function dilitant_land_article_submission_error_message(
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

        'rights_not_confirmed' =>
            'Подтвердите право передать рукопись редакции.',

        'file_missing' =>
            'Выберите файл статьи для отправки.',

        'upload_failed' =>
            'Не удалось загрузить файл статьи.',

        'invalid_file' =>
            'Не удалось принять файл. Используйте документ DOC или DOCX размером не более 10 МБ.',

        'processing_failed' =>
            'Не удалось обработать файл статьи.',

        'save_failed' =>
            'Не удалось сохранить заявку.',
    );

    if ( isset( $messages[ $error_code ] ) ) {
        return $messages[ $error_code ];
    }

    return 'Не удалось отправить статью.';
}


/**
 * Выводит форму отправки статьи редакции.
 */
function dilitant_land_article_submission_render_form() {
    $success    = false;
    $error_code = '';

    if ( isset( $_GET['article_submission'] ) ) {
        $success = (
            'success' === sanitize_key(
                wp_unslash( $_GET['article_submission'] )
            )
        );
    }

    if ( isset( $_GET['article_submission_error'] ) ) {
        $error_code = sanitize_key(
            wp_unslash(
                $_GET['article_submission_error']
            )
        );
    }

    ?>
    <section
        id="article-submission"
        class="article-submission"
    >
        <div class="article-submission__inner">

            <h2>Прислать статью</h2>

            <?php if ( $success ) : ?>

                <p
                    class="article-submission__message
                           article-submission__message--success"
                    role="status"
                >
                    Статья отправлена редакции.
                </p>

            <?php endif; ?>

            <?php if ( '' !== $error_code ) : ?>

                <div
                    class="article-submission__message
                           article-submission__message--error"
                    role="alert"
                >
                    <p>
                        <?php
                        echo esc_html(
                            dilitant_land_article_submission_error_message(
                                $error_code
                            )
                        );
                        ?>
                    </p>
                </div>

            <?php endif; ?>

            <p>
                Вы можете прислать редакции авторскую статью
                по истории Армении.
            </p>

            <form
                class="article-submission__form"
                method="post"
                enctype="multipart/form-data"
            >
                <?php
                wp_nonce_field(
                    'dilitant_land_article_submission',
                    'dilitant_land_article_submission_nonce'
                );
                ?>

                <p>
                    <label for="article-submission-submitter-name">
                        Ваше имя
                    </label>

                    <input
                        type="text"
                        id="article-submission-submitter-name"
                        name="article_submitter_name"
                        autocomplete="name"
                        required
                    >
                </p>

                <p>
                    <label for="article-submission-submitter-email">
                        Ваш email
                    </label>

                    <input
                        type="email"
                        id="article-submission-submitter-email"
                        name="article_submitter_email"
                        autocomplete="email"
                        required
                    >
                </p>

                <p>
                    <label for="article-submission-title">
                        Название статьи
                    </label>

                    <input
                        type="text"
                        id="article-submission-title"
                        name="article_title"
                    >
                </p>

                <p>
                    <label for="article-submission-comment">
                        Комментарий редакции
                    </label>

                    <textarea
                        id="article-submission-comment"
                        name="article_submitter_comment"
                        rows="5"
                    ></textarea>
                </p>

                <p>
                    <label>
                        <input
                            type="checkbox"
                            name="article_rights_confirmation"
                            value="1"
                            required
                        >

                        Я подтверждаю, что имею право
                        передать эту рукопись редакции
                        для рассмотрения и возможной публикации.
                    </label>
                </p>

                <p>
                    <label for="article-submission-file">
                        Файл статьи
                    </label>

                    <input
                        type="file"
                        id="article-submission-file"
                        name="article_file"
                        accept=".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                        required
                    >

                    <small>
                        DOC или DOCX.
                        Максимальный размер файла — 10 МБ.
                    </small>
                </p>

                <p>
                    <button
                        type="submit"
                        name="dilitant_land_article_submission_submit"
                        value="1"
                    >
                        Отправить статью
                    </button>
                </p>

            </form>

        </div>
    </section>
    <?php
}