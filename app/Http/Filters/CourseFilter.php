<?php

namespace App\Http\Filters;

use Illuminate\Database\Eloquent\Builder;
use App\Models\Aukstructure;
use App\Models\Course;
use App\Models\Category;

class CourseFilter extends AbstractFilter
{
    public const TITLE = 'title';
    public const PATH = 'path';
    public const AIRCRAFT_ID = 'aircraft_id';
    public const CATEGORY_ID = 'category_id';        
    public const PARENT_ID = 'parent_id';
    public const TYPE_ID = 'type_id';
    public const COURSE_ID = 'course_id';


    protected function getCallbacks(): array
    {
        return [
            self::TITLE => [$this, 'title'],
            self::PATH => [$this, 'path'],
            self::AIRCRAFT_ID => [$this, 'aircraftId'],
            self::CATEGORY_ID => [$this, 'categoryId'],            
            self::PARENT_ID => [$this, 'parentId'],
            self::TYPE_ID => [$this, 'typeId'],
            self::COURSE_ID => [$this, 'courseId']

        ];
    }
    //-------рабочий--------------------------
//     public function catWcor(Builder $builder, $cat_id, $cor_id)
//     {       
//  {
//      $builder->whereHas('categories', function ($query) use ($cat_id) {
//          $query->where('categories.id', '=', $cat_id);
//      })->where('id', '=', $cor_id);

//      $builder->with(['aukstructures', 'categories']);
//  }
//     }

    public function title(Builder $builder, $value)
    {
        $builder->where('title', 'like', "%{$value}%");
    }
    public function path(Builder $builder, $value)
    {
        $builder->where('path', 'like', "%{$value}%");
    }

    public function aircraftId(Builder $builder, $value)
    {
        $builder->where('aircraft_id', $value);
    }

    // public function categoryId(Builder $builder, $value)
    // {

    //     $builder->withWhereHas('categories', function ($q) use ($value) {

    //         $q->where("category_id", $value); // берет из pivot таблицы category-course
    //     })->withWhereHas('aukstructures', 
    //     function ($qq) {
    //     //    $qq->where("type", "!=",0);        
    //     });
    //     // $builder->whereHas('categories', function ($q) use ($value) {
    //     //     $q->where('category_id', $value);
    //     // })
    //     // ->whereHas('aukstructures', function ($qq) use ($value) {
    //     //     $qq->where('category_id', $value);
    //     // })
    //     ;
    // }

    // public function categoryId(Builder $builder, $categoryId)
    // {
    //     // // Фильтруем коллекцию по category_id из categories
    //     // -------------------работает------------------------------------
    //     $builder->withWhereHas('categories', function ($query) use ($categoryId) {
    //         $query->where('categories.id', '=', $categoryId);
    //     });
    //         // Фильтруем коллекцию по category_id из categories
    // // Фильтруем коллекцию по category_id из categories
    // // $builder->whereHas('categories', function ($query) use ($categoryId) {
    // //     $query->where('id', '=', $categoryId);
    // // });
    // // $builder->withWhereHas('aukstructures', function ($query) {
    // //     $query->where('categories', 'like', 'OB');
    // // });
    // // Загружаем все связанные aukstructure и categories
    // $builder->with(['aukstructures', 'categories']);
    // //$builder->whereHas('aukstructures',function ($query){ $query->where('aukstructures.categories', 'like', "OB"); });
        
    //     // -------------------работает------------------------------------


    //     // Фильтруем коллекцию по category_id из categories и возвращаем только необходимые поля 'id' и 'title' из таблицы 'aukstructures'
    //     // $builder->with(['aukstructures' => function ($query) use ($categoryId) {
    //     //     return $query->whereHas('categories', function ($query) use ($categoryId) {
    //     //         $query->where('categories.id', '=', $categoryId);
    //     //     });
    //     // }]);
    //     // $request = request();
    //     // if ($request->has('category_id')) {
    //     //     $categoryId = $request->query('category_id');
    //     //     $builder->with(['aukstructures' => function ($query) use ($categoryId) {
    //     //         $query->whereHas('categories', function ($query) use ($categoryId) {
    //     //             $query->where('categories.id', '=', $categoryId);
    //     //         });
    //     //     }]);
    //     // }

    //     // return $builder;


    // }



//     public function categoryId(Builder $builder, $categoryId)
// {
//     $builder->whereHas('categories', function ($query) use ($categoryId) {
//         $query->where('categories.id', '=', $categoryId);
//     });
//     //->where('id', '=', $courseId);

//  //   $builder->with(['aukstructures', 'categories']);
//  $builder->with( 'categories');

// }

public function categoryId(Builder $builder, $categoryId)
{
    $categoryCode = Category::find($categoryId)->code;
    $categoryCode = trim($categoryCode);
    
        // // Фильтруем коллекцию по category_id из categories
        $builder
        ->withWhereHas('aukstructures' , function ($query) use ($categoryCode) {
            $query-> where('categories', 'like', "%{$categoryCode}%")  
            ->orWhere('categories', '=', '')
            ->where('type','=' , 0);
        })
        ->whereHas('categories', function ($query) use ($categoryId) {
            $query->where('categories.id', '=', $categoryId);
        });



    // $builder->whereHas('categories', function ($query) use ($categoryId) {
    //     $query->where('categories.id', '=', $categoryId);
    // });

    

   // $builder->with(['aukstructures', 'categories']);
}

    public function parentId(Builder $builder, $value)
    {
        $builder->withWhereHas('aukstructures', function ($q) use ($value) {
            $q->where("parent_id", $value);
        });
    }

    public function courseId(Builder $builder, $value)
    {
        // Ищем курс с указанным id
        $course = Course::where('id', $value)->with('aukstructures')->first();        
        if ($course) {
            // Если курс найден, то возвращаем его
            $builder->where('id', $course->id)->with('aukstructures');
            
        } else {
            // Если курс не найден, то ищем по aukstructure.id
            $course_auk=Aukstructure::find($value)->course_id;
            // $builder->where('id', $course_auk)->with('aukstructures');            
            $builder->where('id', $course_auk)->orWhereHas('aukstructures', function ($query) use ($value) {
                $query->where('parent_id', $value);
            })->with('aukstructures');
        // $builder->orWhereHas('aukstructures', function ($query) use ($value) {
        //     $query->where('parent_id', $value);
        // })->with('aukstructures');
        }    
    }


    public function typeId(Builder $builder, $value)
    {
        $builder->withWhereHas("aukstructures", function ($q) use ($value) {
            $q->where("type", $value);
        });
    }


    private function findChildren($value)
{
    $children = [];
    $aukstructures = Aukstructure::where('parent_id', $value)->get();

    foreach ($aukstructures as $aukstructure) {
        $children[] = $aukstructure;
        $children = array_merge($children, $this->findChildren($aukstructure->id));
    }

    return $children;
}
}



//-------------------мучения-------------
        // Загружаем все связанные aukstructure categories
         //$builder->with('aukstructures');
         

//  // Ищем курс с указанным id
//   $course = Course::where('id', $value)->with('aukstructures')->first();

//  if ($course) {
//      // Если курс найден, то возвращаем его
//      $builder->where('id', $course->id)
//              ->with('categories', 'aukstructures')
//              ->withWhereHas('aukstructures', function ($query) use ($course) {
//                  $query->where('course_id', $course->id)
//                        ->where(function ($query) {
//                            $query->where('categories', '=', '')
//                                  ->orWhereNotNull('categories');
//                        });
//              });
//  } else {
//      // Если курс не найден, то возвращаем пустую коллекцию
//      $builder->where('id', 0);
//  }
