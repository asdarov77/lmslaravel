<?php

namespace App\Lyx;

use PhpParser\Node\Stmt\Label;
use PhpParser\PrettyPrinter\Standard;

class LyxToHtml
{
    public $body = '';
    public $html_body = '';

    private $standard = array();
    private $standard_html = array();
    private $standard_index = -1;

    private $table_row = 0;
    private $table_id = '';

    private $footnotes = array();

    function __construct(string $body,$footnotes)
    {
        $this->body = $body;
        $this->footnotes = $footnotes;

    }

    public function body_to_html()
    {
        $this->html_body = '';
        $this->split_to_standards();
        $this->convert_standards();

        foreach($this->standard_html as $index=>$standard_html)
        {
            // $this->html_body .= '<p>'.$index.' - count:'.count($standard_html).'</p>'."\n";
            $this->html_body .= '<p style="text-indent: 2em;">'."\n".implode("\n",$standard_html).'</p>'."\n";
        }

        return $this->html_body;

    }

    public function split_to_standards()
    {
        $tmp_standard = array();
        $this->standard = array();

        $body_array = preg_split("/((\r?\n)|(\r\n?))/", $this->body);

        for($i = 0; $i < count($body_array); $i++)
        {
            $line = $body_array[$i];
            if(strcmp($line,'\begin_layout Standard') == 0)
            {
                if($i > 0)
                {
                    for($j = 0; $j < count($tmp_standard); $j)
                    {
                        $element = array_pop($tmp_standard);
                        if(strcmp($element,'\end_layout') == 0)
                        {
                            // error_log($element);
                            break;
                        }

                    }
                    // error_log(array_pop($tmp_standard));
                    $this->standard[] = $tmp_standard;
                }
                $tmp_standard = array();
                continue;
            }
            if(strlen($line) > 0)
            {
                $tmp_standard[] = $line;
            }
        }
        $this->standard[] = $tmp_standard;

    }

    public function convert_standards()
    {


        for($i = 0; $i < count($this->standard); $i++)
        {
            $this->convert_standard($i);
        }

    }


    public function convert_standard($standard_index)
    {
        $tmp_standard_html = array();

        // var_dump($this->standard[$standard_index]);
        for($i = 0; $i < count($this->standard[$standard_index]); $i++)
        {

            $line = $this->standard[$standard_index][$i];

            if(strcmp($line,'\begin_inset CommandInset nomencl_print') == 0)
            {
                $tmp_array = $this->nomenclature($standard_index,$i);
                foreach($tmp_array as $tmp)
                {
                    $tmp_standard_html[] = $tmp;
                }
                $i = $this->standard_index;
                continue;
            }
            if(strcmp($line,'\begin_inset Foot') == 0)
            {
                $tmp_array = $this->footnote($standard_index,$i);
                foreach($tmp_array as $tmp)
                {
                    $tmp_standard_html[] = $tmp;
                }
                $i = $this->standard_index;
                continue;
            }

            if(strcmp($line,'\begin_inset CommandInset ref') == 0)
            {
                $tmp_array = $this->internal_link($standard_index,$i);
                foreach($tmp_array as $tmp)
                {
                    $tmp_standard_html[] = $tmp;
                }
                $i = $this->standard_index;
                continue;
            }

            if(strcmp($line,'\begin_inset CommandInset href') == 0)
            {
                $tmp_array = $this->external_link($standard_index,$i);
                foreach($tmp_array as $tmp)
                {
                    $tmp_standard_html[] = $tmp;
                }
                $i = $this->standard_index;
                continue;
            }

            if(strcmp($line,'\series bold') == 0)
            {
                $tmp_array = $this->bold($standard_index,$i);
                foreach($tmp_array as $tmp)
                {
                    $tmp_standard_html[] = $tmp;
                }
                $i = $this->standard_index;
                continue;
            }

            if(strcmp($line,'\begin_inset Note Note') == 0)
            {
                $tmp_array = $this->note_3d($standard_index,$i);

                if(count($tmp_array) > 0)
                {
                    $tmp_standard_html[] = '<div align="center" class="text-3xl text-red-500">Здесь 3D. Временно выключено для повышения скорости разработки</div>';
                    // foreach($tmp_array as $tmp)
                    // {
                    //     $tmp_standard_html[] = $tmp;
                    // }
                }
                $i = $this->standard_index;
                continue;
            }

            if(strcmp($line,'\begin_inset Note Greyedout') == 0)
            {
                $tmp_array = $this->note_greyed($standard_index,$i);
                foreach($tmp_array as $tmp)
                {
                    $tmp_standard_html[] = $tmp;
                }
                $i = $this->standard_index;
                continue;
            }

            if(strcmp($line,'\begin_inset Float figure') == 0)
            {
                $tmp_array = $this->figure($standard_index,$i);
                foreach($tmp_array as $tmp)
                {
                    $tmp_standard_html[] = $tmp;
                }
                $i = $this->standard_index;
                continue;
            }

            if(strcmp($line,'\begin_layout Itemize') == 0)
            {
                $tmp_array = $this->list($standard_index,$i);
                foreach($tmp_array as $tmp)
                {
                    $tmp_standard_html[] = $tmp;
                }
                $i = $this->standard_index;
                continue;
            }

            if(strcmp($line,'\begin_inset Float table') == 0)
            {
                $tmp_array = $this->table($standard_index,$i);
                foreach($tmp_array as $tmp)
                {
                    if(strpos($tmp,'<table') !== false)
                    {
                        $tmp = str_replace('%table_id%',$this->table_id,$tmp);
                    }
                    $tmp_standard_html[] = $tmp;
                }
                $i = $this->standard_index;
                continue;
            }

            if(strcmp($line,'\begin_inset script superscript') == 0)
            {
                $tmp_array = $this->superscript($standard_index,$i);
                foreach($tmp_array as $tmp)
                {
                    $tmp_standard_html[] = $tmp;
                }
                $i = $this->standard_index;
                continue;
            }

            if(strcmp($line,'\begin_inset script subscript') == 0)
            {
                $tmp_array = $this->subscript($standard_index,$i);
                foreach($tmp_array as $tmp)
                {
                    $tmp_standard_html[] = $tmp;
                }
                $i = $this->standard_index;
                continue;
            }

            if(strcmp($line,'\begin_inset Float figure') == 0)
            {
                $tmp_array = $this->figure($standard_index,$i);
                foreach($tmp_array as $tmp)
                {
                    $tmp_standard_html[] = $tmp;
                }
                $i = $this->standard_index;
                continue;
            }

            if(strcmp($line,'\begin_inset Box Shaded') == 0)
            {
                $tmp_array = $this->box($standard_index,$i);
                foreach($tmp_array as $tmp)
                {
                    $tmp_standard_html[] = $tmp;
                }
                $i = $this->standard_index;
                continue;
            }

            if(strpos($line,'\end_') !== false)
            {
                continue;
            }
            if(strpos($line,'\series default') !== false)
            {
                continue;
            }
            $tmp_standard_html[] = $line;
        }
        $this->standard_html[] =  $tmp_standard_html;
    }

    public function nomenclature($standard_index,$index)
    {
        // Список обозначений
        $tmp_array = array();

        for($i = $index + 1; $i < count($this->standard[$standard_index]); $i++)
        {
            $line = $this->standard[$standard_index][$i];
            if(strcmp($line,'\end_inset') == 0)
            {
                // $tmp_array[] = '<div class="text-center text-xl mt-10 mb-8 sm:mt-14 sm:mb-10">Список обозначений:</div>';
                $tmp_array[] = '<div align="center" class="mb-8">';
                $tmp_array[] = '<table>';
                foreach(array_keys($this->footnotes) as $index=>$key)
                {
                    $tmp_array[] = '<tr><td align="right"><span class="mr-2">'.($index+1).'.</span></td><td><b>'.$key.'</b></td><td> - '.$this->footnotes[$key].'</td></tr>';
                }
                $tmp_array[] = '</table>';
                $tmp_array[] = '</div>';
                $this->standard_index = $i;
                return $tmp_array;
                break;
            }

        }
    }

    public function footnote($standard_index,$index)
    {
        // Footnotes
        $tmp_array = array();
        $plain = false;
        $label = false;

        $key = '';
        $value = '';


        for($i = $index + 1; $i < count($this->standard[$standard_index]); $i++)
        {
            $line = $this->standard[$standard_index][$i];
            if(strcmp($line,'\end_inset') == 0)
            {
                if(!$label)
                {
                    foreach(array_keys($this->footnotes) as $index=>$fn_key)
                    {
                        if(strcmp($fn_key,$key) == 0)
                        {
                            $tmp_array[] = '<span onmousemove="showTooltip(event, \''.$this->footnotes[$fn_key].'\');" onmouseout="hideTooltip();"><span class="foot_label">'.($index+1).'</span></span>';
                            break;
                        }
                    }
                    $this->standard_index = $i;
                    return $tmp_array;
                    break;
                }
                else
                {
                    $label = false;
                }
            }
            if(strcmp($line,'\begin_layout Plain Layout') == 0)
            {
                $plain = true;
                continue;
            }
            if(strcmp($line,'\begin_inset CommandInset label') == 0)
            {
                $label = true;
                continue;
            }
            if($plain)
            {
                if(strcmp($line,'\end_layout') == 0)
                {
                    $plain = false;
                    continue;
                }

                if($label)
                {
                    if(strpos($line,'name') !== false)
                    {
                        $key = substr($line,9,strlen($line) - 10);
                    }

                }
                else
                {
                    if(strlen($line) > 0 && strcmp(substr($line,0,1),'\\') != 0)
                    {
                        $value = $line;
                    }
                }
            }
        }
    }

    public function internal_link($standard_index,$index)
    {
        // Внутренние ссылки
        $tmp_array = array();

        for($i = $index + 1; $i < count($this->standard[$standard_index]); $i++)
        {
            $line = $this->standard[$standard_index][$i];
            if(strcmp($line,'\end_inset') == 0)
            {
                $this->standard_index = $i;
                return $tmp_array;
                break;
            }
            if(strcmp(substr($line,0,9),'reference') == 0)
            {
                $split_str = explode('"',$line);
                $name = explode(':',$split_str[1]);
                if(strcmp($name[0],'fn') == 0)
                {
                    foreach(array_keys($this->footnotes) as $index=>$fn_key)
                    {
                        if(strcmp($fn_key,$name[1]) == 0)
                        {
                            $tmp_array[] = '<span onmousemove="showTooltip(event, \''.$this->footnotes[$fn_key].'\');" onmouseout="hideTooltip();"><span class="foot_label">'.($index+1).'</span></span>';
                            // $this->standard_index = $i;
                            // return $tmp_array;
                            break;
                        }
                    }
                }
                else
                {
                    $tmp_array[] = '<a href="#'.$split_str[1].'"><u>'.$name[1].'</u></a>';
                }
            }
        }
    }

    public function external_link($standard_index,$index)
    {
        // Внешние ссылки
        $tmp_array = array();
        $name = '';
        $target = '';

        for($i = $index + 1; $i < count($this->standard[$standard_index]); $i++)
        {
            $line = $this->standard[$standard_index][$i];
            if(strcmp($line,'\end_inset') == 0)
            {
                $tmp_array[] = '<a href="'.$target.'"><u>'.$name.'</u></a>';
                $this->standard_index = $i;
                return $tmp_array;
                break;
            }
            if(strcmp(substr($line,0,4),'name') == 0)
            {
                $name = substr($line,6,strlen($line) -7);
            }
            if(strcmp(substr($line,0,6),'target') == 0)
            {
                $target = substr($line,8,strlen($line) - 9);
            }
        }
    }

    public function bold($standard_index,$index)
    {
        // Жирный текст
        $tmp_array = array();

        $tmp_array[] = '<b>';

        for($i = $index + 1; $i < count($this->standard[$standard_index]); $i++)
        {
            $line = $this->standard[$standard_index][$i];
            if(strcmp($line,'\series default') == 0)
            {
                $tmp_array[] = '</b>';
                $this->standard_index = $i;
                return $tmp_array;
                break;
            }
            if(strcmp($line,'\begin_inset Foot') == 0)
            {
                $foot_array = $this->footnote($standard_index,$i);
                foreach($foot_array as $tmp)
                {
                    $tmp_array[] = $tmp;
                }
                $tmp_array[] = '</b>';
                $i = $this->standard_index;
                return $tmp_array;
                break;
            }
            if(strcmp($line,'\begin_inset script superscript') == 0)
            {
                $super_array = $this->superscript($standard_index,$i);
                foreach($super_array as $tmp)
                {
                    $tmp_array[] = $tmp;
                }
                $i = $this->standard_index;
                $tmp_array[] = '</b>';
                $i = $this->standard_index;
                return $tmp_array;
                break;
                // continue;
            }
            if(strcmp($line,'\begin_inset script subscript') == 0)
            {
                $sub_array = $this->subscript($standard_index,$i);
                foreach($sub_array as $tmp)
                {
                    $tmp_array[] = $tmp;
                }
                $i = $this->standard_index;
                $tmp_array[] = '</b>';
                $i = $this->standard_index;
                return $tmp_array;
                break;
                // continue;
            }
            $tmp_array[] = $line;
        }
        error_log("bold tmp_array\n".print_r($tmp_array));
        // return $tmp_array;
    }

    public function note_3d($standard_index,$index)
    {
        // Note / 3D
        $tmp_array = array();
        $plain = false;
        $plain_data = '';
        $link_3d = 0;
        $minus = false;


        for($i = $index + 1; $i < count($this->standard[$standard_index]); $i++)
        {
            $line = $this->standard[$standard_index][$i];
            if(strcmp($line,'\end_inset') == 0)
            {
                $this->standard_index = $i;
                if($minus)
                {
                    return array();
                }
                else
                {
                    return $tmp_array;
                }
                break;
            }
            if(strcmp($line,'\begin_layout Plain Layout') == 0)
            {
                $plain = true;
                if($link_3d)
                {
                    $link_3d++;
                }
                continue;
            }

            if($plain)
            {
                if(strcmp($line,'\end_layout') == 0)
                {
                    $plain = false;
                    continue;
                }

                $plain_data = $line;
                if(strcmp(substr($plain_data,0,2),'3D') == 0)
                {
                    $link_3d++;
                    if(strcmp($plain_data,'3D-') == 0)
                    {
                        $minus = true;
                    }
                }

                if($link_3d == 2)
                {
                    $tmp_array[] = '<iframe src="'.$plain_data.'" style="text-indent: 0px;" width="100%" height="580px" frameborder="0" align="middle">Ваш браузер не поддерживает плавающие фреймы!</iframe>';
                }
                if($link_3d == 3)
                {
                    $tmp_array[] = '<p class="text-center text-xl mb-8 sm:mt-14">' . $plain_data . '</p>';
                }
            }
        }
    }
    public function note_greyed($standard_index,$index)
    {
        // Note / 3D
        $tmp_array = array();
        $plain = false;

        $tmp_array[] = '<div class="text-gray-500 p-3">';

        for($i = $index + 1; $i < count($this->standard[$standard_index]); $i++)
        {
            $line = $this->standard[$standard_index][$i];
            if(strcmp($line,'\end_inset') == 0)
            {
                $tmp_array[] = '</div>';
                $this->standard_index = $i;
                return $tmp_array;
                break;
            }
            if(strcmp($line,'\begin_layout Plain Layout') == 0)
            {
                $plain = true;
                continue;
            }

            if($plain)
            {
                if(strcmp($line,'\end_layout') == 0)
                {
                    $plain = false;
                    continue;
                }

                $tmp_array[] = $line;
            }
        }
    }

    public function figure($standard_index,$index)
    {
        // Картинки
        $tmp_array = array();
        $plain = false;
        $plain1 = false;
        $figure = false;
        $graphics = false;
        $graphics_src = '';
        $caption = false;
        $graphics_caption = '';
        $label = false;
        $graphics_label = '';


        for($i = $index + 1; $i < count($this->standard[$standard_index]); $i++)
        {
            $line = $this->standard[$standard_index][$i];
            if(strcmp($line,'\end_inset') == 0)
            {
                if(!$graphics && !$caption)
                {
                    $this->standard_index = $i;
                    return $tmp_array;
                    break;
                }
            }

            if($plain)
            {
                if(strcmp($line,'\end_layout') == 0)
                {
                    if(!$plain1)
                    {
                        $plain = false;
                        continue;
                    }
                }
                if(strcmp($line,'\end_inset') == 0)
                {
                    if($graphics)
                    {
                        $graphics = false;
                        continue;
                    }
                    if($label)
                    {
                        $label = false;
                        continue;
                    }
                    else
                    {
                        $caption = false;
                        continue;
                    }
                }

                if($graphics)
                {
                    if(strpos($line,'filename') > 0)
                    {
                        $graphics_src = substr($line,strpos($line,'filename')+9);
                    }
                    continue;
                }
                else
                {
                    if(strcmp($line,'\begin_inset Graphics') == 0)
                    {
                        $graphics = true;
                    }
                }

                if($caption)
                {

                    if($plain1)
                    {
                        if(strcmp($line,'\end_layout') == 0)
                        {
                            $plain1 = false;
                            continue;
                        }
                        if($label)
                        {
                            if(strcmp(substr($line,0,4),'name') == 0)
                            {
                                $graphics_label = substr($line,6,strlen($line)-7);
                            }
                            continue;
                        }
                        else
                        {
                            if(strcmp($line,'\begin_inset CommandInset label') == 0)
                            {
                                $label = true;
                                continue;
                            }
                        }

                        if(strlen($line) > 0 && strcmp(substr($line,0,1),'\\')!=0)
                        {
                            $graphics_caption = $line;
                            $tmp_array[] = '<div align="center">';
                            $tmp_array[] = '<img src="'.$graphics_src.'" id="'. $graphics_label .'">';
                            $tmp_array[] = '</div>';
                            $tmp_array[] = '<p class="text-center text-xl mb-8 sm:mb-14">' . $graphics_caption . '</p>';
                        }
                    }
                    else
                    {
                        if(strcmp($line,'\begin_layout Plain Layout') == 0)
                        {
                            $plain1 = true;
                            continue;
                        }
                    }
                    continue;
                }
                else
                {
                    if(strcmp($line,'\begin_inset Caption Standard') == 0)
                    {
                        $caption = true;
                        continue;
                    }
                }
            }
            else
            {
                $plain = true;
                continue;
            }

        }
    }

    public function list($standard_index,$index)
    {
        // Список
        $tmp_array = array();
        $items = array();
        $item = true;
        $item_data = array();
        $end_last_item = $index;


        for($i = $index + 1; $i < count($this->standard[$standard_index]); $i++)
        {
            $line = $this->standard[$standard_index][$i];
            // error_log($i . ' ' . $line);
            if(strcmp($line,'\begin_layout Itemize') == 0)
            {
                $item = true;
                continue;
            }
            if(strcmp($line,'\end_layout') == 0)
            {
                $items[] = $item_data;
                $item_data = array();
                $item = false;
                $end_last_item = $i;
                // error_log('Last i '.$end_last_item);
                continue;
            }
            if($item)
            {
                if(strcmp($line,'\series bold') == 0)
                {
                    $bold_array = $this->bold($standard_index,$i);
                    foreach($bold_array as $tmp)
                    {
                        $item_data[] = $tmp;
                    }
                    $i = $this->standard_index;
                    continue;

                }

                if(strcmp($line,'\begin_inset CommandInset ref') == 0)
                {
                    $ref_array = $this->internal_link($standard_index,$i);
                    foreach($ref_array as $tmp)
                    {
                        $item_data[] = $tmp;
                    }
                    $i = $this->standard_index;
                    continue;
                }
                if(strcmp($line,'\begin_inset Foot') == 0)
                {
                    $foot_array = $this->footnote($standard_index,$i);
                    foreach($foot_array as $tmp)
                    {
                        $item_data[] = $tmp;
                    }
                    $i = $this->standard_index;
                    continue;
                }
                if(strcmp($line,'\begin_inset script superscript') == 0)
                {
                    $super_array = $this->superscript($standard_index,$i);
                    foreach($super_array as $tmp)
                    {
                        $item_data[] = $tmp;
                    }
                    $i = $this->standard_index;
                    continue;
                }
                if(strcmp($line,'\begin_inset script subscript') == 0)
                {
                    $sub_array = $this->subscript($standard_index,$i);
                    foreach($sub_array as $tmp)
                    {
                        $item_data[] = $tmp;
                    }
                    $i = $this->standard_index;
                    continue;
                }
                if(strpos($line,'\series default') !== false)
                {
                    continue;
                }
                if(strlen($line) > 0)
                {
                    $item_data[] = $line;
                }
            }
        }
        if(count($item_data) > 0)
        {
            $items[] = $item_data;
            $end_last_item = count($this->standard[$standard_index]) - 1;
        }

        $tmp_array[] = '<ul class="list-disc list-inside">';
        foreach($items as $item_data)
        {
            $tmp_array[] = '<li>';
            foreach($item_data as $line)
            {
                $tmp_array[] = $line;
            }
            $tmp_array[] = '</li>';
        }
        $tmp_array[] = '</ul>';
        $this->standard_index = $end_last_item;

        return $tmp_array;
    }

    public function table($standard_index,$index)
    {
        // Таблица
        $tmp_array = array();
        $in_cell = false;
        $caption = false;

        $table_array = array();
        // $table_array[] = '<div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">';
        // $table_array[] = '<div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">';
        // $table_array[] = '<div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">';

        for($i = $index + 1; $i < count($this->standard[$standard_index]); $i++)
        {
            $line = $this->standard[$standard_index][$i];

            if(strcmp(substr($line,0,1),'<') == 0)
            {
                if(strcmp(substr($line,1,1),'/') == 0)
                {
                    $in_cell = false;
                }
                else
                {
                    $in_cell = true;
                }
                // error_log($line);
                $table_array[] = $this->table_tag_replace($line);
            }
            else
            {
                if($in_cell)
                {
                    // $table_array[] = $line;
                    $cell_array = $this->table_cell_convert($standard_index,$i);
                    foreach($cell_array as $cell)
                    {
                        $table_array[] = $cell;
                    }
                    $i = $this->standard_index;
                    continue;
                }
            }

            if(strcmp($line,'</lyxtabular>') == 0)
            {
                $this->standard_index = $i;
                $in_cell = false;
                $caption = true;
                continue;
                // break;
            }

            if($caption)
            {
                $caption_array = $this->table_label_convert($standard_index,$i);
                foreach($caption_array as $caption)
                {
                    $table_array[] = $caption;
                }
                $i = $this->standard_index;
                continue;
            }
            // $tmp_array[] = $line;
        }
        // $table_array[] = '</div>';
        // $table_array[] = '</div>';
        // $table_array[] = '</div>';

        $tmp_array = $table_array;
        return $tmp_array;
    }

    public function table_tag_replace($string)
    {
        $conv_string = '';
        if(strpos($string,'<lyxtabular') !== false)
        {
            // $conv_string = '<table class="min-w-full divide-y divide-gray-200">';
            $conv_string = '<table id="%table_id%" class="table-auto border-collapse border-3 border-blue-900">';
            return $conv_string;
        }
        if(strpos($string,'</lyxtabular') !== false)
        {
            $conv_string = '</tbody></table>';
            return $conv_string;
        }
        if(strpos($string,'<cell') !== false)
        {
            if($this->table_row == 1)
            {
                $conv_string = '<th class="border-2 border-blue-900">';
            }
            else
            {
                $conv_string = '<td class="border-2 border-blue-900">';
            }
            return $conv_string;
        }
        if(strpos($string,'</cell') !== false)
        {
            if($this->table_row == 1)
            {
                $conv_string = '</th>';
            }
            else
            {
                $conv_string = '</td>';
            }
            return $conv_string;
        }
        if(strpos($string,'<row') !== false)
        {
            $this->table_row++;
            if($this->table_row == 1)
            {
                // $conv_string = '<thead class="bg-gray-50"><tr>';
                $conv_string = '<thead><tr>';
                return $conv_string;
            }
            if($this->table_row == 1)
            {
                $conv_string = '<tbody><tr>';
                return $conv_string;
            }

            $conv_string = '<tr>';
            return $conv_string;
        }
        if(strpos($string,'</row') !== false)
        {
            if($this->table_row == 1)
            {
                $conv_string = '</thead></tr>';
            }
            else
            {
                $conv_string = '</tr>';
            }
            return $conv_string;
        }

        if(strlen($conv_string) == 0)
        {
            $conv_string = $string;
        }
        return $conv_string;
    }

    public function table_cell_convert($standard_index,$index)
    {
        $tmp_array = array();
        $plain = 0;

        for($i = $index + 1; $i < count($this->standard[$standard_index]); $i++)
        {
            $line = $this->standard[$standard_index][$i];
            if(strcmp($line,'\end_inset') == 0)
            {
                $this->standard_index = $i;
                break;
            }
            if(strcmp($line,'\begin_layout Plain Layout') == 0)
            {
                $plain++;
                continue;
            }
            if(strcmp($line,'\end_layout') == 0)
            {
                $plain--;
                continue;
            }
            if($plain > 0)
            {
                if(strcmp($line,'\begin_inset script subscript') == 0)
                {
                    $sub_array = $this->subscript($standard_index,$i);
                    foreach($sub_array as $tmp)
                    {
                        $tmp_array[] = $tmp;
                    }
                    $i = $this->standard_index;
                    continue;
                }
                if(strcmp($line,'\begin_inset Foot') == 0)
                {
                    $foot_array = $this->footnote($standard_index,$i);
                    foreach($foot_array as $tmp)
                    {
                        $tmp_array[] = $tmp;
                    }
                    $i = $this->standard_index;
                    continue;
                }
                if(strcmp($line,'\begin_inset CommandInset ref') == 0)
                {
                    $ref_array = $this->internal_link($standard_index,$i);
                    foreach($ref_array as $tmp)
                    {
                        $tmp_array[] = $tmp;
                    }
                    $i = $this->standard_index;
                    continue;
                }

                if(strcmp($line,'\begin_inset CommandInset href') == 0)
                {
                    $href_array = $this->external_link($standard_index,$i);
                    foreach($href_array as $tmp)
                    {
                        $tmp_array[] = $tmp;
                    }
                    $i = $this->standard_index;
                    continue;
                }

                $tmp_array[] = $line;
            }
        }

        return $tmp_array;

    }

    public function table_label_convert($standard_index,$index)
    {
        $tmp_array = array();
        $plain = 1;
        $caption = array();

        for($i = $index + 1; $i < count($this->standard[$standard_index]); $i++)
        {
            $line = $this->standard[$standard_index][$i];
            if(strcmp($line,'\begin_layout Plain Layout') == 0)
            {
                $plain++;
                continue;
            }
            if(strcmp($line,'\end_layout') == 0)
            {
                $plain--;
                if($plain == 0)
                {
                    $this->standard_index = $i;
                    $tmp_array[] = '<p class="text-center text-xl mb-8 sm:mb-14">' . implode(' ',$caption) . '</p>';

                    break;
                }
                continue;
            }
            if(strcmp(substr($line,0,1),'\\') != 0)
            {
                if(strcmp($line,'LatexCommand label') == 0)
                {
                    continue;
                }
                // error_log($line);
                if(strpos($line,'name') !== false)
                {
                    $this->table_id = substr($line,6,strlen($line) -7);
                }
                else
                {
                    $caption[] = $line;
                }
            }
        }

        $this->standard_index = $i;
        return $tmp_array;

    }

    public function superscript($standard_index,$index)
    {
        $tmp_array = array();
        $plain = false;
        $bold = false;

        for($i = $index + 1; $i < count($this->standard[$standard_index]); $i++)
        {
            $line = $this->standard[$standard_index][$i];

            if(strcmp($line,'\end_inset') == 0)
            {
                $this->standard_index = $i;
                return $tmp_array;
                break;
            }

            if($plain)
            {
                if(strcmp($line,'\end_layout') == 0)
                {
                    $plain = false;
                    if($bold)
                    {
                        $tmp_array[] = '</b>';
                        $bold = false;
                    }
                    $tmp_array[] = '</span>';
                    continue;
                }
                if(strcmp($line,'\series bold') == 0)
                {
                    $bold = true;
                    $tmp_array[] = '<b>';
                    continue;
                }
                $tmp_array[] = $line;
            }
            else
            {
                if(strcmp($line,'\begin_layout Plain Layout') == 0)
                {
                    $plain = true;
                    $tmp_array[] = '<span style="vertical-align: super;">';
                    continue;
                }
            }
        }
    }
    public function subscript($standard_index,$index)
    {
        $tmp_array = array();
        $plain = false;
        $bold = false;

        for($i = $index + 1; $i < count($this->standard[$standard_index]); $i++)
        {
            $line = $this->standard[$standard_index][$i];

            if(strcmp($line,'\end_inset') == 0)
            {
                $this->standard_index = $i;
                return $tmp_array;
                break;
            }

            if($plain)
            {
                if(strcmp($line,'\end_layout') == 0)
                {
                    $plain = false;
                    if($bold)
                    {
                        $tmp_array[] = '</b>';
                        $bold = false;
                    }
                    $tmp_array[] = '</span>';
                    continue;
                }
                if(strcmp($line,'\series bold') == 0)
                {
                    $bold = true;
                    $tmp_array[] = '<b>';
                    continue;
                }
                $tmp_array[] = $line;
            }
            else
            {
                if(strcmp($line,'\begin_layout Plain Layout') == 0)
                {
                    $plain = true;
                    $tmp_array[] = '<span style="vertical-align: sub;">';
                    continue;
                }
            }
        }
    }
    public function box($standard_index,$index)
    {
        // Note / 3D
        $tmp_array = array();
        $plain = false;

        $tmp_array[] = '<div class="p-3 text-2xl font-extrabold warning">';

        for($i = $index + 1; $i < count($this->standard[$standard_index]); $i++)
        {
            $line = $this->standard[$standard_index][$i];
            if(strcmp($line,'\end_inset') == 0)
            {
                $tmp_array[] = '</div>';
                $this->standard_index = $i;
                return $tmp_array;
                break;
            }
            if(strcmp($line,'\begin_layout Plain Layout') == 0)
            {
                $plain = true;
                continue;
            }

            if($plain)
            {
                if(strcmp($line,'\end_layout') == 0)
                {
                    $plain = false;
                    continue;
                }

                $tmp_array[] = '<div align="center">'.$line.'</div>';
            }
        }
    }

}
