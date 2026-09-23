<?php
namespace App\Lyx;

use App\Lyx\LyxToHtml;

class LyxSubsubsection
{
    public $title = '';
    public $body = '';
    private $lyx_body = '';
    private $footnotes = array();

    function __construct(string $lyx_body,$footnotes)
    {
        $this->lyx_body = $lyx_body;
        $this->footnotes = $footnotes;
        $this->exract_subsubsection_body();
    }

    public function exract_subsubsection_body()
    {
        $title = false;
        $body = false;

        $subsubsection_lyx_body_array = preg_split("/((\r?\n)|(\r\n?))/", $this->lyx_body);

        for($i = 0; $i < count($subsubsection_lyx_body_array); $i++)
        {
            $line = $subsubsection_lyx_body_array[$i];

            if(!$title)
            {
                if(strcmp(substr($line,0,27),'\begin_layout Subsubsection') == 0)
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
                    $body = true;
                    continue;
                }
                $this->title .= $line."\n";
                continue;
            }
            if($body)
            {
                $this->body .= $line."\n";
            }
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
