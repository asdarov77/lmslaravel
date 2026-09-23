<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Отображает список настроек.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $settings = Setting::all();

        return response()->json($settings);
    }

    /**
     * Обновляет настройки.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $settings = $request->all();

        foreach ($settings as $setting) {
            $name = $setting['name'];
            $value = $setting['value'];

            Setting::where('name', $name)->update(['value' => $value]);
        }

        return response()->json(['message' => 'Настройки успешно обновлены']);
    }
}
