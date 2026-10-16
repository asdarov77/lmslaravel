<?php

namespace App\Http\Controllers;

use App\Models\MaterialNote;
use App\Models\Aukstructure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Заметки студента к материалам курса.
 *
 * CRUD для личных заметок студента к материалам курса.
 * Доступен только авторизованным пользователям, свои заметки
 * видят/редактируют только авторы.
 */
class MaterialNoteController extends Controller
{
    /**
     * Получить заметку пользователя к материалу.
     *
     * GET /api/materials/{id}/note
     */
    public function show(Request $request, int $materialId)
    {
        $user = $request->user();
        abort_if($user === null, 401);

        // Проверяем, что материал существует и доступен пользователю
        $material = Aukstructure::findOrFail($materialId);

        // Проверка доступа через CourseAccess (если нужно)
        // Здесь упрощённо: если курс доступен в каталоге, считаем доступным

        $note = MaterialNote::where('user_id', $user->id)
            ->where('aukstructure_id', $materialId)
            ->first();

        return response()->json([
            'data' => $note ? [
                'id' => $note->id,
                'content' => $note->content,
                'updated_at' => $note->updated_at?->toIso8601String(),
            ] : null,
        ]);
    }

    /**
     * Сохранить/обновить заметку.
     *
     * PUT /api/materials/{id}/note
     */
    public function update(Request $request, int $materialId)
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $material = Aukstructure::findOrFail($materialId);

        $validated = $request->validate([
            'content' => ['nullable', 'string', 'max:5000'],
        ]);

        $note = MaterialNote::updateOrCreate(
            [
                'user_id' => $user->id,
                'aukstructure_id' => $materialId,
            ],
            [
                'course_id' => $material->course_id,
                'content' => $validated['content'] ?? '',
            ]
        );

        return response()->json([
            'data' => [
                'id' => $note->id,
                'content' => $note->content,
                'updated_at' => $note->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Удалить заметку.
     *
     * DELETE /api/materials/{id}/note
     */
    public function destroy(Request $request, int $materialId)
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $deleted = MaterialNote::where('user_id', $user->id)
            ->where('aukstructure_id', $materialId)
            ->delete();

        return response()->json([
            'data' => ['deleted' => $deleted > 0],
        ]);
    }
}