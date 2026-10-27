<!doctype html>
<html lang="ru">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Восстановление пароля</title>
  </head>
  <body style="margin:0;padding:24px;background:#f3f5f7;font-family:Arial,sans-serif;color:#1b2733">
    <div style="max-width:520px;margin:0 auto;background:#fff;border:1px solid #dfe4ea;border-radius:8px;padding:24px">
      <h1 style="margin:0 0 12px;font-size:18px">Восстановление пароля</h1>
      <p style="margin:0 0 16px;font-size:14px">
        Здравствуйте, {{ $fio }}!<br>
        Вы запросили восстановление пароля. Перейдите по ссылке ниже,
        чтобы задать новый пароль.
      </p>
      <p style="margin:0 0 16px">
        <a href="{{ $url }}"
           style="display:inline-block;background:#3b6fd4;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;font-size:14px">
          Задать новый пароль
        </a>
      </p>
      <p style="margin:0;font-size:12px;color:#5b6b7c">
        Ссылка действует {{ $ttl }} минут. Если вы не запрашивали восстановление,
        просто проигнорируйте это письмо — пароль не изменится.
      </p>
    </div>
  </body>
</html>
