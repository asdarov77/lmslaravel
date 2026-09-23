## UI таблицы и формы

Общие принципы
- Сортировка по клику на заголовок, сохранение состояния фильтров в query
- Массовые действия с подтверждением и оптимистичным обновлением
- Состояния: загрузка (скелетоны), пусто, ошибка (retry)

Курсы (Courses)
- Таблица: id:number(sort), title:string(sort/filter), category:string(filter), aircraft:string(filter), status:enum(sort/filter), lessons:number, updated_at:datetime(sort)
- Действия строки: Просмотр, Редактировать, Публиковать/Снять, Удалить
- Массовые: Публиковать, Снять, Удалить
- Форма: title(required, max:255), description(max:4000), category_id(required), aircraft_id(optional), status(enum: draft|published), files[]
- Состояния:
  - загрузка: скелетоны строк 5–10 шт.
  - пусто: «Нет курсов», кнопка «Создать курс»
  - ошибка: алерт с сообщением, кнопка «Повторить»
  - пагинация: серверная, meta.pagination
- Валидация: title required, max 255; category_id required; status in [draft,published]

Занятия (Lessons)
- Таблица: id, course:title(link), chapter, duration, visible:bool
- Действия: Редактировать, Скрыть/Показать, Удалить

Пользователи (Users)
- Таблица: id, fio, role, group, last_login
- Действия: Сброс пароля, Изменить роль/права, Удалить
- Форма: fio(required), role(required enum), password(required on create, min:6), group_id
- Состояния:
  - загрузка: скелетоны
  - пусто: «Нет пользователей», кнопка «Добавить» (для админов)
  - ошибка: алерт, retry
  - пагинация: серверная, meta.pagination
- Валидация: fio required; role in [Администратор,Инструктор,Обучаемый]; password min 6

Группы/Зачисления
- Таблица групп: id, name, members
- Таблица зачислений: id, group, course, status
- Действия: Зачислить/Отчислить, Импорт CSV

Отчеты
- Таблица: Пользователь, Курс, Прогресс, Результат теста, Дата

Диалоги подтверждения
- Удалить: требует ввод «DELETE» для перманентного
- Публиковать/Снять: чекбокс уведомления пользователей


