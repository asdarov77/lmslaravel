<?php

namespace App\Http\Controllers;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //$objArr = array();
        //if (Auth::user()->role == "Администратор") {
        $category = Category::all();
        return $category; 
       
    //}
    //else {
    // $cat =  Auth::user()->categories; 
    // foreach ($cat as $item) {
    //     array_push($objArr, $item);
    // }


   // return $objArr;
//}
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'code' => 'nullable|string|max:50',
            'aircraft_id' => 'nullable|integer|exists:aircrafts,id'
        ]);

        $category = Category::create($validated);
        
        return response()->json([
            'success' => true,
            'data' => $category,
            'error' => null,
            'meta' => null
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $category = Category::find($id);          
        return $category;
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
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'code' => 'nullable|string|max:50',
            'aircraft_id' => 'nullable|integer|exists:aircrafts,id',
        ]);

        $category = Category::findOrFail($id);
        // поддержка алиаса name (используется во фронтенде и части тестов)
        if ($request->has('name') && !$request->has('title')) {
            $validated['title'] = $request->input('name');
        }
        $category->fill($validated);
        $category->save();

        return response()->json([
            'success' => true,
            'data' => $category->fresh(),
            'error' => null,
            'meta' => null,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();
        return response()->json([
            'success' => true,
            'data' => null,
            'error' => null,
            'meta' => null,
        ], 200);
    }
}
