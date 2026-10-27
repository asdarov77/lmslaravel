<?php

namespace App\Support\Tutor;

/**
 * Поиск релевантного фрагмента материала.
 *
 * Два хранилища, одно поведение:
 *
 *  - PostgresVectorStore — полнотекстовый поиск по tsvector, работает
 *    всегда и ничего не требует. Вариант B из плана.
 *  - SatelliteVectorStore — векторный поиск в ChromaDB внутри
 *    Python-сателлита, коллекция на курс. Вариант A.
 *
 * Выбор делает Indexer: если сателлит настроен и отвечает, работает он,
 * иначе — Postgres. Разница между ними важна и её не стоит прятать:
 * tsvector ищет по словам, вектор — по смыслу. Запрос «что делать при
 * отказе гидросистемы» не пересечётся ни с одним словом текста, и
 * Postgres найдёт фрагмент только если пользователь назвал термин из него.
 *
 * Идентификаторы чанков в обоих случаях одинаковы и стабильны
 * (material_id + seq), поэтому переиндексация идемпотентна: повторный
 * запуск не плодит дубли, а обновляет существующие записи.
 */
interface TutorVectorStore
{
    /** Готов ли поиск к работе. */
    public function available(): bool;

    /**
     * Перезаписать индекс фрагментов материала.
     *
     * Полная перезапись, а не дополнение: после перезаливки курса
     * фрагменты меняются, и дописывание оставляло бы в индексе текст,
     * которого больше нет.
     *
     * @param  array<int,array{seq:int,content:string}>  $chunks
     * @return array{indexed: int, backend: string}
     */
    public function index(int $materialId, array $chunks): array;

    /**
     * Найти релевантные фрагменты материала.
     *
     * @param  array<int,int>  $excludeSeq  фрагменты, которые уже использованы
     * @return array<int,array{seq:int,score:float}>
     */
    public function search(int $materialId, string $query, int $limit, array $excludeSeq = []): array;

    /** Человекочитаемое имя бэкенда — для диагностики. */
    public function name(): string;
}