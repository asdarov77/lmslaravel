<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Папки файлового менеджера.
 *
 * Папка принадлежит пользователю и вложена в другую папку того же
 * пользователя: parent_id — самоссылка. Глубина вложенности не
 * ограничена, но каждый уровень проходит проверку на зацикливание при
 * переносе (см. App\Support\FileManager\Location::assertNoCycle).
 *
 * Почему папка в базе, а не «просто каталог на диске»: каталог на диске
 * не отличает свою папку от мусора, оставшегося после сбоя, и не знает
 * владельца. С таблицей список папок — один запрос, права проверяются по
 * user_id, а дисковое содержимое можно сверить и починить отдельной
 * командой.
 *
 * Внешнего ключа на parent_id намеренно нет (и у файлов тоже): база в
 * проекте обходится без внешних ключей (см. ClearDBController), а
 * удаление поддерева выполняется кодом в одной транзакции.
 */
class CreateFileFoldersTable extends Migration
{
    public function up(): void
    {
        Schema::create('file_folders', function (Blueprint $table) {
            $table->id();

            // Владелец. Составная уникальность ниже идёт по user_id,
            // поэтому одного индекса на user_id мало: список папок
            // пользователя читается одним запросом и по одному полю.
            $table->unsignedBigInteger('user_id');

            // null — папка в корне пользователя.
            $table->unsignedBigInteger('parent_id')->nullable();

            $table->string('name');

            $table->timestamps();

            $table->index(['user_id', 'parent_id'], 'file_folders_user_parent_idx');

            // Две папки с одинаковым именем в одном каталоге — это
            // состояние, в котором пользователь не может выбрать, что
            // открыть, а удаление одной молча удалит не ту. Запрещаем
            // на уровне базы.
            //
            // ВНИМАНИЕ: в PostgreSQL unique не считает NULL равным NULL,
            // поэтому две папки в КОРНЕ с одинаковым именем база не
            // поймает. Корневая уникальность проверяется в коде
            // (Location::assertNameFree) — оба уровня нужны.
            $table->unique(['user_id', 'parent_id', 'name'], 'file_folders_unique_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_folders');
    }
}
