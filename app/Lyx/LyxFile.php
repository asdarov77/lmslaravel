<?php
namespace App\Lyx;
use Illuminate\Support\Facades\Config;

// use Illuminate\Support\Facades\Config;

class LyxFile
{
    public $file_path = '';
    public $html_file_path = '';
    public $file_data = '';
    private $lyx_body = '';
    public $includes = array();

    function __construct(string $file_path = '')
    {
        if(strlen($file_path) > 0)
        {
            $this->read_file_data($file_path);
        }
    }

    public function read_file_data(string $file_path)
    {
        $this->file_path = $file_path;
        $this->html_file_path = substr($file_path,strpos($file_path,Config::get('app.courses_path')));
        $this->file_data  = file_get_contents($file_path);
        $this->extract_lyx_body();
        $this->fix_links();
    }

    public function extract_lyx_body()
    {
        $tmp_string = '';
        $body = false;
        $include = false;

        foreach(preg_split("/((\r?\n)|(\r\n?))/", $this->file_data) as $line)
        {
            if($body)
            {
                if(strncmp($line,'\end_body',9) == 0)
                {
                    break;
                }
                if($include)
                {
                    if(strcmp(substr($line,0,8),'filename') == 0)
                    {
                        $split_str = explode('"',$line);
                        $this->includes[$split_str[1]] = new LyxFile(dirname($this->file_path).'/'.$split_str[1]);
                        $include = false;
                    }

                }
                else
                {
                    if(strpos($line,'include') > 0)
                    {
                        $include = true;
                    }
                }
                $tmp_string .= $line."\n";
            }
            else
            {
                if(strncmp($line,'\begin_body',11) == 0)
                {
                    $body = true;
                    continue;

                }
            }
        }
        $this->lyx_body = $tmp_string;
    }

    private function fix_links()
    {
        $this->fix_links_3D();
        $this->fix_links_graphics();
        $this->fix_links_href();
    }

    private function fix_links_3D()
    {
        $tmp_lyx_body = '';
        $standard = false;
        $note = false;
        $plain = false;
        $link_3d = 0;
        $plain_data = '';

        $lyx_body_array = preg_split("/((\r?\n)|(\r\n?))/", $this->lyx_body);

        for($i = 0; $i < count($lyx_body_array); $i++)
        {
            $line = $lyx_body_array[$i];
            if($standard)
            {
                if(strcmp($line,'\end_layout') == 0)
                {
                    if($plain)
                    {
                        $plain = false;
                    }
                    else
                    {
                        $standard = false;
                    }
                }
                if(strcmp($line,'\begin_inset Note Note') == 0)
                {
                    $note = true;
                }
                if($note)
                {
                    if(strcmp($line,'\end_inset') == 0)
                    {
                        $note = false;
                        $link_3d = 0;
                    }

                    if(strcmp($line,'\begin_layout Plain Layout') == 0)
                    {
                        $plain = true;
                        if($link_3d)
                        {
                            $link_3d++;
                        }
                        $tmp_lyx_body .= $line ."\n";
                        continue;
                    }

                    if($plain)
                    {
                        $plain_data = $line;
                        if(strcmp(substr($plain_data,0,2),'3D') == 0)
                        {
                            $link_3d++;
                        }

                        if($link_3d == 2)
                        {
                            $tmp_lyx_body .= dirname($this->html_file_path) . '/' . $plain_data . "\n";
                            continue;
                        }
                    }
                }
            }
            else
            {
                if(strcmp($line,'\begin_layout Standard') == 0)
                {
                    $standard = true;
                }
            }
            $tmp_lyx_body .= $line ."\n";
        }
        $this->lyx_body = $tmp_lyx_body;
    }

    public function fix_links_graphics()
    {
        $tmp_lyx_body = '';
        $plain = false;
        $graphics = false;

        $lyx_body_array = preg_split("/((\r?\n)|(\r\n?))/", $this->lyx_body);

        for($i = 0; $i < count($lyx_body_array); $i++)
        {
            $line = $lyx_body_array[$i];
            if($plain)
            {
                if(strcmp($line,'\end_layout') == 0)
                {
                    $plain = false;
                }
                if(strcmp($line,'\begin_inset Graphics') == 0)
                {
                    $graphics = true;
                }
                if($graphics)
                {
                    if(strcmp($line,'\end_inset') == 0)
                    {
                        $graphics = false;
                    }
                    if(strpos($line,'filename') > 0)
                    {
                        $split_str = explode(' ',$line);
                        $tmp_lyx_body .= '	filename '. dirname($this->html_file_path) . '/' . $split_str[count($split_str)-1] ."\n";
                        continue;
                    }
                }
            }
            else
            {
                if(strcmp($line,'\begin_layout Plain Layout') == 0)
                {
                    $plain = true;
                }
            }
            $tmp_lyx_body .= $line ."\n";
        }
        $this->lyx_body = $tmp_lyx_body;
    }

    public function fix_links_href()
    {
        $tmp_lyx_body = '';
        $href = false;

        $lyx_body_array = preg_split("/((\r?\n)|(\r\n?))/", $this->lyx_body);

        for($i = 0; $i < count($lyx_body_array); $i++)
        {
            $line = $lyx_body_array[$i];
            if($href)
            {
                if(strcmp(substr($line,0,6), 'target') == 0)
                {
                    $href = false;
                    $split_str = explode(' ',$line);
                    $tmp_lyx_body .= 'target "'. dirname($this->html_file_path) . '/'. substr($split_str[count($split_str)-1],1) ."\n";
                    continue;


                }
            }
            else
            {
                if(strcmp($line, 'LatexCommand href') == 0)
                {
                    $href = true;
                }

            }
            $tmp_lyx_body .= $line ."\n";
        }
        $this->lyx_body = $tmp_lyx_body;
    }

    public function lyx_body()
    {
        $join_lyx_body = '';

        $standard_start = -1;
        $standard_end = -1;
        $include = false;
        $filename_start = -1;

        // $join_lyx_body .= $this->html_file_path . "\n";
        // $join_lyx_body .= dirname($this->html_file_path) . "\n";

        $lyx_body_array = preg_split("/((\r?\n)|(\r\n?))/", $this->lyx_body);

        for($i = 0; $i < count($lyx_body_array); $i++)
        {
            $line = $lyx_body_array[$i];
            if($standard_start < 0)
            {
                if(strcmp($line,'\begin_layout Standard') == 0)
                {
                    $standard_start = $i;
                    continue;
                }
                else
                {
                    $join_lyx_body .= $line."\n";
                }
            }
            else
            {
                if(strpos($line,'include') > 0)
                {
                    $include = true;
                }
                if(strcmp(substr($line,0,8),'filename') == 0)
                {
                    $filename_start = $i;
                }
                if(strcmp($line,'\end_layout') == 0)
                {
                    $standard_end = $i;
                    if($include && $filename_start > 0)
                    {
                        $split_str = explode('"',$lyx_body_array[$filename_start]);
                        $join_lyx_body .= $this->includes[$split_str[1]]->lyx_body();
                    }
                    else
                    {
                        for($j = $standard_start; $j < $standard_end + 1; $j++)
                        {
                            $join_lyx_body .= $lyx_body_array[$j]."\n";
                        }
                    }
                    $standard_start = -1;
                    $standard_end = -1;
                    $include = false;
                    $filename_start = -1;
                }
            }

        }
        // }
        return $join_lyx_body;
    }
}
