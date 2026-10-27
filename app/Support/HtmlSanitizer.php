<?php

namespace App\Support;

/**
 * Серверная санитизация HTML материала.
 *
 * Материал материала/описания урока может быть набран в визуальном
 * редакторе: сохраняется только «безопасная» разметка. Это зеркальный
 * барьер фронтовой sanitizeMaterialHtml: у итогового документа нет
 * скриптов, инлайновых обработчиков и javascript:-ссылок, даже если
 * запрос пришёл мимо проверенной формы.
 */
class HtmlSanitizer
{
    public static function clean(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $clean = $html;

        // <script> целиком (включая содержимое), одиночные теги, iframe.
        $clean = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/is', '', $clean) ?? $clean;
        $clean = preg_replace('/<\/?(script|embed|object)\b[^>]*>/i', '', $clean) ?? $clean;
        $clean = preg_replace('/<iframe\b[^>]*>(?:[\s\S]*?<\/iframe>)?/i', '', $clean) ?? $clean;
        $clean = preg_replace('/<iframe\b[^>]*\/?>/i', '', $clean) ?? $clean;
        $clean = preg_replace('/<\/iframe>/i', '', $clean) ?? $clean;
        $clean = preg_replace('/ on[a-z]+="[^"]*"/i', '', $clean) ?? $clean;
        $clean = preg_replace("/ on[a-z]+='[^']*'/i", '', $clean) ?? $clean;
        $clean = str_ireplace('javascript:', '', $clean) ?? $clean;

        return $clean;
    }
}
