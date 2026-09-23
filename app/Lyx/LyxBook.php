<?php

namespace App\Lyx;

use App\Lyx\LyxChapter;
use App\Lyx\LyxToHtml;

class LyxBook
{
    public $title = '';
    public $body = '';
    public $chapter = array();
    private $lyx_body = '';
    public $footnotes = array();


    function __construct(string $lyx_body)
    {
        $this->lyx_body = $lyx_body;
        $this->extract_footnotes();
        // error_log(print_r($this->footnotes));
        $this->extract_lyx_chapters();
    }

    public function extract_lyx_chapters()
    {
        $tmp_chapter_data = '';
        $title = false;
        $chapter_count = 0;

        $book_lyx_body_array = preg_split("/((\r?\n)|(\r\n?))/", $this->lyx_body);

        for($i = 0; $i < count($book_lyx_body_array); $i++)
        {
            $line = $book_lyx_body_array[$i];

            if(!$title)
            {
                if(strcmp($line,'\begin_layout Title') == 0)
                {
                    $title = true;
                    continue;
                }
            }
            else
            {
                if(strcmp($line,'\end_layout')==0)
                {
                    $this->title = substr($this->title,0,strlen($this->title)-1);
                    $title = false;
                    continue;
                }
                $this->title .= $line."\n";
                continue;
            }

            if(strcmp($line,'\begin_layout Chapter') == 0)
            {
                if($chapter_count == 0)
                {
                    $this->body = substr($this->body,0,strlen($this->body)-1);
                    $chapter_count++;
                }
                else
                {
                    $tmp_chapter_data = substr($tmp_chapter_data,0,strlen($tmp_chapter_data)-1);
                    $this->chapter[] = new LyxChapter($tmp_chapter_data,$this->footnotes);
                    $chapter_count++;
                    $tmp_chapter_data = '';
                }
            }
            if($chapter_count > 0)
            {
                $tmp_chapter_data .= $line ."\n";
            }
            else
            {
                $this->body .= $line."\n";
            }
        }
        if(strlen($tmp_chapter_data) > 0)
        {
            $tmp_chapter_data = substr($tmp_chapter_data,0,strlen($tmp_chapter_data)-1);
            $this->chapter[] = new LyxChapter($tmp_chapter_data,$this->footnotes);
        }
    }

    public function body_to_html()
    {
        $html = new LyxToHtml($this->body,$this->footnotes);
        return($html->body_to_html());
    }

    public function extract_footnotes()
    {
        $footnote = false;
        $plain = false;
        $label = false;

        $key = '';
        $value = '';

        $book_lyx_body_array = preg_split("/((\r?\n)|(\r\n?))/", $this->lyx_body);

        for($i = 0; $i < count($book_lyx_body_array); $i++)
        {
            $line = $book_lyx_body_array[$i];

            if($footnote)
            {
                if(strcmp($line,'\end_inset') == 0)
                {
                    if(!$label)
                    {
                        $this->footnotes[$key] = $value;
                        $footnote = false;
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
            else
            {
                if(strcmp($line,'\begin_inset Foot') == 0)
                {
                    $footnote = true;
                }
            }

        }
    }

}
