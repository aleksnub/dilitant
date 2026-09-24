<?php
/**
 * Front Page Template
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
<body <?php body_class(); ?>>
<button
    class="site-menu-toggle"
    type="button"
    aria-label="Открыть меню"
    aria-expanded="false"
    aria-controls="site-menu"
>
    <span></span>
    <span></span>
    <span></span>
</button>

<div class="site-menu-backdrop"></div>

<aside
    class="site-menu-panel"
    id="site-menu"
    aria-hidden="true"
>
    <button
        class="site-menu-close"
        type="button"
        aria-label="Закрыть меню"
    >×</button>

    <nav class="site-menu-nav" aria-label="Основная навигация">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a>
        <a href="<?php echo esc_url( home_url( '/#subscribe' ) ); ?>">Подписаться</a>
        <a href="<?php echo esc_url( home_url( '/diletant-armenia/' ) ); ?>">Дилетанты в Армении</a>
        <a href="<?php echo esc_url( home_url( '/delivery/' ) ); ?>">Доставка</a>
        <a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">О нас</a>
        <a href="<?php echo esc_url( home_url( '/contacts/' ) ); ?>">Контакты</a>
    </nav>
</aside>
<div class="hero">
    <div class="hero-slides">
        <section class="hero-slide hero-slide--1 is-active" style="background-image: url('<?php echo esc_url( get_template_directory_uri() . '/assets/images/01-rome.webp' ); ?>');">
            <div class="hero-overlay"></div>

        </section>
        <section class="hero-slide hero-slide--2" style="background-image: url('<?php echo esc_url( get_template_directory_uri() . '/assets/images/02-mashtots.webp' ); ?>');">
            <div class="hero-overlay"></div>

        </section>
        <section class="hero-slide hero-slide--3" style="background-image: url('<?php echo esc_url( get_template_directory_uri() . '/assets/images/03-columbus.webp' ); ?>');">
            <div class="hero-overlay"></div>

        </section>
        <section class="hero-slide hero-slide--4" style="background-image: url('<?php echo esc_url( get_template_directory_uri() . '/assets/images/04-eiffel.webp' ); ?>');">
            <div class="hero-overlay"></div>

        </section>
        <section class="hero-slide hero-slide--5" style="background-image: url('<?php echo esc_url( get_template_directory_uri() . '/assets/images/05-un.webp' ); ?>');">
            <div class="hero-overlay"></div>

        </section>
        <section class="hero-slide hero-slide--6" style="background-image: url('<?php echo esc_url( get_template_directory_uri() . '/assets/images/06-rocket.webp' ); ?>');">
            <div class="hero-overlay"></div>

        </section>
        <section class="hero-slide hero-slide--7" style="background-image: url('<?php echo esc_url( get_template_directory_uri() . '/assets/images/07-magazine.webp' ); ?>');">
            <div class="hero-overlay"></div>

        </section>
    </div>

    <div class="hero-cta">
        <a href="#subscribe">Подписаться</a>
    </div>
</div>

<section class="editorial" aria-labelledby="editorial-title">
    <div class="editorial__inner">
        <div class="editorial__label"><h1></h1>Популярный исторический журнал «Дилетант»</h1></div>

        <div class="editorial__grid">
            <div class="editorial__portrait" aria-hidden="true">
                <div class="editorial__portrait-frame">
                    <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/venediktov.webp' ); ?>" alt="">
                </div>
            </div>

            <div class="editorial__content">
                <p class="editorial__eyebrow">От редакции легендарного «Эха Москвы» — теперь и в Армении</p>
                <h2 id="editorial-title">Историю объясняют люди, которых знают</h2>
                <p class="editorial__lead">
                    В центре проекта — узнаваемая редакционная школа Алексея Венедиктова: разговор с читателем без назидания, уважение к фактам и умение связывать прошлое с сегодняшним днём.
                </p>
                <p>
                    «Дилетант» говорит об истории не как о музейной пыли, а как о живой памяти. Империи, открытия, войны, идеи, судьбы людей — всё это складывается в одну линию, которую важно видеть целиком.
                </p>

                <div class="editorial__quote">
                    <span>Главный смысл</span>
                    <strong>История становится понятной, когда за ней слышен человеческий голос.</strong>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="covers-carousel" aria-labelledby="covers-carousel-title">
    <div class="covers-carousel__inner">
        <div class="covers-carousel__header">
            <div class="covers-carousel__label">Обложки, которые становятся входом в эпоху</div>
            <h2 id="covers-carousel-title">Каждый номер — отдельная дверь в историю</h2>
            <p>Над каждым выпуском стоит редакция, умеющая превращать прошлое не в набор дат, а в человеческую драму, конфликт решений и цепочку последствий.</p>
        </div>

        <div class="covers-carousel__wrap">
            <div class="covers-carousel__track">
                <article class="covers-carousel__card">
                    <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/diletant1.jpg' ); ?>" alt="Обложка Дилетант №1">
                </article>
                <article class="covers-carousel__card">
                    <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/diletant2.jpg' ); ?>" alt="Обложка Дилетант №2">
                </article>
                <article class="covers-carousel__card">
                    <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/diletant3.jpg' ); ?>" alt="Обложка Дилетант №3">
                </article>
                <article class="covers-carousel__card">
                    <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/diletant4.jpg' ); ?>" alt="Обложка Дилетант №4">
                </article>
                <article class="covers-carousel__card">
                    <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/diletant5.jpg' ); ?>" alt="Обложка Дилетант №5">
                </article>
                <article class="covers-carousel__card">
                    <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/diletant6.jpg' ); ?>" alt="Обложка Дилетант №6">
                </article>
                <article class="covers-carousel__card">
                    <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/diletant7.jpg' ); ?>" alt="Обложка Дилетант №7">
                </article>
                <article class="covers-carousel__card">
                    <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/diletant8.jpg' ); ?>" alt="Обложка Дилетант №8">
                </article>
            </div>
        </div>
    </div>
</section>

<section id="subscribe" class="subscription" aria-labelledby="subscription-title">
    <div class="subscription__inner">
        <div class="subscription__header">
            <p class="subscription__label">Оформить подписку</p>
            <h2 id="subscription-title">Выберите свой способ читать «Дилетант»</h2>
            <p>
                Подписка подойдёт тем, кто хочет получать каждый новый номер без поиска по магазинам.
                Начните с одного месяца или сразу соберите личную историческую библиотеку на год.
            </p>
        </div>

        <div class="subscription__plans" aria-label="Варианты подписки">

        <article class="subscription-card">
        <div class="subscription-card__top">
            <p class="subscription-card__period">1 месяц</p>
            <h3>Попробовать</h3>
            <p class="subscription-card__note">Один свежий номер, чтобы познакомиться с журналом.</p>
        </div>

        <ul class="subscription-card__list">
            <li>1 выпуск журнала</li>
            <li>Доставка по Армении</li>
            <li>Без долгих обязательств</li>
            <li>3500 драм</li>
        </ul>

        <a class="subscription-card__button"
           href="https://shop.relokant.am/cart/?add-to-cart=676">
            Выбрать
        </a>
        </article>

        <article class="subscription-card">
        <div class="subscription-card__top">
            <p class="subscription-card__period">3 месяца</p>
            <h3>На сезон</h3>
            <p class="subscription-card__note">Три выпуска, чтобы войти в ритм и понять формат.</p>
        </div>

        <ul class="subscription-card__list">
            <li>3 выпуска журнала</li>
            <li>Доставка по Армении</li>
            <li>Удобный короткий срок</li>
            <li>9500 драм; -10 %</li>
        </ul>

        <a class="subscription-card__button"
           href="https://shop.relokant.am/cart/?add-to-cart=678">
            Выбрать
        </a>
        </article>

        <article class="subscription-card subscription-card--featured">
        <div class="subscription-card__badge">Самый популярный</div>

        <div class="subscription-card__top">
            <p class="subscription-card__period">6 месяцев</p>
            <h3>Читать регулярно</h3>
            <p class="subscription-card__note">Полгода историй, тем, авторов и новых выпусков.</p>
        </div>

        <ul class="subscription-card__list">
            <li>6 выпусков журнала</li>
            <li>Регулярная доставка</li>
            <li>Удобный срок без годового решения</li>
            <li>17000 драм; -15 %</li>
        </ul>

        <a class="subscription-card__button"
           href="https://shop.relokant.am/cart/?add-to-cart=680">
            Выбрать
        </a>
        </article>

        <article class="subscription-card">
        <div class="subscription-card__top">
            <p class="subscription-card__period">12 месяцев</p>
            <h3>Собрать год истории</h3>
            <p class="subscription-card__note">Годовая подписка для тех, кто хочет полную коллекцию.</p>
        </div>

        <ul class="subscription-card__list">
            <li>12 выпусков журнала</li>
            <li>Личная библиотека номеров</li>
            <li>Минимум забот на весь год</li>
            <li>28000 драм; -30 % до 31.09.26</li>
        </ul>

        <a class="subscription-card__button"
           href="https://shop.relokant.am/cart/?add-to-cart=681">
            Выбрать
        </a>
        </article>

    </div>

        <p class="subscription__afterword">
            Подписка — самый удобный способ получать каждый новый номер.
            А отдельный выпуск можно купить в городской точке продаж.В первую доставку возможно получение выпуска №122 или №116. График доставки отстаёт от Москвы на 1 месяц.
        </p>
    </div>
</section>

<section class="armenia-presence" aria-labelledby="armenia-presence-title">
    <div class="armenia-presence__inner">
        <div class="armenia-presence__header">
            <p class="armenia-presence__label">Распространение</p>
            <h2 id="armenia-presence-title">«Дилетант» в Армении</h2>
            <p>
                Журнал уже представлен в Ереване. Мы развиваем сеть распространения
                и открыты к сотрудничеству с площадками, где читают и обсуждают идеи.
            </p>
        </div>

        <div class="armenia-presence__grid">
            <article class="armenia-presence-card armenia-presence-card--sales">
                <p class="armenia-presence-card__kicker">Купить журнал</p>
                <h3>Бар «Релокант»</h3>
                <p class="armenia-presence-card__text">
                    Свежие номера журнала можно приобрести в точке продаж в центре Еревана.
                </p>

                <dl class="armenia-presence-card__details">
                    <div>
                        <dt>Адрес</dt>
                        <dd>ул. Ханджяна, д. 7</dd>
                    </div>
                    <div>
                        <dt>Ориентир</dt>
                        <dd>остановка «Педагогический университет»</dd>
                    </div>
                </dl>

                <a
                    class="armenia-presence-card__button"
                    href="https://yandex.com/maps/?text=%D0%95%D1%80%D0%B5%D0%B2%D0%B0%D0%BD%2C%20%D1%83%D0%BB.%20%D0%A5%D0%B0%D0%BD%D0%B4%D0%B6%D1%8F%D0%BD%D0%B0%207%2C%20%D0%A0%D0%B5%D0%BB%D0%BE%D0%BA%D0%B0%D0%BD%D1%82"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Открыть на Яндекс.Картах
                </a>
            </article>

            <article class="armenia-presence-card armenia-presence-card--partner">
                <p class="armenia-presence-card__kicker">Стать партнёром</p>
                <h3>Присоединиться к сети</h3>
                <p class="armenia-presence-card__text">
                    У вас книжный магазин, культурное пространство, галерея, кофейня
                    или другое место, где любят читать и обсуждать идеи?
                </p>
                <p>

                </p>
                <p>

                </p>

                <p class="armenia-presence-card__text">
                    Мы открыты к сотрудничеству и готовы обсуждать новые точки продаж
                    журнала «Дилетант» в Армении.
                </p>

                <a class="armenia-presence-card__button armenia-presence-card__button--ghost" href="mailto:info@relokant.am">
                    Связаться с редакцией
                    relokantinc@gmail.com
                </a>
            </article>
        </div>
    </div>
</section>
<section class="readings" id="readings">

    <div class="readings__header">
        <span class="section-label">Событие проекта</span>
        <h2>Дилетантские чтения</h2>
    </div>

    <div class="readings__content">

        <div class="readings__speakers">

            <div class="speaker">
                <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/venediktov2.webp' ); ?>" alt="Алексей Венедиктов">
                <div class="speaker__name">
                    Алексей Венедиктов
                </div>
            </div>

            <div class="speaker">
                <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/dimarski.webp' ); ?>" alt="Виталий Дымарский">
                <div class="speaker__name">
                    Виталий Дымарский
                </div>
            </div>

            <div class="speaker">
                <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/buntman.webp' ); ?>" alt="Сергей Бунтман">
                <div class="speaker__name">
                    Сергей Бунтман
                </div>
            </div>

        </div>

        <div class="readings__info">

             <?php

            $subscriptions = 43; 

            $goal = 500; 

            ?>

            <h3>Большая встреча читателей</h3>

            <p>

                Мы хотим, чтобы этот журнал стал не просто красивой картинкой, а основой сообщества читателей и авторов. Когда число подписчиков журнала достигнет первых пятисот, 

                мы проведём в Ереване

                <strong>«Дилетантские чтения»</strong>: при участие журналистов легендарной редакции

                <strong>«Эха Москвы»</strong>.

            </p>

            <p>
                Все действующие подписчики журнала получат
                <strong>скидку 50%</strong>
                на входной билет.
            </p>

            <div class="readings__counter">

                <div class="counter__title">
                    Уже оформлено подписок
                </div>

                <div class="counter__numbers">
                    <span><?php echo $subscriptions; ?></span>
                    <small>/ <?php echo $goal; ?></small>
                </div>

                <div class="counter__progress">
                    <div class="counter__fill"
                        style="width: <?php echo min(100, ($subscriptions / $goal) * 100); ?>%;">
                    </div>
                </div>

            </div>

            <a class="button button--gold" href="#subscribe">
                Оформить подписку
            </a>

        </div>

    </div>

</section>
<footer class="site-footer" aria-label="Подвал сайта">
    <div class="site-footer__inner">
        <nav class="site-footer__legal" aria-label="Юридическая навигация">
            <a href="/wp-content/themes/dilitant-land/about.html">О нас</a>
            <a href="/wp-content/themes/dilitant-land/user-agreement.html">Пользовательское соглашение</a>
            <a href="/wp-content/themes/dilitant-land/privacy-policy.html">Политика конфиденциальности</a>
            <a href="/wp-content/themes/dilitant-land/cookie-policy.html">Cookie</a>
            <a href="/wp-content/themes/dilitant-land/refund-policy.html">Возврат</a>
            <a href="/wp-content/themes/dilitant-land/contacts.html">Контакты</a>
        </nav>
        
        <div class="site-footer__bottom">
                        <nav
                class="site-footer__social"
                aria-label="Каналы проекта"
            >
                <a
                    href="https://t.me/diletantinarmenia"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Telegram
                </a>

                <a
                    href="https://www.youtube.com/channel/UCJ2zyXCQqCtX-TLAgnpOwUg"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    YouTube
                </a>
            </nav>
            <a class="site-footer__developer" href="https://r1.relokant.am/" target="_blank" rel="noopener noreferrer">
                Разработчик: R-1 Studio
            </a>

            <p class="site-footer__copyright">
                Copyright © 2026 Relokant Free Store
            </p>

            <img
                class="site-footer__payments"
                src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/pay.webp'); ?>"
                alt="Visa, Mastercard, ArCa, Google Pay"
                loading="lazy"
            >
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
