<?php
class cards {

    public static $table_name = "cards";


    public static function findAll($id){
        $searchResult = ORM::for_table(self::$table_name)
            ->select('*')
            ->where('del_flg', 0)
            ->where('panels_id', $id)
            ->order_by_asc('order_key')
            ->find_many();
        return $searchResult;

    }

    public static function search($keyword){
        $searchResult = ORM::for_table(self::$table_name)
            ->table_alias('c')
            ->select('c.id')
            ->select('c.title')
            ->select('c.panels_id')
            ->select('p.boards_id')
            ->join('panels', 'c.panels_id = p.id', 'p')
            ->where_raw('(c.title LIKE ? OR contents LIKE ?)', array('%'.$keyword.'%', '%'.$keyword.'%'))
            ->where('c.del_flg', 0)
            ->order_by_asc('c.order_key')
            ->limit(100)
            ->find_many();
        return $searchResult;

    }


    public static function findLabel($labelId){
        $searchResult = ORM::for_table(self::$table_name)
            ->select('*')
            ->where('labelid', $labelId)
            ->where('del_flg', 0)
            ->order_by_asc('title')
            ->find_many();
        return $searchResult;
    }


    public static function lists($page, $keyword=''){

        $offset = 0;
        if($page[1] !== 1){
            $offset = ( ( $page[0] -1 ) * $page[1] );
        }
        $searchResult = ORM::for_table(self::$table_name)
            ->select('*')
            ->where_like('username', '%'.$keyword.'%')
            ->where('del_flg', 0)
            ->limit($page[1])
            ->offset($offset)
            ->order_by_desc('id')
            ->find_many();
        return $searchResult;
    }


    public static function find($id){
        $searchResult = ORM::for_table(self::$table_name)
            ->select('*')
            ->where('del_flg', 0)
            ->where('id', $id)
            ->find_one();
        return $searchResult;
    }


    public static function del($id){
        $delete = ORM::for_table(self::$table_name)->find_one($id);
        $delete->del_flg = 1;
        return $delete->save();
    }


    public static function create($array) {

        $lastOneResult = ORM::for_table(self::$table_name)
                ->select('*')
                ->where('del_flg', 0)
                ->order_by_desc('id')
                ->find_one();
        $newOrderNum = $lastOneResult['id'] + 1;

        $create = ORM::for_table(self::$table_name)->create();
        foreach ($array as $key => $value) {
            $create->{$key} = $value;
        }
        $create->order_key = $newOrderNum;
        $create->save();
        return $create->id();
    }


    public static function edit($id, $array){
        $edit = ORM::for_table(self::$table_name)->find_one($id);
        foreach ($array as $key => $value) {
            $edit->{$key} = $value;
        }
        return $edit->save();
    }

}
