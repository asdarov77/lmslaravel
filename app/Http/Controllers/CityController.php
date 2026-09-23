<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CityController extends Controller
{
      public function index() {
             $file = file_get_contents(resource_path('js/Pages/User/city.json'));
             $jsonData = json_decode($file, true);
             $jsonData = json_encode($jsonData, JSON_UNESCAPED_UNICODE);
             
             $string = file_get_contents(resource_path('js/Pages/User/city.json'));
             $json = json_decode($string, true);
             
             foreach ($json as $key => $value) {
                 if (!is_array($value)) {
                     echo $key . '=>' . $value . '<br />';
                 } else {
                     foreach ($value as $key => $val) {
                        // echo $key . '=>' . $val . '<br />';                
                                  
                     }
                 }
             }
             
      return $jsonData;
//return $file;
    }
}
