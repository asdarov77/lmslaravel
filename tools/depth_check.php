<?php
// Утилита: построчный баланс {} и () вне строковых литералов.
// Использование: php tools/depth_check.php <file> [from] [to]
$file = $argv[1] ?? null;
$from = (int)($argv[2] ?? 1);
$to = (int)($argv[3] ?? PHP_INT_MAX);
if (!$file) { fwrite(STDERR, "usage: depth_check.php file [from] [to]\n"); exit(2); }

$src = file_get_contents($file);
$tokens = token_get_all($src);
// Собираем карту "номер строки -> дельта глубины" только по реальным скобкам
// (токены T_*, а не строки/комментарии).
$delta = [];
foreach ($tokens as $t) {
    if (is_array($t)) continue; // строковые токены: '{','(','}'...
    $line = -1;
}
// Проще: пройдём по массиву строк исходника, вырезав строковые литералы
// эвристикой через tokenizer-карту позиций.
$inStringLines = [];
$pos = 0;
foreach ($tokens as $i => $t) {
    if (is_array($t) && in_array($t[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML, T_COMMENT, T_DOC_COMMENT], true)) {
        $startLine = $t[2];
        $endLine = $startLine + substr_count($t[1], "\n");
        for ($l = $startLine; $l <= $endLine; $l++) $inStringLines[$l] = true;
    }
}
$d = 0;
foreach (explode("\n", $src) as $idx => $line) {
    $ln = $idx + 1;
    if (!isset($inStringLines[$ln])) {
        $code = preg_replace('~//[^\n]*|#[^\n]*~', '', $line);
        $d += substr_count($code, '{') + substr_count($code, '(')
            - substr_count($code, '}') - substr_count($code, ')');
    }
    if ($ln >= $from && $ln <= $to) {
        echo $ln . ' d=' . $d . ' | ' . trim($line) . "\n";
    }
}
