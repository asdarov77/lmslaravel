<?php

namespace App\Http\Controllers;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Http\Request;
use App\GiftParser\GiftParser;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

require_once __DIR__ . '/../Helpers/ParseHtmlHelper.php';

class GiftController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        return "ok index";
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
//    public function store(Request $request)
public function store($file)
    {
        if (!file_exists($file)) return;
      //  $file = $request->file('file');
      //  $gift = file_get_contents($file->getRealPath());

      ////$filePath = Config::get('app.courses_path').'test.txt';      
      $filePath =  $file;
      //echo(Config::get('app.courses_path').'test.txt');
      $gift = file_get_contents($filePath);
     // $gift = "This is a **bold** text.\n\n> This is a quote.\n\n* First item\n* Second item";
      //echo($gift);
      $parser = new GiftParser();
      $html = $parser->parse($gift);    
        // /return response()->json($html);    
        return $html;   

   

    }
   

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)    
    {

    }

    public function truncate()
    {
        DB::table('questions')->truncate();
        DB::table('answers')->truncate();

        return response()->json([
            'message' => 'Таблица questions & answers была успешно очищена'
        ]);        
    }

}
