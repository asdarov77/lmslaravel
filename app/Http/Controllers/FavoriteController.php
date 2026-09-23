<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;
use App\Models\Favorite;
use App\Models\Aukstructure;

class FavoriteController extends Controller
{   

    public function index()
    {
        $favorites = Favorite::all();
        //return $favorites;
        return response()->json(['favorites' => $favorites], 200);
    }

    public function fav_add(Request $request)
    {
        // Проверяем, есть ли уже запись в таблице favorites
         $isFavorite = Favorite::where('user_id', Auth::user()->id)
         ->where('course_id', $request->course_id)
         ->exists();

        // Если запись уже существует, возвращаем ошибку
        if ($isFavorite) {
             return response()->json(['error' => 'Эта страница уже добавлена в избранное'], 400);
        }

        $favorite = new Favorite();
        $favorite->user_id = Auth::user()->id;
        $favorite->course_id = $request->course_id;
        $favorite->title = $request->title;
        $favorite->save();
    
       // return $favorite;
        return response()->json(['success' => true]); 

    }


    public function remove($course_id)
    {
        // Получаем текущего пользователя
        $user = Auth::user();

        // Ищем запись в таблице favorites
        $favorite = Favorite::where('user_id', Auth::user()->id)
        ->where('course_id', $course_id)->first();

        // Если запись не найдена, возвращаем ошибку
        if (!$favorite) {
            return response()->json(['error' => 'Эта страница не найдена в избранном'], 400);
        }

        // Удаляем запись из таблицы favorites
        $favorite->delete();

        // Возвращаем успешный ответ
        return response()->json(['success' => 'Страница успешно удалена из избранного'], 200);
    }


    
}
