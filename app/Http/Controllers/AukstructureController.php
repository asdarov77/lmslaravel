<?php

namespace App\Http\Controllers;
use App\Http\Filters\AukstructureFilter;
use App\Http\Requests\Aukstructure\FilterRequest;
use Illuminate\Http\Request;
use App\Models\Aukstructure;

/**
 * Временный контроллер для получения данных с таблицы Aukstructure 
 * с фильтрами. Возможно потом удалим. 
 * 
 */
class AukstructureController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(FilterRequest $request)
    {
        $data = $request->validated();
        $filter = app()->make(AukstructureFilter::class, ['queryParams' => array_filter($data)]);
        $aukstructure = Aukstructure::filter($filter)->get();
        return $aukstructure;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
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
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
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
        //
    }
}
