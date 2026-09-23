<?php
namespace App\Lyx;

use App\Lyx\LyxToHtml;

class LyxSubsection
{
    public $title = '';
    public $body = '';
    public $subsubsection = array();
    private $lyx_body = '';
    private $footnotes = array();

    function __construct(string $lyx_body,$footnotes)
    {
        $this->lyx_body = $lyx_body;
        $this->footnotes = $footnotes;
        $this->extract_lyx_subsubsections();
    }

    public function extract_lyx_subsubsections()
    {
        $tmp_subsubsection_data = '';
        $title = false;
        $subsubsection_count = 0;

        $subsection_lyx_body_array = preg_split("/((\r?\n)|(\r\n?))/", $this->lyx_body);

        for($i = 0; $i < count($subsection_lyx_body_array); $i++)
        {
            $line = $subsection_lyx_body_array[$i];

            if(!$title)
            {
                if(strcmp($line,'\begin_layout Subsection') == 0)
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

            if(strcmp(substr($line,0,27),'\begin_layout Subsubsection') == 0)
            {
                if($subsubsection_count == 0)
                {
                    $this->body = substr($this->body,0,strlen($this->body)-1);
                    // $this->body = substr($this->body,0,strrpos($this->body,'\end_layout'));
                    $subsubsection_count++;
                }
                else
                {
                    $tmp_subsubsection_data = substr($tmp_subsubsection_data,0,strlen($tmp_subsubsection_data)-1);
                    $this->subsubsection[] = new LyxSubsubsection($tmp_subsubsection_data,$this->footnotes);
                    $subsubsection_count++;
                    $tmp_subsubsection_data = '';
                }
            }
            if($subsubsection_count > 0)
            {
                $tmp_subsubsection_data .= $line ."\n";
            }
            else
            {
                $this->body .= $line."\n";
            }
        }
        if(strlen($tmp_subsubsection_data) > 0)
        {
            $tmp_subsubsection_data = substr($tmp_subsubsection_data,0,strlen($tmp_subsubsection_data)-1);
            $this->subsubsection[] = new LyxSubsubsection($tmp_subsubsection_data,$this->footnotes);
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
        if(count($this->subsubsection) > 0)
        {
            $empty = false;
        }
        return $empty;
    }
}
