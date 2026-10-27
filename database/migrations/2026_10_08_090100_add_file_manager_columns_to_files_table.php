<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Файловый менеджер: папка, путь, размер и тип файла.
 *
 * Существующие записи files заполнены НЕ будут. У них путь на диске
 * выводился из типа и имени (FilesController::index/store), каталога
 * не было, а часть файлов лежит по двум разным схемам. Их
 * принадлежность менеджеру не выводится — и не должна: придумывать
 * путь для старой записи значило бы либо указать не туда (файл
 * «потеряется», но останется в списке), либо переименовать лишнее.
 * Поэтому path = NULL читается как «файл вне файлового менеджера», и
 * менеджер такие записи не показывает. Старые endpoints продолжают
 * работать как раньше.
 *
 * Имя колонки path, а не disk_path, потому что хранится путь ОТНОСИТЕЛЬНО
 * корня пользователя: абсолютный путь в базе ломается при переносе
 * каталога приложения, а абсолютный префикс не должен зависеть от
 * машины.
 */
class AddFileManagerColumnsToFilesTable extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            // null — файл в корне пользователя (NULL, а не «корень
            // сам по себе» важно для совместимости со старыми строками,
            // у которых папки не было вовсе).
            $table->unsignedBigInteger('folder_id')->nullable()->after('user_id');

            // Путь файла относительно корня пользователя, включая имя.
            // null — запись вне менеджера (см. описание миграции).
            $table->string('path')->nullable()->after('folder_id');

            $table->unsignedBigInteger('size')->nullable()->after('path');
            $table->string('mime')->nullable()->after('size');

            $table->index(['user_id', 'folder_id'], 'files_user_folder_idx');
            $table->index('path', 'files_path_idx');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropIndex('files_user_folder_idx');
            $table->dropIndex('files_path_idx');

            $table->dropColumn(['folder_id', 'path', 'size', 'mime']);
        });
    }
}
