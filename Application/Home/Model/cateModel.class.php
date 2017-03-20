<?php
namespace Home\Model;

use Think\Model;

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/2/27 0027
 * Time: 14:58
 */
class cateModel extends Model
{
    //自动验证
    protected $_validate = array(

        array('c_name', '',-22, self::EXISTS_VALIDATE, 'unique', self::MODEL_BOTH),

    );
    //自动完成
    protected $_auto = array(
        array('c_hide','0'),//新增的时候把c_hide设为0
        array('c_rec', '0'),//新增的时候把c_rec设为0

    );

    //无限极分类
    public function cateTree()
    {
        $data = $this->select();
        return $this->restsort($data);
    }

    private function restsort($data,$parentid=0,$level=0)
    {
       static $arr=[];
       foreach($data as $k=>$v)
       {
           if($v['pid'] == $parentid)
           {
               $v['level'] = $level;
               $arr[] = $v;
               $this->restsort($data,$v['c_id'],$level+1);
           }
       }
        return $arr;

    }


    //通过主ID查找下面所有子ID
    public function getData($cateid)
    {
        $data = $this->select();
        return $this->getChileid($data,$cateid);
    }

    public function getChileid($data,$cateid)
    {
        static $arr=[];
        foreach($data as $k=>$v)
        {
            if($v['parentid'] == $cateid)
            {
                $arr[] = $v['id'];
                $this->getChileid($data,$v['id']);
            }
        }
        return $arr;
    }

}