<?php

namespace App\Http\Controllers;

use App\Models\Aircraft;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

use App\Models\Link;
use App\Models\Aukstructure;

use DOMDocument;
use DOMText;
use \DOMNode;
use DOMElement;
use DOMXPath;

use DOMDocumentFragment;
use SimpleXMLElement;
use Symfony\Component\DomCrawler\Crawler;






class SearchController extends Controller
{
    // public function search(Request $request)
    // {
    //     $searchTerm = strtolower($request->input('query'));
    //     $aircraft = Aircraft::find($request->input('aircraft'))->path;
    //     $path = $request->input('path');
    //     $directory = "private/{$aircraft}/{$path}/";

    //     $matches = [];

    //     $files = Storage::disk('public')->files($directory);

    //     foreach ($files as $file) {
    //         if (str_ends_with($file, '.html') && !str_ends_with($file, 'index.html')) {
    //             $contents = Storage::get($file);
    //             $contents = strtolower($contents);

    //             if (mb_stripos($contents, $searchTerm) !== false) {
    //                 $filename = basename($file);
    //                 $filename = trim($filename);
    //                 // Получаем id структуры из таблицы links
    //                 $link = Link::where('link', $filename)->first();    
    //                 if($link) $aukstructure_id = $link->aukstructure_id;
    //                 $aukstructure = Aukstructure::whereHas('links', function($query) use ($filename) {
    //                     $query->where('link', $filename);
    //                  })->first();


    //                 //echo($link->aukstructure_id);
    //                 //$aukstructure = Aukstructure::find($link->aukstructure_id);                
    //                 //echo($link);
    //                 $title = $aukstructure ? $aukstructure->title : null;
    //                 //$title = $aukstructure->title;
    //                 //echo($title);

    // // Получаем массив  nodes с подсвеченными и оригинальными
    // $highlightedHtml = $this->highlightWords($contents, $searchTerm);
    // //dd($highlightedHtml);

    // // $dom->loadHTML($highlightedHtml, LIBXML_NOWARNING );
    //                 //dd($highlightedHtml['highlightedNodes']);
    //                 //dd($highlightedHtml['originalNodes']);
    //                 $matches[] = [
    //                     'file' => $file,
    //                     //'position' => mb_stripos($contents, $searchTerm),
    //                     //'length' => mb_strlen($searchTerm),                    
    //                     //'contents' => $highlightedContents,
    //                     'contents' => $highlightedHtml,
    //                     'title' => $title,
    //                     'itemId'  => $aukstructure_id,
    //                     'highlightedHtml' => $highlightedHtml['highlightedNodes'],
    //                     'originalHtml' => $highlightedHtml['originalNodes'],
    //                 ];
    //                 //$matches[] = $file;
    //             } 
    //         }
    // }
    // return response()->json($matches);                      

    //  //$encoded = json_encode($this->utf8ize($matches), JSON_INVALID_UTF8_IGNORE); // для устранения ошибки кодировки utf-8


    //    //     return json_encode($matches, JSON_INVALID_UTF8_IGNORE);
    //        //return json_encode($matches, JSON_INVALID_UTF8_IGNORE); // смотрим файлы только верхнего уровня,без вложенности. ошибка ушла

    //        //return response()->json($matches)->header('Content-Type', 'application/json; charset=utf-8');
    //         // если с ошибкой, то возврат просто  //return response()->json($matches);
    //         //return response()->json($matches);
    //         //return response()->json_encode($matches, JSON_INVALID_UTF8_SUBSTITUTE)
    // }


    // function highlightWords(string $html, string $searchTerm) {

    //     $dom = new DOMDocument();
    //     libxml_use_internal_errors(true);
    //     $dom->loadHTML($html);

    //     $xpath = new DOMXPath($dom);
    //     $nodes = $xpath->query("//text()[contains(., '$searchTerm')]");


    //     $highlightedNodes = [];
    //     $originalNodes = [];
    //     foreach($nodes as $node) {
    //         $nodeText = $node->nodeValue;
    //         $originalNodes[] = $node;
    //         $highlighted = '<span class="highlighted" data-id="' . uniqid() . '">' . $searchTerm . '</span>';
    //         $replaced = str_ireplace($searchTerm, $highlighted, $nodeText);
    //         $newNode = $dom->createTextNode($replaced);
    //         $node->parentNode->replaceChild($newNode, $node);    
    //         $highlightedNodes[] = $newNode;
    //     }  
    //     return [
    //         'highlightedNodes' => $highlightedNodes,
    //         'originalNodes' => $originalNodes    
    //     ];
    // }


    // public function highlightWords($html, $searchTerm)
    // {
    //     libxml_use_internal_errors(true);
    //     // create DOMDocument object

    //     $dom = new DOMDocument();

    //     // load HTML into DOMDocument object
    //     $dom->loadHTML($html);

    //     // perform search and highlight
    //     $xpath = new DOMXPath($dom);
    //     $nodes = $xpath->query('//text()');
    //     $highlightedNodes = [];
    //     $originalNodes = [];
    //     foreach ($nodes as $node) {
    //         $text = $node->nodeValue;
    //         $pos = stripos($text, $searchTerm);
    //         if ($pos !== false) {
    //             // split text node into three parts: before, highlighted, and after
    //             $before = substr($text, 0, $pos);
    //             $highlighted = substr($text, $pos, strlen($searchTerm));
    //             $after = substr($text, $pos + strlen($searchTerm));
    //             $node->nodeValue = $before;

    //             // create highlighted node and append it to the DOM
    //             $highlightedNode = $dom->createElement('span', htmlspecialchars($highlighted));
    //             $highlightedNode->setAttribute('class', 'highlighted');
    //             $highlightedNode->setAttribute('data-id', substr(md5($searchTerm), 0, 10));
    //             $node->parentNode->insertBefore($highlightedNode, $node->nextSibling);

    //             // create original node and append it to the DOM
    //             $originalNode = $dom->createElement('span', htmlspecialchars($text));
    //             $node->parentNode->insertBefore($originalNode, $highlightedNode);

    //             // create a new text node for the text after the highlighted word
    //             if (!empty($after)) {
    //                 $afterNode = $dom->createTextNode($after);
    //                 $node->parentNode->insertBefore($afterNode, $highlightedNode->nextSibling);
    //             }

    //             // save highlighted and original nodes
    //             $highlightedNodes[] = $highlightedNode;
    //             $originalNodes[] = $originalNode;
    //         }
    //     }

    //     // return array of highlighted and original nodes
    //     return [
    //         'highlightedHtml' => $dom->saveHTML(),
    //         'highlightedNodes' => $highlightedNodes,
    //         'originalNodes' => $originalNodes,
    //     ];
    // }
    // //--------------------------------------------------------------------------------------------

    // ////////////////////////////////////////////////////////////////////////// !!!!!!!!!!!!!!!!!!!!!!!
    // public function search(Request $request)
    // {
    //     $searchTerm = strtolower($request->input('query'));
    //     $aircraftPath = Aircraft::find($request->input('aircraft'))->path;
    //     $directory = "private/{$aircraftPath}/{$request->input('path')}/";

    //     $matches = [];

    //     $files = Storage::disk('public')->files($directory);

    //     foreach ($files as $file) {
    //         if (Str::endsWith($file, '.html') && !Str::endsWith($file, 'index.html')) {
    //             $contents = strtolower(Storage::get($file));

    //             if (Str::contains($contents, $searchTerm)) {
    //                 $filename = basename($file);
    //                 $filename = trim($filename);
    //                 $link = Link::where('link', $filename)->first();
    //                 $aukstructure = $link ? $link->aukstructure : Aukstructure::whereHas('links', function($query) use ($filename) {
    //                     $query->where('link', $filename);
    //                 })->first();
    //                 $title = $aukstructure ? $aukstructure->title : null;

    //                 $highlightedHtml = $this->highlightWords($contents, $searchTerm);
    //                 //var_dump($highlightedHtml);
    //                 //dd($highlightedHtml);

    //                 $highlightedHtmlStrings = $this->convertNodesToHtml($highlightedHtml['highlightedNodes']);
    //                 $originalHtmlStrings = $this->convertNodesToHtml($highlightedHtml['originalNodes']);
    //                 //dd($originalHtmlStrings);
    //                //dd($highlightedHtmlStrings);



    //                 $matches[] = [
    //                     'file' => $file,
    //                     'contents' => $highlightedHtml,
    //                     'title' => $title,
    //                     'itemId'  => $aukstructure->id,
    //                     //'highlightedHtmlStrings' => $highlightedHtmlStrings,
    //                     '$originalHtmlStrings' => $originalHtmlStrings,
    //                     'highlightedHtml' => $highlightedHtml
    //                 ];
    //             }
    //         }
    //     }

    //     return response()->json($matches);
    // }

    /////рабочий
    // private function highlightWords($html, $searchTerm)
    // {
    //     // create DOMDocument object
    //     $dom = new DOMDocument();
    //     libxml_use_internal_errors(true);
    //     // load HTML into DOMDocument object
    //     $dom->loadHTML($html);

    //     // perform search and highlight
    //     $xpath = new DOMXPath($dom);
    //     $nodes = $xpath->query('//text()');
    //     $highlightedNodes = [];
    //     $originalNodes = [];
    //     foreach ($nodes as $node) {
    //         $text = $node->nodeValue;
    //         $pos = stripos($text, $searchTerm);
    //         if ($pos !== false) {
    //             $highlightedNode = $dom->createElement('span', htmlspecialchars(substr($text, $pos, strlen($searchTerm))));
    //             $originalNode = $node->parentNode->cloneNode(true);
    //             $highlightedNode->setAttribute('class', 'highlighted');
    //             $highlightedNode->setAttribute('data-id', substr(md5($searchTerm), 0, 10));
    //             $before = substr($text, 0, $pos);
    //             $after = substr($text, $pos + strlen($searchTerm));
    //             $node->nodeValue = $before;
    //             $node->parentNode->insertBefore($node->ownerDocument->createTextNode($before), $node);
    //             $node->parentNode->insertBefore($highlightedNode, $node);
    //             $node->parentNode->insertBefore($node->ownerDocument->createTextNode($after), $node);
    //             $highlightedNodes[] = $highlightedNode;
    //             $originalNodes[] = $originalNode;
    //         }
    //     }

    //     // return array of highlighted and original nodes
    //     return [
    //         'highlightedHtml' => $dom->saveHTML(),
    //         'highlightedNodes' => $highlightedNodes,
    //         'originalNodes' => $originalNodes,
    //     ];
    // }



    //////////////////////highlighted целиком
    // public function highlightWords($html, $searchTerm)
    // {
    //     libxml_use_internal_errors(true);
    //     $dom = new DOMDocument();
    //     $dom->loadHTML($html);
    //     $xpath = new DOMXPath($dom);  

    //     $nodes = $xpath->query('//text()');
    //     $highlightedNodes = [];
    //     $originalNodes = [];
    //     foreach ($nodes as $node) {
    //         $text = $node->nodeValue;
    //         $pos = stripos($text, $searchTerm);
    //         if ($pos !== false) {
    //             $before = substr($text, 0, $pos);
    //             $highlighted = substr($text, $pos, strlen($searchTerm));
    //             $after = substr($text, $pos + strlen($searchTerm));
    //             $node->nodeValue = $before;
    //             $highlightedNode = $dom->createElement('span', htmlspecialchars($highlighted));
    //             $highlightedNode->setAttribute('class', 'highlighted');
    //             $highlightedNode->setAttribute('data-id', substr(md5($searchTerm), 0, 10));
    //             $node->parentNode->insertBefore($highlightedNode, $node->nextSibling);
    //             $originalNode = $dom->createElement('span', htmlspecialchars($text));
    //             $node->parentNode->insertBefore($originalNode, $highlightedNode);
    //             if (!empty($after)) {
    //                 $afterNode = $dom->createTextNode($after);
    //                 $node->parentNode->insertBefore($afterNode, $highlightedNode->nextSibling);
    //             }

    //             //var_dump($highlightedNode->getNodePath());
    //             $highlightedNodes[] = [
    //                 'node' => json_encode($dom->saveHTML($highlightedNode)),
    //                 //'xpath' => $xpath->$this->getNodePath($highlightedNode),
    //                // 'xpath' => $highlightedNode->getAttribute('xpath')
    //                //'xpath' => $xpath->evaluate('string(' . $highlightedNode->getNodePath() . ')') 
    //                'xpath' => $highlightedNode->getNodePath(),
    //             ];  


    //             $originalNodes[] = [
    //                 'node' => json_encode($dom->saveHTML($originalNode)),  
    //                 //'xpath' => $xpath->evaluate('string(' . $originalNode->getNodePath() . ')') ,  
    //                 'xpath' => $originalNode->getNodePath(),
    //                 //'test' => 'test' // для диагностики  

    //             ];            
    //         }
    //     }

    //     return [
    //         'highlightedNodes' => $highlightedNodes,
    //         'originalNodes' => $originalNodes,
    //     ];
    // }



    //-----------------------это вариант пятницы не совсем рабочий--------------------
    // public function highlightWords($html, $searchTerm)
    // {
    //     libxml_use_internal_errors(true);
    //     $dom = new DOMDocument();
    //     $dom->loadHTML($html);
    //     $xpath = new DOMXPath($dom);  

    //     $nodes = $xpath->query('//text()');
    //     $highlightedNodes = [];
    //     $originalNodes = [];
    //     foreach ($nodes as $node) {
    //         $text = $node->nodeValue;
    //         $pos = stripos($text, $searchTerm);
    //         if ($pos !== false) {
    //             $before = substr($text, 0, $pos);
    //             $highlighted = substr($text, $pos, strlen($searchTerm));
    //             $after = substr($text, $pos + strlen($searchTerm));
    //             $node->nodeValue = $before;
    //             $highlightedNode = $dom->createElement('span', htmlspecialchars($highlighted));
    //             $highlightedNode->setAttribute('class', 'highlighted');
    //             $highlightedNode->setAttribute('data-id', substr(md5($searchTerm), 0, 10));
    //             $node->parentNode->insertBefore($highlightedNode, $node->nextSibling);
    //             $originalNode = $dom->createElement('span', htmlspecialchars($text));
    //             $node->parentNode->insertBefore($originalNode, $highlightedNode);
    //             if (!empty($after)) {
    //                 $afterNode = $dom->createTextNode($after);
    //                 $node->parentNode->insertBefore($afterNode, $highlightedNode->nextSibling);
    //             }

    //             $highlightedXml = $dom->saveXML($highlightedNode);
    //             $highlightedNodes[] = [
    //                 'node' => json_encode($highlightedXml),
    //                 'xpath' => $highlightedNode->getNodePath(),
    //             ];  


    //             $originalXml = $dom->saveXML($originalNode);
    //             $originalNodes[] = [
    //                 'node' => json_encode($originalXml),  
    //                 'xpath' => $originalNode->getNodePath(),                
    //             ];            
    //         }
    //     }

    //     return [
    //         'highlightedNodes' => $highlightedNodes,
    //         'originalNodes' => $originalNodes,
    //     ];
    // }
    //-----------------------это вариант пятницы не совсем рабочий--------------------

    public function highlightWords($html, $searchTerm)
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        $nodes = $xpath->query('//text()');
        $highlightedNodes = [];
        foreach ($nodes as $node) {
            $text = $node->nodeValue;

            $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text); // удаляем все специальные символы
            $text = trim($text); // удаляем пробелы в начале и конце строки

            $pos = stripos($text, $searchTerm);
            if ($pos !== false) {
                $before = substr($text, 0, $pos);
                $highlighted = substr($text, $pos, strlen($searchTerm));
                $after = substr($text, $pos + strlen($searchTerm));
                $node->nodeValue = $before;
                $highlightedNode = $dom->createElement('mark', htmlspecialchars($highlighted));
                $highlightedNode->setAttribute('class', 'highlighted');
                //$highlightedNode->setAttribute('data-id', substr(md5($searchTerm), 0, 10));
                $highlightedNode->setAttribute('data-id', uniqid()); //генерим уникальный data-id
                $node->parentNode->insertBefore($highlightedNode, $node->nextSibling);
                if (!empty($after)) {
                    $afterNode = $dom->createTextNode($after);
                    $node->parentNode->insertBefore($afterNode, $highlightedNode->nextSibling);
                }

                //$originalText = strip_tags($text);
                $originalText = $this->strip_tags2($text); // удаляем спецсимволы 
                $originalText = ltrim($originalText, " \t\r\0\x0B\n."); // удаляем спецсимволы 
                //$originalText = trim($originalText);
                //$originalText = preg_replace('/<[^>]*>/', '', $text);
                $originalXpath = $node->getNodePath();
                //$highlightedText = $before . "<mark class='highlighted' data-id='" . substr(md5($searchTerm), 0, 10) . "'>" . $highlighted . "</mark>" . $after;
                $highlightedText = "{$before}<mark class='highlighted' data-id='{$highlightedNode->getAttribute('data-id')}'>{$highlighted}</mark>{$after}";
                $highlightedXpath = $highlightedNode->getNodePath();
                $highlightedNodes[] = [
                    'originalText' => $originalText,
                    'originalXpath' => $originalXpath,
                    'highlightedText' => $highlightedText,
                    //'highlightedXpath' => $highlightedXpath,
                ];
            }
        }

        return $highlightedNodes;
    }

    public function strip_tags2($html)
    {
        // удалить комментарии
        $html = preg_replace('/<!--(.|\s)*?-->/', '', $html);
        // удалить теги, кроме нужных
        $html = preg_replace('/<(?!(\/?(b|i|u|s|strong|em|ins|del|strike|a|img|br|hr|input)))[^>]+>/si', '', $html);
        // удалить атрибуты из нужных тегов, кроме нужных
        $html = preg_replace('/<(b|i|u|s|strong|em|ins|del|strike|a|img|br|hr|input)\s+(?!(src|alt|href|title|type|name|value|checked|size|maxlength))[^>]+>/si', '<$1>', $html);
        // удалить теги без закрывающего тега
        $html = preg_replace('/<(img|br|hr|input)(\s+[^>]*)?\/?>/si', '<$1$2></$1>', $html);
        // заменить специальные символы на их html сущности
        $html = htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // удалить повторяющиеся пробелы и переносы строк
        $html = preg_replace('/[ \t]+/', ' ', $html);
        $html = preg_replace('/[\r\n]+/', "\n", $html);
        $html = trim($html);
        return $html;
    }


    public function search(Request $request)
    {
        $searchTerm = strtolower($request->input('query'));
        $aircraftPath = Aircraft::find($request->input('aircraft'))->path;
        $directory = "private/{$aircraftPath}/{$request->input('path')}/";
        $matches = [];

        //$files = Storage::disk('public')->files($directory);

        $disk = Storage::disk('public');
        $files = $disk->files($directory);

        //var_dump($files);
        //   var_dump($directory);

        foreach ($files as $file) {
            if (Str::endsWith($file, '.html') && !Str::endsWith($file, 'index.html')) {
                $contents = $disk->get($file);
                //$contents = Storage::get($file);
                if (Str::contains(strtolower($contents), $searchTerm)) {
                    $filename = basename($file);
                    $filename = trim($filename);
                    $link = Link::where('link', $filename)->first();
                    if ($link) $aukstructureId = $link->aukstructure_id;
                    $aukstructure = $link ? $link->aukstructure : Aukstructure::whereHas('links', function ($query) use ($filename) {
                        $query->where('link', $filename);
                    })->first();
                    $title = $aukstructure ? $aukstructure->title : null;
                    $highlightedHtml = $this->highlightWords($contents, $searchTerm);
                    //$highlightedHtmlStrings = $this->convertNodesToHtml($highlightedHtml['highlightedNodes']);
                    //$originalHtmlStrings = $this->convertNodesToHtml($highlightedHtml['originalNodes']);
                    //var_dump($aukstructureId);
                    //dd($highlightedHtml['highlightedNodes']);
                    //dd($highlightedHtml['originalNodes']);

                    $matches[] = [
                        'file' => $file,
                        'title' => $title,
                        'itemId'  => $aukstructureId,

                        'highlightedNodes' => $highlightedHtml,
                        //'originalNodes' => $highlightedHtml['originalText'],
                        //    /'originalXpath' => $highlightedHtml['originalXpath'],
                    ];
                }
            }
        }
        return response()->json($matches);
    }







    // 'contents' =>$highlightedHtml,
    //                    'highlightedHtmlStrings' => $highlightedHtmlStrings,
    //                    'originalHtmlStrings' => $originalHtmlStrings,


    public function convertNodesToHtml($nodes)
    {
        $htmlNodes = [];
        foreach ($nodes as $node) {
            $htmlNodes[] = $node->ownerDocument->saveHTML($node);
        }
        return $htmlNodes;
    }


    //--------------------------------------------------------------------------------------------
    function fixRepeatingAttributes($html)
    {

        $dom = new DOMDocument();
        $dom->loadHTML($html);

        $id = 1;

        $colspanNodes = $dom->getElementsByTagName('td');
        $colspanMap = [];

        // Создать карту colspan -> ID
        foreach ($colspanNodes as $node) {
            $colspan = $node->getAttribute('colspan');
            $colspanMap[$colspan] = 'colspan' . $id++;
        }

        // Установить уникальные ID как атрибут
        foreach ($colspanNodes as $node) {
            $colspan = $node->getAttribute('colspan');
            $node->setAttribute('colspan', $colspanMap[$colspan]);
        }

        // Обновить все td с новыми ID
        foreach ($colspanNodes as $node) {
            $id = $node->getAttribute('colspan');
            $node->setAttribute('colspan', $id);
        }
        return $dom->saveHTML();
    }

    //-------------------------------------------------------------------------------------------
    function utf8ize($mixed)
    {
        if (is_array($mixed)) {
            foreach ($mixed as $key => $value) {
                $mixed[$key] = $this->utf8ize($value);
            }
        } elseif (is_string($mixed)) {
            return mb_convert_encoding($mixed, "UTF-8", "UTF-8");
        }
        return $mixed;
    }




    //     /**
    //  * Получить XPath для элемента DOM
    //  *
    //  * @param DOMElement $element
    //  * @return string
    //  */

    //  function getXPath(DOMElement $element) {
    //     $paths = array();
    //     $web_driver = $element->ownerDocument;
    //     for (; $element && $element->nodeType == DOMNode::ELEMENT_NODE; $element = $element->parentNode) {
    //         $index = 0;
    //         $nodeName = $element->nodeName;
    //         $siblings = $web_driver->getElementsByTagName($nodeName);
    //         for ($i = 0, $l = $siblings->length; $i < $l; $i++) {
    //             if ($siblings->item($i) === $element) {
    //                 $index = $i + 1;
    //                 break;
    //             }
    //         }
    //         $paths[] = $nodeName . '[' . $index . ']';
    //     }
    //     return '/' . implode('/', array_reverse($paths));
    // }

}
//--------------------------------------------------------------------------------------------