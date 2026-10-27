<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Незавершённые загрузки файлового менеджера.
 *
 * Строка появляется на «init» и живёт до «complete» либо до истечения
 * FILE_MANAGER_UPLOAD_TTL. Смысл строки — не учёт файла (файл ещё не
 * существует), а адрес временного каталога, который разбирается на
 * чанки, и возможность продолжить загрузку после обрыва.
 *
 * Ключ — строка upload_id, а не autoincrement: он попадает в URL
 * удаления и в ответ клиенту, и по нему клиент после перезагрузки
 * страницы узнаёт, что загрузка уже идёт. Числовой id для этого
 * пришлось бы угадывать, а перечислять чужие сессии перебором не
 * захочется.
 *
 * Отпечаток fingerprint — sha256 от (user_id, имя, размер, mtime).
 * Он позволяет начать с того же места: клиент после перезагрузки
 * страницы повторяет init с теми же метаданными файла и получает
 * upload_id и список уже принятых кусков вместо новой загрузки.
 * Отпечаток НЕ включает имя каталога: перенос незавершённого файла в
 * другую папку — осмысленное действие, и init его и обслуживает.
 */
class CreateFileUploadSessionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('file_upload_sessions', function (Blueprint $table) {
            $table->string('id', 40)->primary();

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('folder_id')->nullable();

            // Оригинальное имя после очистки (EntryName::clean).
            // Итоговое имя может отличаться: при совпадении в каталоге
            // добавляется «(2)», чтобы не затереть чужой файл.
            $table->string('filename');

            $table->unsignedBigInteger('size');
            $table->string('mime')->nullable();

            // Отпечаток незавершённой загрузки, см. описание миграции.
            $table->string('fingerprint', 64);

            // Размер чанка фиксируется в момент init. Если настройку
            // поменяли посреди загрузки, пересчёт total_chunks сбил бы
            // уже принятые куски; поэтому размер фиксируется.
            $table->unsignedInteger('chunk_bytes');
            $table->unsignedInteger('total_chunks');

            // Сколько байт уже принято. Держится в базе, а не вычисляется
            // по каталогу: на диске лежат куски, а сколько из них
            // действительно дописано — известно только отслеживанием
            // записи, и проверка «не больше объявленного» обязана быть
            // точной.
            $table->unsignedBigInteger('received_bytes')->default(0);

            // Отметка «файл уже собран и записан». Сессия остаётся в
            // таблице после complete: она нужна, чтобы повторный
            // complete из двух вкладок не собрал файл дважды.
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'fingerprint'], 'file_upload_sessions_fp_idx');
            $table->index('created_at', 'file_upload_sessions_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_upload_sessions');
    }
}
