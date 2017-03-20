<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/9/13 0013
 * Time: 17:02
 */


function getMsgByCode($code)
{
    include_once dirname(__FILE__) . "/../../Common/Common/L8n.php";
//    $codeInfo = new \L8n();
    return $codeInfo[$code];
}

/**
 * 判断登录设备
 */
function isMobile()
{
    $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
    //分析数据
    $is_pc = (strpos($agent, 'windows nt')) ? true : false;
    $is_iphone = (strpos($agent, 'iphone')) ? true : false;
    $is_ipad = (strpos($agent, 'ipad')) ? true : false;
    $is_android = (strpos($agent, 'android')) ? true : false;

    //输出数据
    if ($is_pc) {
        return 'pc';
    }
    if ($is_iphone) {
        return 'mobile';
    }
    if ($is_ipad) {
        return 'mobile';
    }
    if ($is_android) {
        return 'mobile';
    }
    return 'unknown';
}