<?php

/**
 * Правила геймификации.
 *
 * Всё в конфиге, а не в коде контроллера: стоимость действия и
 * условия бейджа — это настройка, которую меняют без релиза и без
 * правок в логике начисления.
 *
 * Тон намеренно сдержанный: в LMS для взрослых «детские» медали и
 * анимированные конфетти мешают работать. Поэтому XP — молчаливый
 * счётчик прогресса, а бейджи названы достижениями, а не «медалями».
 */
return [

    /*
     * Сколько XP даёт действие.
     *
     * Числа подобраны так, чтобы курс средней длины (~40 уроков,
     * 1–2 экзамена, 3 работы) давал порядка 600–800 XP: это уровень
     * 4–5 по формуле ниже — заметно, но без ощущения «детсада».
     */
    'points' => [
        'lesson_completed' => 10,
        'exam_passed' => 30,
        'assignment_submitted' => 15,
        'course_completed' => 50,
        'certificate_issued' => 100,
    ],

    /*
     * Уровни.
     *
     * Порог растёт квадратично: level = floor(sqrt(xp / 100)) + 1.
     * Линейный порог давал бы десятки «уровней» в первую неделю, а
     * эта формула делает каждый следующий уровень заметно дороже
     * предыдущего. Формула живёт здесь, а не в шаблоне: страница
     * показывает «уровень N», и человек должен понимать, как
     * получить следующий.
     */
    'level' => [
        'divisor' => 100,
    ],

    /*
     * Бейджи.
     *
     * metric — имя агрегата из Gamification::metrics(), threshold —
     * порог. Условие одно: «агрегат >= порога». Сложные правила
     * («3 курса и 5 экзаменов») этой формой не описываются, и
     * правильно: их пришлось бы писать кодом в шаблоне страницы,
     * где им и место не по праву.
     */
    'badges' => [

        // Начало.
        'first_lesson' => [
            'title' => 'Первый урок',
            'hint' => 'Закройте первый урок',
            'icon' => 'mdi-play-circle-outline',
            'metric' => 'lessons',
            'threshold' => 1,
        ],
        'lessons_25' => [
            'title' => 'Двадцать пять',
            'hint' => 'Закройте 25 уроков',
            'icon' => 'mdi-book-check-outline',
            'metric' => 'lessons',
            'threshold' => 25,
        ],
        'lessons_100' => [
            'title' => 'Сотня',
            'hint' => 'Закройте 100 уроков',
            'icon' => 'mdi-book-open-page-variant-outline',
            'metric' => 'lessons',
            'threshold' => 100,
        ],

        // Экзамены.
        'first_exam' => [
            'title' => 'Первый экзамен',
            'hint' => 'Сдайте первый экзамен',
            'icon' => 'mdi-clipboard-check-outline',
            'metric' => 'exams',
            'threshold' => 1,
        ],
        'exams_5' => [
            'title' => 'Пять экзаменов',
            'hint' => 'Сдайте 5 экзаменов',
            'icon' => 'mdi-school-outline',
            'metric' => 'exams',
            'threshold' => 5,
        ],

        // Курсы.
        'course_complete' => [
            'title' => 'Курс закрыт',
            'hint' => 'Закройте курс целиком',
            'icon' => 'mdi-flag-checkered',
            'metric' => 'courses',
            'threshold' => 1,
        ],
        'courses_3' => [
            'title' => 'Три курса',
            'hint' => 'Закройте 3 курса',
            'icon' => 'mdi-map-marker-path',
            'metric' => 'courses',
            'threshold' => 3,
        ],
        'certificate' => [
            'title' => 'Сертификат получен',
            'hint' => 'Получите первый сертификат',
            'icon' => 'mdi-certificate-outline',
            'metric' => 'certificates',
            'threshold' => 1,
        ],

        // Работы.
        'first_assignment' => [
            'title' => 'Первая работа',
            'hint' => 'Сдайте первую домашнюю работу',
            'icon' => 'mdi-text-box-check-outline',
            'metric' => 'assignments',
            'threshold' => 1,
        ],

        // Регулярность.
        'streak_7' => [
            'title' => 'Неделя подряд',
            'hint' => 'Занимайтесь 7 дней подряд',
            'icon' => 'mdi-calendar-check-outline',
            'metric' => 'streak',
            'threshold' => 7,
        ],
        'streak_30' => [
            'title' => 'Месяц подряд',
            'hint' => 'Занимайтесь 30 дней подряд',
            'icon' => 'mdi-calendar-star',
            'metric' => 'streak',
            'threshold' => 30,
        ],

        // Объём.
        'xp_500' => [
            'title' => 'Пятьсот',
            'hint' => 'Наберите 500 XP',
            'icon' => 'mdi-chart-line',
            'metric' => 'xp',
            'threshold' => 500,
        ],
        'xp_2000' => [
            'title' => 'Две тысячи',
            'hint' => 'Наберите 2000 XP',
            'icon' => 'mdi-trophy-outline',
            'metric' => 'xp',
            'threshold' => 2000,
        ],
    ],
];