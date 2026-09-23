<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Group2learning;
use App\Models\Aukstructure;

class LessonsController extends Controller
{
    //функция для построения дерева расписания занятий


    public function lessons()
    {
        $temp = Aukstructure::all();
        $g2l = Group2learning::all();
        $input = json_decode(json_encode($temp), true);
        $g2ljson = json_decode(json_encode($g2l), true);
        //var_dump($g2ljson);
        $result = array();
        foreach($g2ljson as $node)
        {
          //  print_r($node['id']);
        $navTree = $this->CreateTree($input); // плоское дерево
        //echo '<pre>';
        // print_r($navTree);
        $flatArr = $this->getParentsById($navTree, $node['id']); // найти по id
        $tree = $this->buildTree($flatArr); // иерархическое дерево
        array_push($result,$tree );    
    }
    

    //return $result;
        //return $tree;
        
        $mergedItems = [];

        foreach ($result as $subItems) {
            $mergedItems = array_merge_recursive($mergedItems, $subItems);
            //$groupedItems = $this->groupByParentId($item, $groupedItems);
        }
        return $mergedItems;
    //    return $groupedItems;
        //var_dump($groupedItems);

    }
//----------------удалить------------------------


    public function groupByParentId($items, $groupedItems, $parentId = 0) {
        foreach ($items as $item) {
            if ($item['parent_id'] == $parentId) {
                $groupedItems[] = $item;
                if (isset($item['children'])) {
                    $groupedItems = $this->groupByParentId($item['children'], $groupedItems, $item['id']);
                }
            }
        }
        return $groupedItems;
    }
//------------------удалить---------------------

    function CreateTree($array, $sub = 0, $tab = '')
    {
        $category = array();
        if ($sub > 0) {
            $tab .= '-';
        }
        foreach ($array as $v) {
            if ($sub == $v['parent_id']) {
                $category[$v['id']]['id'] = $v['id'];
                $category[$v['id']]['parent_id'] = $v['parent_id'];
                $category[$v['id']]['title'] = $tab . $v['title'];
                // $category[$v['id']]['study_from'] = $tab . $v['study_from'];
                // $category[$v['id']]['study_to'] = $tab . $v['study_to'];
                $category += $this->CreateTree($array, $v['id'], $tab);
            }
        }
        return $category;
    }

    function getParentsById($arr, $id, $response = [])
    {
        $key = $this->get_key($arr, $id);
        $response[] = $arr[$key];
        if ($arr[$key]['parent_id'] != 0) {
            $response = $this->getParentsById($arr, $arr[$key]['parent_id'], $response);
        }
        return $response;
    }



    function get_key($arr, $id)
    {
        foreach ($arr as $key => $val) {
            if ($val['id'] === $id) {
                return $key;
            }
        }
        return null;
    }


    public function buildTree(array $elements, $parentId = null)
    {
        $branch = [];

        foreach ($elements as $element) {
            if ($element['parent_id'] == $parentId) {
                $children = $this->buildTree($elements, $element['id']);
                if ($children) {
                    $element['children'] = $children;
                }
                $branch[] = $element;
            }
        }

        return $branch;
    }
}
