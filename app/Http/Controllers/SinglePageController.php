<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Отдаёт view('app') — оболочку Vue-приложения.
 *
 * Используется на '/' и как fallback для всех не-API маршрутов SPA.
 * Backend-маршрутов, отдающих что-то кроме оболочки, здесь нет.
 */
class SinglePageController extends Controller
{
    public function index() {
    return view('app');
    //return 'ok';
    }
}
