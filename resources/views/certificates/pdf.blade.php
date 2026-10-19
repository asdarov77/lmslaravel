<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $fio }} — {{ $course }}</title>
    <style>
        @page {
            margin: 12mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
        }
        .cert-sheet {
            border: 3px double #000;
            padding: 48px;
            max-width: 100%;
        }
        .cert-sheet__head {
            border-bottom: 1px solid #000;
            padding-bottom: 24px;
            text-align: center;
            margin-bottom: 32px;
        }
        .cert-sheet__logo {
            max-height: 80px;
            margin-bottom: 16px;
        }
        .cert-sheet__org {
            color: #333;
            font-size: 0.875rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin: 0 0 12px;
        }
        .cert-sheet__title {
            font-size: 1.75rem;
            letter-spacing: 0.04em;
            margin: 12px 0 0;
            text-transform: uppercase;
        }
        .cert-sheet__lead {
            color: #333;
            margin-top: 32px;
        }
        .cert-sheet__fio {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 8px 0 24px;
        }
        .cert-sheet__course {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 8px 0;
        }
        .cert-sheet__module {
            color: #333;
            margin-top: 8px;
        }
        .cert-sheet__facts {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px 32px;
            margin-top: 32px;
        }
        .cert-sheet__facts dt {
            color: #333;
            font-size: 0.875rem;
        }
        .cert-sheet__facts dd {
            font-weight: 600;
            margin: 0;
        }
        .cert-sheet__note {
            margin-top: 32px;
            padding-top: 16px;
            border-top: 1px solid #ccc;
        }
        .cert-sheet__foot {
            border-top: 1px solid #000;
            margin-top: 48px;
            padding-top: 24px;
        }
        .cert-sheet__code {
            display: flex;
            align-items: baseline;
            gap: 16px;
            justify-content: center;
            margin-bottom: 24px;
        }
        .cert-sheet__code-label {
            color: #333;
            font-size: 0.875rem;
        }
        .cert-sheet__code-value {
            font-family: monospace;
            font-size: 1.125rem;
            letter-spacing: 0.16em;
        }
        .cert-sheet__sign {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            margin-top: 24px;
        }
        .cert-sheet__sign-line {
            width: 200px;
            border-bottom: 1px solid #000;
        }
        .cert-sheet__sign-name {
            font-weight: 600;
        }
        .cert-sheet__sign-title {
            color: #333;
            font-size: 0.875rem;
        }
        .cert-sheet__verify {
            color: #333;
            font-size: 0.75rem;
            margin-top: 16px;
            text-align: center;
        }
    </style>
</head>
<body>
    <article class="cert-sheet">
        <header class="cert-sheet__head">
            @if (!empty($logo_url))
                <img src="{{ $logo_url }}" class="cert-sheet__logo" alt="" />
            @endif
            <p class="cert-sheet__org">{{ $organization ?? 'Организация' }}</p>
            <h1 class="cert-sheet__title">Сертификат</h1>
        </header>

        <p class="cert-sheet__lead">Настоящим удостоверяется, что</p>
        <p class="cert-sheet__fio">{{ $fio }}</p>

        <p class="cert-sheet__lead">успешно завершил обучение по курсу</p>
        <p class="cert-sheet__course">{{ $course }}</p>

        @if (!empty($module_title))
            <p class="cert-sheet__module">Модуль: {{ $module_title }}</p>
        @endif

        <dl class="cert-sheet__facts">
            <div>
                <dt>Период обучения</dt>
                <dd>{{ $study_from }} — {{ $study_to }}</dd>
            </div>
            <div>
                <dt>Уроки</dt>
                <dd>{{ $lessons_done }} / {{ $lessons_total }}</dd>
            </div>
            @if (!empty($exams_total))
                <div>
                    <dt>Экзамены</dt>
                    <dd>{{ $exams_passed }} / {{ $exams_total }}</dd>
                </div>
            @endif
            <div>
                <dt>Дата выдачи</dt>
                <dd>{{ $issued_at }}</dd>
            </div>
        </dl>

        @if (!empty($footer_note))
            <p class="cert-sheet__note">{{ $footer_note }}</p>
        @endif

        <footer class="cert-sheet__foot">
            <div class="cert-sheet__code">
                <span class="cert-sheet__code-label">Код проверки:</span>
                <span class="cert-sheet__code-value">{{ $code }}</span>
            </div>
            @if (!empty($signatory_name))
                <div class="cert-sheet__sign">
                    <span class="cert-sheet__sign-line"></span>
                    <span class="cert-sheet__sign-name">{{ $signatory_name }}</span>
                    @if (!empty($signatory_title))
                        <span class="cert-sheet__sign-title">{{ $signatory_title }}</span>
                    @endif
                </div>
            @endif

            <p class="cert-sheet__verify">Подлинность подтверждается по коду на {{ config('app.url') }}/certificates/verify/{{ $code }}</p>
        </footer>
    </article>
</body>
</html>