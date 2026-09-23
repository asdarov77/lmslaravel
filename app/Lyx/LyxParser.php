<?php

namespace App\Lyx;

use Illuminate\Support\Facades\Config;
use App\Lyx\LyxFile;
use App\Lyx\LyxBook;


class LyxParser
{
    //public $course_data = array();
    // public $level = ['Title' => 0,'Chapter' => 1,'Section' => 2,'Subsection' => 3,'Subsubsection' => 4,'Subsubsection*' => 4,'Standard' => 5,'Plain Layout' => 5,'Itemize' => 5];
    // public $content_layout = ['Standard','Plain Layout','Itemize'];
    // public $parentnes = ['Section' => 'Chapter','Subsection'=>'Section','Subsubsection'=>'Subsection','Subsubsection*'=>'Subsection'];
    // public $parrent_id = ['Chapter' => 0,'Section'=>0 ,'Subsection'=>0 ,'Subsubsection'=>0];
    // public $current_id = ['Chapter' => 0,'Section'=>0 ,'Subsection'=>0 ,'Subsubsection'=>0];

    // public $course_strings = array();
    // public $lyx_files = array();
    // public $course_string = '';
    // public $course_chapter_strings = array();
    public $lyx_file;
    public $lyx_book;
    public $lyx_html;


    public function get_course_data(string $course, string $title)
    {

        $course_path = public_path() . Config::get('app.courses_path') . '/' . $course . '/' . $title;
        $course_path_html = Config::get('app.courses_path') . '/' . $course . '/' . $title; 
        $course_files = array_diff(scandir($course_path), array('..', '.'));
        $course_file = '';
        // foreach($course_files as $file)
        // {
        //     if(strcmp(substr($file,strlen($file)-3,3),'lyx') == 0)
        //     {
        //         $course_file = $file;                
        //         break;
        //     }
        // }

        foreach ($course_files as $file) {

            if (strcmp($file, 'index.html') == 0) {
                $course_file = $file;
                break;
            }
            return ($course_path_html . '/' . $course_file);            
        }


        //$this->lyx_file = new LyxFile($course_path . '/' . $course_file);        
        //va$this->lyx_book = new LyxBook($this->lyx_file->lyx_body());

        //return($this->lyx_book); 
        //    // return($this->lyx_file->lyx_body());
        //return($course_path_html . '/' . $course_file);    
    }
}
