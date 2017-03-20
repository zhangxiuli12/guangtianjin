<?php
namespace Home\Controller;

use Think\Controller;

class IndexController extends Controller
{

    public function qqlogin()
    {
        import("ORG.Connect.API.qqConnectAPI");
        $qc = new \QC();
        $qc->qq_login();


    }

    /**
     * app下载//TODO
     */
    public function download()
    {

        $download=M('download');
        $where['d_date'] = date('Y-m-d', time());
        $isExist = $download->where($where)->find();
        if ($isExist) {
            $reg['d_day'] = $isExist['d_day'] + 0 + 1;
            $reg['d_week'] = $isExist['d_week'] + 0 + 1;
            $reg['d_month'] = $isExist['d_month'] + 0 + 1;
            $reg['d_count'] = $isExist['d_count'] + 0 + 1;
            $reg['d_id'] = $isExist['d_id'];
            if($download->save($reg)){
                CommonController::json(1);
            }else{
                CommonController::json(-29);
            }
        } else {
            $reg['d_day'] = 1;
            $reg['d_date']=date('Y-m-d',time());
            $week['d_week_date'] = date('Y',time()).'第'.date('W', time()).'周';
            if ($weekcount = $download->where($week)->field('d_week')->order('d_id desc')->limit(1)->find()) {
                $reg['d_week'] = $weekcount['d_week'] + 0 + 1;
            } else {
                $reg['d_week'] = 1;
            }
            $month['d_month_date'] = date('Y-m', time());
            if ($monthcount = $download->where($month)->field('d_month')->order('d_id desc')->limit(1)->find()) {

                $reg['d_month']=$monthcount['d_month']+0+1;
            }else{
                $reg['d_month']=1;
            }
            $count=$download->field('d_count')->order('d_id desc')->limit(1)->find();
            $reg['d_count']=$count['d_count']+0+1;
            $reg['d_week_date']=date('Y',time()).'第'.date('W', time()).'周';
            $reg['d_month_date']=date('Y-m',time());
            if($download->add($reg)){
                CommonController::json(1);
            }else{
                CommonController::json(-29);
            }

        }
    }


}