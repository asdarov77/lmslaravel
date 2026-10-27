"""Сателлит тренажёра: индексация и векторный поиск по материалам курсов.

Зачем он нужен. На машине пользователя нет расширения pgvector, а ставить
его — отдельная задача администрирования Postgres. ChromaDB внутри
Python-сервиса решает то же самое, не трогая схему БД.

Границы ответственности. Сателлит НЕ решает, что пользователю можно
видеть: он получает уже проверенный material_id и отвечает только за поиск
по тексту. Права остаются в Laravel. Коллекция создаётся на курс, а не на
пользователя, поэтому чужие материалы сюда не попадают даже при ошибке в
вызывающем коде — их просто не запрашивали.

Три маршрута:
    GET  /health    — диагностика: Chroma доступна, модель загружена
    POST /ingest    — upsert фрагментов (идемпотентно по sha1)
    POST /search    — top-k по смыслу, с исключением уже использованных
"""

from __future__ import annotations

import logging
import os
from typing import Any

from fastapi import FastAPI
from pydantic import BaseModel, Field

logging.basicConfig(level=logging.INFO)
log = logging.getLogger("tutor.satellite")

app = FastAPI(title="Tutor satellite", version="0.1.0")

# --------------------------------------------------------------------------
# Модель эмбеддингов.
#
# multilingual-e5-small: лёгкий, русский, требует префиксов
# passage:/query:. Без них качество падает заметно — это не формальность.
# --------------------------------------------------------------------------
EMBED_MODEL = os.getenv("TUTOR_EMBED_MODEL", "multilingual-e5-small")
CHROMA_PATH = os.getenv("TUTOR_CHROMA_PATH", "./.tutor-chroma")

_model = None
_chroma = None


def get_model():
    """Модель загружается лениво и один раз.

    Загрузка на старте (в add_middleware) роняла бы процесс на каждом
    рестарте uvicorn с воркерами: модель грузится в каждом. Ленивая
    загрузка даёт один раз на процесс, а health отвечает и до неё.
    """
    global _model
    if _model is None:
        from sentence_transformers import SentenceTransformer

        log.info("загружаю модель эмбеддингов: %s", EMBED_MODEL)
        _model = SentenceTransformer(EMBED_MODEL)
        log.info("модель загружена")
    return _model


def get_chroma():
    global _chroma
    if _chroma is None:
        import chromadb
        from chromadb.config import Settings

        _chroma = chromadb.PersistentClient(
            path=CHROMA_PATH,
            settings=Settings(anonymized_telemetry=False, allow_reset=True),
        )
    return _chroma


def collection(name: str):
    return get_chroma().get_or_create_collection(
        name=name,
        # cosine: векторы e5 уже нормированы, и косинус — естественная
        # мера для них.
        metadata={"hnsw:space": "cosine"},
    )


class IngestRequest(BaseModel):
    collection: str = Field(..., min_length=1, max_length=200)
    documents: list[dict[str, Any]]


class SearchRequest(BaseModel):
    collection: str = Field(..., min_length=1, max_length=200)
    query: str = Field(..., min_length=1)
    k: int = Field(5, ge=1, le=100)
    exclude_seq: list[int] = Field(default_factory=list)


@app.get("/health")
def health() -> dict[str, Any]:
    """Диагностика для страницы настроек.

    Отвечает 200 всегда и честно сообщает, что именно сломано. Раньше
    «сервис недоступен» и «Chroma не установлена» выглядели одинаково, и
    по логам было не понять, куда смотреть.
    """
    out: dict[str, Any] = {"service": "ok", "chroma": False, "model": EMBED_MODEL, "model_loaded": False, "error": None}

    try:
        get_chroma()
        out["chroma"] = True
    except Exception as exc:  # noqa: BLE001 — диагностика не должна падать
        out["error"] = f"chromadb: {exc}"

    try:
        get_model()
        out["model_loaded"] = True
    except Exception as exc:  # noqa: BLE001
        out["error"] = (out["error"] + "; " if out["error"] else "") + f"sentence-transformers: {exc}"

    return out


@app.post("/ingest")
def ingest(req: IngestRequest) -> dict[str, Any]:
    """Положить или обновить фрагменты.

    Идентификаторы приходят от Laravel (sha1 от material_id и seq), поэтому
    повторная индексация обновляет записи, а не создаёт дубли. Это то, что
    нужно при перезаливке курса.
    """
    if not req.documents:
        return {"count": 0}

    col = collection(req.collection)
    model = get_model()

    ids = [str(d["id"]) for d in req.documents]
    texts = [str(d["text"]) for d in req.documents]
    metadatas = [dict(d.get("metadata") or {}) for d in req.documents]

    # Префикс passage: обязателен для e5, иначе вектор «про текст»
    # сравнивается с вектором «про вопрос».
    col.upsert(
        ids=ids,
        documents=texts,
        metadatas=metadatas,
        embeddings=model.encode(["passage: " + t for t in texts], normalize_embeddings=True).tolist(),
    )

    return {"count": len(ids)}


@app.post("/search")
def search(req: SearchRequest) -> dict[str, Any]:
    """Top-k релевантных фрагментов с исключением уже использованных."""
    col = collection(req.collection)
    model = get_model()

    # Запрашиваем больше, чем нужно: часть результатов отсеется по
    # exclude_seq уже после выборки. Если этого не учесть, сессия
    # получала бы меньше фрагментов, чем просила, и материал мог бы
    # «кончиться» при непустом индексе.
    total = int(col.count())
    fetch = max(req.k + len(req.exclude_seq), min(total, 200))

    if fetch == 0:
        # Пустая коллекция: это не ошибка, а «материал ещё не проиндексирован».
        return {"results": []}

    result = col.query(
        query_embeddings=[model.encode(["query: " + req.query], normalize_embeddings=True).tolist()[0]],
        n_results=fetch,
        include=["documents", "metadatas", "distances"],
    )

    metadatas = (result.get("metadatas") or [[]])[0]
    distances = (result.get("distances") or [[]])[0]

    out = []
    for meta, dist in zip(metadatas, distances):
        seq = int((meta or {}).get("seq", 0))
        if seq <= 0 or seq in req.exclude_seq:
            continue
        out.append({"metadata": dict(meta or {}), "score": round(1.0 - float(dist), 6)})

    out.sort(key=lambda r: r["score"], reverse=True)
    return {"results": out[: req.k]}
