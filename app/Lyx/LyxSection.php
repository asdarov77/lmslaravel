<?php
namespace App\Lyx;

use App\Lyx\LyxSubsection;
use App\Lyx\LyxToHtml;

class LyxSection
{
    public $title = '';
    public $body = '';
    public $subsection = array();
    private $lyx_body = '';
    private $footnotes = array();

    function __construct(string $lyx_body,$footnotes)
    {
        $this->lyx_body = $lyx_body;
        $this->footnotes = $footnotes;
        $this->extract_lyx_subsections();
    }

    public function extract_lyx_subsections()
    {
        $tmp_subsection_data = '';
        $title = false;
        $subsection_count = 0;

        $section_lyx_body_array = preg_split("/((\r?\n)|(\r\n?))/", $this->lyx_body);

        for($i = 0; $i < count($section_lyx_body_array); $i++)
        {
            $line = $section_lyx_body_array[$i];

            if(!$title)
            {
                if(strcmp($line,'\begin_layout Section') == 0)
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

            if(strcmp($line,'\begin_layout Subsection') == 0)
            {
                if($subsection_count == 0)
                {
                    $this->body = substr($this->body,0,strlen($this->body)-1);
                    $subsection_count++;
                }
                else
                {
                    $tmp_subsection_data = substr($tmp_subsection_data,0,strlen($tmp_subsection_data)-1);
                    $this->subsection[] = new LyxSubsection($tmp_subsection_data,$this->footnotes);
                    $subsection_count++;
                    $tmp_subsection_data = '';
                }
            }
            if($subsection_count > 0)
            {
                $tmp_subsection_data .= $line ."\n";
            }
            else
            {
                $this->body .= $line."\n";
            }
        }
        if(strlen($tmp_subsection_data) > 0)
        {
            $tmp_subsection_data = substr($tmp_subsection_data,0,strlen($tmp_subsection_data)-1);
            $this->subsection[] = new LyxSubsection($tmp_subsection_data,$this->footnotes);
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
