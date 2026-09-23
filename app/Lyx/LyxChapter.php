<?php
namespace App\Lyx;

use App\Lyx\LyxSection;
use App\Lyx\LyxToHtml;

class LyxChapter
{
    public $title = '';
    public $body = '';
    public $section = array();
    private $lyx_body = '';
    private $footnotes = array();

    function __construct(string $lyx_body,$footnotes)
    {
        $this->lyx_body = $lyx_body;
        $this->footnotes = $footnotes;
        $this->extract_lyx_sections();
    }

    public function extract_lyx_sections()
    {
        $tmp_section_data = '';
        $title = false;
        $section_count = 0;

        $chapter_lyx_body_array = preg_split("/((\r?\n)|(\r\n?))/", $this->lyx_body);

        for($i = 0; $i < count($chapter_lyx_body_array); $i++)
        {
            $line = $chapter_lyx_body_array[$i];

            if(!$title)
            {
                if(strcmp($line,'\begin_layout Chapter') == 0)
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

            if(strcmp($line,'\begin_layout Section') == 0)
            {
                if($section_count == 0)
                {
                    $this->body = substr($this->body,0,strlen($this->body)-1);
                    $section_count++;
                }
                else
                {
                    $tmp_section_data = substr($tmp_section_data,0,strlen($tmp_section_data)-1);
                    $this->section[] = new LyxSection($tmp_section_data,$this->footnotes);
                    $section_count++;
                    $tmp_section_data = '';
                }
            }
            if($section_count > 0)
            {
                $tmp_section_data .= $line ."\n";
            }
            else
            {
                $this->body .= $line."\n";
            }
        }
        if(strlen($tmp_section_data) > 0)
        {
            $tmp_section_data = substr($tmp_section_data,0,strlen($tmp_section_data)-1);
            $this->section[] = new LyxSection($tmp_section_data,$this->footnotes);
        }
    }

    public function body_to_html()
    {
        $html = new LyxToHtml($this->body,$this->footnotes);
        return($html->body_to_html());
    }

    public function is_body_empty()
    {
        $empty = true;
        if(strlen($this->body) > 1)
        {
            $empty = false;
        }
        return $empty;
    }

}
