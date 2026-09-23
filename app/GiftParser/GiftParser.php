<?php

namespace App\GiftParser;
use App\Models\Category;
use App\Models\Question;
use App\Models\Answer;
use Illuminate\Support\Facades\DB;

// class GiftParser {
//     public function parse($gift) {
//         $lines = preg_split('/\R/', $gift);
//         $html = '';
//         $in_list = false;
//         $in_quote = false;
//         $list_level = 0;
//         foreach ($lines as $line) {
//             // Проверяем, является ли строка заголовком
//             if (preg_match('/^\s*(=+)\s*(.+?)\s*(=*)\s*$/', $line, $matches)) {
//                 $level = strlen($matches[1]);
//                 $text = $matches[2];
//                 $html .= "<h$level>$text</h$level>";
//             }
//             // Проверяем, является ли строка элементом списка
//             elseif (preg_match('/^\s*[*+-]\s+(.*)$/', $line, $matches)) {
//                 if (!$in_list) {
//                     $html .= '<ul>';
//                     $in_list = true;
//                 }
//                 $html .= '<li>' . $matches[1] . '</li>';
//             }
//             // Проверяем, является ли строка цитатой
//             elseif (preg_match('/^\s*>\s*(.*)$/', $line, $matches)) {
//                 if (!$in_quote) {
//                     $html .= '<blockquote>';
//                     $in_quote = true;
//                 }
//                 $html .= '<p>' . $matches[1] . '</p>';
//             }
//             // Обрабатываем простой текст
//             elseif (!empty(trim($line))) {
//                 if ($in_list) {
//                     $html .= '</ul>';
//                     $in_list = false;
//                 }
//                 if ($in_quote) {
//                     $html .= '</blockquote>';
//                     $in_quote = false;
//                 }
//                 $html .= '<p>' . $line . '</p>';
//             }
//         }
//         if ($in_list) {
//             $html .= '</ul>';
//         }
//         if ($in_quote) {
//             $html .= '</blockquote>';
//         }
//         return $html;
//     }
// }


class GiftParser
{
    /**
     * Читает строку в формате Gift и возвращает HTML-код для отображения в Moodle
     *
     * @param string $giftString Строка в формате Gift
     * @return string HTML-код для отображения в Moodle
     */    
    
     public function parse($giftString)
    {
        $lines = explode("\n", $giftString);
        // Получение текста вопроса
        $questionText = '';

        // Получение списка ответов
        $answers = array();
        $categories = array(); 
        $specialties = array(); 
        $questions = array();
        $specialty = null;
// поиск вопросов
$startTag = '<p>';
$endTag = '<p> }';

//-----end поиск вопросов
$category_id = 0;
$aukstructure_id = 0;
$question_id = 0;

        foreach ($lines as $index => $line) {
            
            if ($index === 0) {
                $questionText = $line;
                continue;
            }

            
            if (preg_match('/\$CATEGORY:\s*(.*)/', $line, $match)) 
            {
                //echo $line . "\n";
                $parts = explode('/', $match[1]);
                $lastThree = array_slice($parts, -3);
                $category = implode('/', $lastThree);
                $category = preg_replace('/\d+/', '', $category);
                $categories[] = $category; // вся строка с категориями последние три Десантно-транспортное оборудование и авиационное вооружение/ Инженер по АВ и ДТО/ Десантно-транспортное оборудование
                $specialty = $parts[count($parts) - 2];
                $razdel = $parts[count($parts) - 1]; // последняя подстрока раздел
                $razdel = trim(preg_replace('/\d+/', '', $razdel)); // убираем цифры
             
                $specialty = preg_replace('/\d+/', '', $specialty);
                $specialties[] = $specialty; // специальности вида  Инженер по АВ и ДТО 
               
                $specialty = trim($specialty);
               
                $temp_category_id = DB::table('categories')               
                ->where('title', 'like', $specialty)       
                 ->first();
                if($temp_category_id) 
                {
                    $category_id = $temp_category_id ->id;                     
                 }
                else $category_id = null;  
                $temp_aukstructure_id =   DB::table('aukstructures')               
                ->where('title', 'like', $razdel)       
                 ->first(); 
                 if($temp_aukstructure_id) 
                {
                    $aukstructure_id = $temp_aukstructure_id ->id;                     
                 }
                else $aukstructure_id = null;                
            }
            
            elseif ($category_id && $aukstructure_id && substr($line, 0, 3) === "<p>" && substr($line, -5) === '<p> {') {
            
                $startPos = strpos($line, $startTag);            
                $endPos = strpos($line, $endTag);                  
                $question = substr($line, $startPos + strlen($startTag), $endPos - $startPos- strlen($endTag));                
                $questions[$specialty][] = $question;                         
                
                $question = Question::create([
                    'question_text' => $question,
                    'category_id' => $category_id,
                    'aukstructure_id' => $aukstructure_id                                        
                ]);
               $question_id = $question->id;              
              //$question_id = 1;
                
            }
            elseif(preg_match('/^\s*=<p>/', $line)  && substr($line,-5)=== '<p> }' && $question_id )
           // elseif(strpos($line, '=<p>')===4 && substr($line,-5)=== '<p> }' && $question_id )
            {
        
            $is_correct = true;
            
            $answer=substr($line, 8, -5);            
            //echo($answer . "\n");

             $an = Answer::create([
                 'answer' => $answer,
                 'question_id' => $question_id,
                 'is_correct' => $is_correct
             ]);            
             $question_id = null;

            }
            elseif(preg_match('/^\s*=<p>/', $line)  && strpos($line, '<p>') && $question_id)
            {
             //   echo(strpos($line, '<p>') );
            $is_correct = true;            
            $answer=substr($line, 8, -3);            
           // echo($answer . "\n");

              $an = Answer::create([
                  'answer' => $answer,
                  'question_id' => $question_id,
                  'is_correct' => $is_correct
              ]);

            }
            elseif(preg_match('/^\s*~<p>/', $line)  && (substr($line, -3) === '<p>') && $question_id )
            {            
            $answer=substr($line, 8, -3);

            $is_correct = false;            
            //echo($answer . "\n");
             $an = Answer::create([
                 'answer' => $answer,
                 'question_id' => $question_id,
                 'is_correct' => $is_correct
             ]);
            }
            
            elseif(preg_match('/^\s*~<p>/', $line)  && substr($line,-5)=== '<p> }' && $question_id )
            {
                //echo(substr($line,-4));
            $is_correct = false;            
            $answer=substr($line, 8, -5);                        

            //echo($answer . "\n");
             $an = Answer::create([
                 'answer' => $answer,
                 'question_id' => $question_id,
                 'is_correct' => $is_correct
             ]);
             $question_id = null;
            }

        //}

        }

        // foreach ($specialties as $specialty) {
        //     // Для каждой специальности создаем массив вопросов и добавляем в него вопросы из $questions
        //     $specialtyQuestions = array();
        //     foreach ($questions[$specialty] as $question) {
        //         $specialtyQuestions[] = $question;
        //     }
        // }    

        //return $html;
        //return $specialties;
        //return $categories;
        //return $questions;
       ////// print_r($questions);    
        //return $specialtyQuestions;           

    // $allQuestions = array();
    // foreach ($specialties as $specialty) {
    //     // Объединяем массивы вопросов для каждой специальности в один массив
    //     $specialtyQuestions = $questions[$specialty];
    //     $allQuestions = array_merge($allQuestions, $specialtyQuestions);
    // }    
    //return $allQuestions;
    }    
}



// class GiftParser
// {
//     /**
//      * Читает строку в формате Gift и возвращает HTML-код для отображения в Moodle
//      *
//      * @param string $giftString Строка в формате Gift
//      * @return string HTML-код для отображения в Moodle
//      */
//     public function parse($giftString)
//     {
//         $lines = explode("\n", $giftString);

//         // Получение текста вопроса
//         $questionText = array_shift($lines);

//         // Получение списка ответов
//         $answers = array();

//         $categories = array(); 
//         $specialties = array(); 
//         $questions = array();
//         $specialty = null;
//         foreach ($lines as $line) {
//             if (preg_match('/\$CATEGORY:\s*(.*)/', $line, $match)) 
//             {
//                 $parts = explode('/', $match[1]);
//                 $lastThree = array_slice($parts, -3);
//                 $category = implode('/', $lastThree);
//                 $category = preg_replace('/\d+/', '', $category);
//                 $categories[] = $category; // вся строка с категориями последние три Десантно-транспортное оборудование и авиационное вооружение/ Инженер по АВ и ДТО/ Десантно-транспортное оборудование
//                 $specialty = $parts[count($parts) - 2];
//                 $specialty = preg_replace('/\d+/', '', $specialty);
//                 $specialties[] = $specialty; // специальности вида  Инженер по АВ и ДТО 
//                 if (!isset($questions[$specialty])) {
//                     $questions[$specialty] = array();
//                 }
//             }
//             elseif (preg_match('/<p>([A-Z].*?)<\/p>/s', $line, $match)) 
//             {
//                 $question = $match[1];
//                 $questions[$specialty][] = $question;
//                                  echo "Вопрос для специальности $specialty: $question\n";
//             }     
//         }

//         $allQuestions = array();
//         foreach ($specialties as $specialty) {
//             // Объединяем массивы вопросов для каждой специальности в один массив
//             $specialtyQuestions = $questions[$specialty];
//             $allQuestions = array_merge($allQuestions, $specialtyQuestions);
//         }    
//         return $allQuestions;
//     }    
// }
