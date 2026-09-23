<?php

function parseHtml($html) {
    $dom = new DOMDocument();
    $dom->loadHTML($html);

    $questions = [];
    $answers = [];

    $elements = $dom->getElementsByTagName('p');
    foreach ($elements as $element) {
        $question = trim($element->nodeValue);
        if (strpos($question, '?') !== false) {
            $questions[] = $question;
        } else {
            $answers[] = $question;
        }
    }

    $results = [];
    for ($i = 0; $i < count($questions); $i++) {
        $results[] = [
            'question' => $questions[$i],
            'answer' => $answers[$i]
        ];
    }

    return $results;
}
