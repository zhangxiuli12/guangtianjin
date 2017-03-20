<?php
namespace Home\Controller;

use Think\Controller;
use Think\Model;

class ApiController extends CommonController
{

    /**
     * 验证手机号是否存在
     */
    public function checkMobileExist()
    {
        $m_user = M('Users');
        $this->checkPost(array(I('post.mobile'))); //判断值不为空
        $pattern = '/^1(3[0-9]|4[57]|5[0-35-9]|7[0135678]|8[0-9])\\d{8}$/'; //匹配移动、联通、电信手机号
        if (preg_match($pattern, I('post.mobile'))) {  //匹配是
            $data = $this->checkTelUnique($m_user, 'mobile', I('post.mobile'));
            CommonController::json(1, $data);
        } else {      //匹配不是
            CommonController::json(-52); //请填写正确的手机号
        }
    }


    /**
     * 发送短信
     * 'SMS_45650167' 注册模板
     * 'SMS_45650165' 找回密码模板
     */
    public function sendSms()
    {
        $m_usersVerify = M('UsersVerify');

        $this->checkPost(array(I('post.mobile'), I('post.type'))); //判断值不为空
        $data['code'] = $this->genExchangeCode(); //生成验证码

        $pattern = '/^1(3[0-9]|4[57]|5[0-35-9]|7[0135678]|8[0-9])\\d{8}$/'; //匹配移动、联通、电信手机号
        if (preg_match($pattern, I('post.mobile'))) {  //匹配是
            $where['mobile'] = array('eq', I('post.mobile'));
        } else {      //匹配不是
            CommonController::json(-52); //请填写正确的手机号
        }

        $telExist = $m_usersVerify->where($where)->find(); //查询该手机号是否存在
        if ($telExist) {  //该手机号存在 更新
            //判断上一次调用该接口的时间和当前时间相比是否在60秒内  少于60秒不让再发短信
            $addTimeArr = $m_usersVerify->where($where)->field('addTime')->find();
            $nowChuo = strtotime(date('Y-m-d H:i:s'));  //将时间转换成时间戳
            $addTimeChuo = strtotime(implode("", $addTimeArr));
            $diffTime = $nowChuo - $addTimeChuo;  //相差的秒数
            if ($diffTime < 120) {
                CommonController::json(-38);  //请两分钟之后再尝试发送
            } else {
                $newId = $m_usersVerify->where($where)->save($data);
            }
        } else { //该手机号不存在 新增
            $data['mobile'] = I('post.mobile');
            $newId = $m_usersVerify->add($data);
        }
        if ($newId > 0) {  //插入数据库成功
            switch (I('post.type')) {
                case "zhuce": //注册模板
                    $isSend = $this->sms(I('post.mobile'), $data['code'], 'SMS_45650167');
                    break;
                case "zhaohui": //找回密码模板
                    $isSend = $this->sms(I('post.mobile'), $data['code'], 'SMS_45650165');
                    break;
            }
            if ($isSend === true) {
                CommonController::json(1, $newId);
            } else {
                CommonController::json(-11, $isSend); //验证码发送失败
            }
        } else {
            CommonController::json(-10);  //验证码插入失败
        }
    }


    /**
     * 验证验证码是否正确
     */
    public function checkCode()
    {
        $m_usersVerify = M('UsersVerify');
        $data['mobile'] = I('post.mobile');
        $data['code'] = I('post.code');
        $where['mobile'] = array('eq', $data['mobile']);
        $where['code'] = array('eq', $data['code']);
        $codeInfo = $m_usersVerify->where($where)->find();
        if ($codeInfo) {  //判断手机号及验证码存在
            $now = date('Y-m-d H:i:s');
            $addTime = $codeInfo['addTime'];
            $minute = floor((strtotime($now) - strtotime($addTime)) % 86400 / 60);
            if ($minute > 15) {  //时间差大于15分钟
                CommonController::json(-12);  //验证码超时，请重新获取
            } else {
                CommonController::json(1);
            }
        } else {
            CommonController::json(-13);  //验证失败
        }
    }


    /**
     * 获取qq授权登录后get到的用户信息
     */
    public function thirdQQGetUserInfo()
    {
        require_once './ThinkPHP/Library/Org/Connect/API/qqConnectAPI.class.php'; //引入qq第三方登录
        require_once './ThinkPHP/Library/Org/Connect/API/class/QC.class.php';
        $qc = new \QC(I('post.qq_accesstoken'), I('post.qq_openid'));
        $userinfo = $qc->get_user_info();
        $info['sex'] = $userinfo['gender'];
        $info['avatar'] = $userinfo['figureurl_qq_2'];
        $info['nickname'] = $userinfo['nickname'];

//        $m_user = M('Users');
        //判断nickname不能重复
//        $nWhere['nickname'] = array('eq', $userinfo['nickname']);
//        $findNick = $m_user->where($nWhere)->select();
//        if ($findNick){  //存在同名nickname
//            $info['nickname'] = $userinfo['nickname'].'_'.rand(101, 888);
//        }else{
//            $info['nickname'] = $userinfo['nickname'];
//        }
        CommonController::json(1, $info);
    }


    /**
     * qq登录已有账号绑定qq
     */
    public function bindQQ()
    {
        // 已有账号绑定qq
        $this->checkPost(array(I('post.mobile'), I('post.password'), I('post.qq_openid')));  //检测传入值是否为空
        $mobile = I('post.mobile');
        $password = I('post.password');
        $where['mobile'] = array('eq', I('post.mobile'));
        $where['password'] = array('eq', md5(I('post.password')));
        $where['qq_openid'] = array('eq', '');
        $m_user = M('Users');
        $userExist = $m_user->where('(mobile="' . $mobile . '" and password="' . md5($password) . '" and qq_openid="") or (nickname="' . $mobile . '" and password="' . md5($password) . '" and qq_openid="")')->find();
        if ($userExist) {  //验证该用户名和密码正确 进行绑定
            $data['qq_openid'] = I('post.qq_openid');
            $userSave = $m_user->where('id=' . $userExist['id'])->save($data);
            if ($userSave !== false) {
                CommonController::json(1);
            } else {
                CommonController::json(-65); //绑定失败
            }
        } else { //验证不正确
            CommonController::json(-2); //用户名或者密码不正确
        }
    }


    /**
     * 获取微信授权登录后get到的用户信息
     */
    public function thirdWeChatGetUserInfo()
    {
        $userInfo = IndexController::oauth2_get_user_info(I('post.wx_accesstoken'), I('post.wx_openid'));
        $info['sex'] = $userInfo['sex'];
        $info['avatar'] = $userInfo['headimgurl'];
        $info['nickname'] = $userInfo['nickname'];
        $info['unionid'] = $userInfo['unionid'];
        CommonController::json(1, $info);
    }


    /**
     * 微信登录已有账号绑定微信
     */
    public function bindWeChat()
    {
        // 已有账号绑定微信
        $this->checkPost(array(I('post.mobile'), I('post.password'), I('post.unionid')));  //检测传入值是否为空
        $mobile = I('post.mobile');
        $password = I('post.password');
        $where['mobile'] = array('eq', I('post.mobile'));
        $where['password'] = array('eq', md5(I('post.password')));
        $where['weixin_unionid'] = array('eq', "");
        $m_user = M('Users');
        $userExist = $m_user->where('(mobile="' . $mobile . '" and password="' . md5($password) . '" and weixin_unionid="") or (nickname="' . $mobile . '" and password="' . md5($password) . '" and weixin_unionid="")')->find();
        if ($userExist) {  //验证该用户名和密码正确 进行绑定
            $data['weixin_unionid'] = I('post.unionid');
            $userSave = $m_user->where('id=' . $userExist['id'])->save($data);
            if ($userSave !== false) {
                CommonController::json(1);
            } else {
                CommonController::json(-65); //绑定失败
            }
        } else { //验证不正确
            CommonController::json(-2); //用户名或者密码不正确
        }
    }

    /**
     * 显示成员地区(注册时选择)
     */
    public function showCity()
    {
        $city = M('city');
        $map['code'] = array('between', array('120000', '120225'));
        $data = $city->where($map)->select();
        CommonController::json(1, $data);
    }


    /**
     * app用户注册
     *
     */
    public function userRegister()
    {
        $m_user = M('Userinfo');
        if (I('post.qq_openid')) {  //如果接收到qqOpenId 是qq授权登录的且没有账号完善资料
            $this->checkPost(array(I('post.nickname'), I('post.sex'), I('post.avatar'))); //判断值不为空
            //判断nickname不能重复
            $nWhere['nickname'] = array('eq', I('post.nickname'));
            $findNick = $m_user->where($nWhere)->select();
            if ($findNick) {  //存在同名nickname
                $data['u_nickname'] = I('post.nickname') . '_' . time();
            } else {
                $data['u_nickname'] = I('post.nickname');
            }
            $data['u_sex'] = I('post.sex');
            $data['u_icon'] = I('post.avatar');
            $data['qq_openid'] = I('post.qq_openid');
            $data['u_origin'] = 1;  //注册来源;1:qq;2:微信;3:手机号;
        } elseif (I('post.weixin_unionid')) {  //如果接收到weixin_unionid 是微信授权登录的且没有账号完善资料
            $this->checkPost(array(I('post.nickname'), I('post.sex'), I('post.avatar'))); //判断值不为空
            //判断nickname不能重复
            $nWhere['u_nickname'] = array('eq', I('post.nickname'));
            $findNick = $m_user->where($nWhere)->select();
            if ($findNick) {  //存在同名nickname
                $data['nickname'] = I('post.nickname') . '_' . time();
            } else {
                $data['u_nickname'] = I('post.nickname');
            }
            $data['u_sex'] = I('post.sex');
            $data['u_icon'] = I('post.avatar');
            $data['weixin_unionid'] = I('post.weixin_unionid');
            $data['u_origin'] = 2;  //注册来源;1:qq;2:微信;3:手机号;
        } else {  //普通注册
            //判断nickname不能重复
            $this->checkPost(array(I('post.nickname'), I('post.password'), I('post.mobile'), I('u_birth')));
            $nWhere['u_nickname'] = array('eq', I('post.nickname'));
            $findNick = $m_user->where($nWhere)->select();
            if ($findNick) {  //存在同名nickname
                CommonController::json(-59);  //该昵称已存在
            } else {
                $data['u_nickname'] = I('post.nickname');
            }
            $data['u_origin'] = 3;  //注册来源;1:qq;2:微信;3:手机号;
        }

        //nickname正常 可以注册
        $data['u_password'] = md5(I('post.password'));
        $data['u_city'] = I('post.u_city');
        $birth = str_replace('-', '.', substr('2017-03-15', 5, 5)) + 0;
        $data['constell'] = $this->constell($birth);

        $pattern = '/^1(3[0-9]|4[57]|5[0-35-9]|7[0135678]|8[0-9])\\d{8}$/'; //匹配移动、联通、电信手机号
        if (preg_match($pattern, I('post.mobile'))) {  //匹配是
            $data['u_phone'] = I('post.mobile');
        } else {      //匹配不是
            CommonController::json(-52); //请填写正确的手机号
        }

        $data['u_reg_time'] = date('Y-m-d H:i:s');
        $data['u_login_num'] = 1;
        $this->checkPost(array($data['u_phone'], $data['u_nickname'], $data['password']), $data['u_city']);  //检测传入值是否为空
        $this->checkTelUnique($m_user, 'u_phone', $data['u_phone']);  //判断该手机号是否唯一

        $newId = $m_user->add($data);
        if ($newId > 0) {
            $regnum = M('regnum');
            $where['r_date'] = date('Y-m-d', time());
            $isExist = $regnum->where($where)->find();
            if ($isExist) {
                $reg['r_day'] = $isExist['r_day'] + 0 + 1;
                $reg['r_week'] = $isExist['r_week'] + 0 + 1;
                $reg['r_month'] = $isExist['r_month'] + 0 + 1;
                $reg['r_count'] = $isExist['r_count'] + 0 + 1;
                $reg['r_id'] = $isExist['r_id'];
                $regnum->save($reg);
            } else {
                $reg['r_day'] = 1;
                $reg['r_date'] = date('Y-m-d', time());
                $week['r_week_date'] = date('Y', time()) . '第' . date('W', time()) . '周';
                if ($weekcount = $regnum->where($week)->field('r_week')->order('r_id desc')->limit(1)->find()) {
                    $reg['r_week'] = $weekcount['r_week'] + 0 + 1;
                } else {
                    $reg['r_week'] = 1;
                }
                $month['r_month_date'] = date('Y-m', time());
                if ($monthcount = $regnum->where($month)->field('r_month')->order('r_id desc')->limit(1)->find()) {
                    $reg['r_month'] = $monthcount['r_month'] + 0 + 1;
                } else {
                    $reg['r_month'] = 1;
                }
                $count = $regnum->field('r_count')->order('r_id desc')->limit(1)->find();
                $reg['r_count'] = $count['r_count'] + 0 + 1;
                $reg['r_week_date'] = date('Y', time()) . '第' . date('W', time()) . '周';
                $reg['r_month_date'] = date('Y-m', time());
                $regnum->add($reg);

            }


            CommonController::json(1, $newId);
        } else {
            CommonController::json(-5);  //注册失败
        }
    }

    /**
     * 根据生日判断星座
     */
    public function constell($s)
    {
        if ($s >= 3.21 && $s <= 4.19) {
            return '白羊座';
        } elseif ($s >= 4.20 && $s <= 5.20) {
            return '金牛座';
        } elseif ($s >= 5.21 && $s <= 6.21) {
            return '双子座';
        } elseif ($s >= 6.22 && $s <= 7.22) {
            return '巨蟹座';
        } elseif ($s >= 7.23 && $s <= 8.22) {
            return '狮子座';
        } elseif ($s >= 8.23 && $s <= 9.22) {
            return '处女座';
        } elseif ($s >= 9.23 && $s <= 10.23) {
            return '天秤座';
        } elseif ($s >= 10.24 && $s <= 11.22) {
            return '天蝎座';
        } elseif ($s >= 11.23 && $s <= 12.21) {
            return '射手座';
        } elseif ($s >= 12.22 && $s <= 1.19) {
            return '魔羯座';
        } elseif ($s >= 1.20 && $s <= 2.18) {
            return '水瓶座';
        } elseif ($s >= 2.19 && $s <= 3.20) {
            return '双鱼座';
        }
    }

    /**
     * 更改用户头像
     */
    public function userIcon()
    {

        $this->checkPost(array(I('post.u_id')));
        $userinfo = M('Userinfo');
        $u_id = I('post.u_id');
        $where['u_id'] = array('eq', $u_id);
        $isExist = $userinfo->where($where)->field('u_id,u_icon')->find();
        if ($_FILES['u_icon']['name'] != "") {
            $size = 5242880;
            $rootPath = './Public/uploads/cate/';
            $savePath = 'pic/';
            $type = array('jpg', 'png', 'jpeg');
            $ret = $this->upload($type, $rootPath, $savePath, array('uniqid', ''), $size);
            if ($ret['success'] == true) {
                foreach ($ret['info'] as $key => $value) {
                    $images[] = substr($rootPath, 1) . $savePath . $ret['info'][$key]['savename'];
                    $data['c_icon'] = implode(",", $images);

                }
                $this->thumb('.' . $data['c_icon'], '.' . $data['c_icon']);

                if ($userinfo->create($data)) {
                    //保存数据库
                    if ($userinfo->add()) {
                        CommonController::json(1);

                    } else {
                        CommonController::json(-29);  //新建失败
                    }
                } else {
                    CommonController::json($userinfo->getError());
                }
            }
        } else {
            CommonController::json(-19);  //图片上传失败，请按规范上传
        }
    }

    /**
     * 用户个人中心
     */
    public function userCenter()
    {
        $this->checkPost(array(I('post.u_id')));
        $u_id = I('post.u_id');
        $userinfo = M('userinfo');
        $isExist = $userinfo->field('u_id')->find($u_id);
        if ($isExist) {
            $data = $userinfo->field('u_lock,u_password', true)->find($u_id);
            CommonController::json(1, $data);//返回数据
        } else {
            CommonController::json(-9);//非法操作
        }
    }

    /**
     * 搜索活动
     */
    public function searAct()
    {
        $this->checkPost(array(I('post.searchBox')));
        $activity = M('activity');
        $where['ac_title'] = array("like", "%" . I('post.searchBox') . "%");
        $where['ac_lock'] = array("eq", 1);//活动开启的状态
        $data = $activity->where($where)->field('ac_id,t_id,ac_lock', true)->select();
        CommonController::json(1, $data);


    }

    /**
     * 显示预约活动
     */
    public function showActOrder()
    {
        $this->checkPost(array(I('post.u_id'), I('post.ac_id'), I('post.peonum')));
        $ac_id = I('post.ac_id');
        $where['ac_id'] = $ac_id;
        $activity = M('activity');
        $act = $activity->field('ac_title,ac_mobile,ac_address')->where($where)->find();
        $data['ac_mobile'] = $act['ac_mobile'];
        $data['ac_address'] = $act['ac_address'];
        $data['ac_title'] = $act['ac_title'];
        $data['peonum'] = I('post.peonum');
        $data['o_sn'] = 'HKT' . date('Ymd', time()) . I('post.ac_id') . I('post.u_id') . date('His', time()) . mt_rand(20, 90);
        CommonController::json(1, $data);
    }

    /**
     * 提交预约活动
     */
    public function actOrder()
    {
        $this->checkPost(array(I('post.ac_id'), I('post.u_id'), I('post.o_sn'), I('post.peonum')));
        $actOrd = M('activityOrder');
        $where['u_id'] = I('post.u_id');
        $where['ac_id'] = I('post.ac_id');
        $isExist = $actOrd->where($where)->field('o_id')->find();
        if ($isExist) {
            CommonController::json(-33);//预约已经存在
        } else {
            $actOrd->create();
            if ($actOrd->add()) {
                CommonController::json(1);
            } else {
                CommonController::json(-37);//预约失败
            }
        }


    }

    /**
     * 获取顶级分类
     */
    public function getCate()
    {
        $cate = M('cate');
        $where['pid'] = 0;
        $where['c_hide'] = 0;
        $data['list'] = $cate->where($where)->field('c_id,c_icon,c_name')->select();
        CommonController::json(1, $data);
    }

    /**
     * 获取顶级分类下面的子分类
     */
    public function childCate()
    {
        $this->checkPost(array(I('post.c_id')));
        $c_id = I('post.c_id');
        $cate = M('cate');
        $where['c_hide'] = 0;
        $data = $cate->field('c_id,c_name,c_icon,pid')->where($where)->select();
        $result = $this->getChild($c_id, $data);
        CommonController::json(1, $result);

    }

    /**
     * 无限极分类获取子孙树
     * @param $c_id
     * @param $data
     */

    public function getChild($c_id, $data)
    {
        static $arr = array();
        foreach ($data as $k => $v) {
            if ($v['pid'] == $c_id) {
                $arr[] = $v;
                unset($data[$k]);
                $this->getChild($v['c_id'], $data);
            }
        }
        return $arr;
    }

    /**
     * 搜索文章
     */
    public function searArt()
    {
        $this->checkPost(array(I('post.searchBox')));
        $article = M('article');
        $where['a_title'] = array("like", "%" . I('post.searchBox') . "%");
        $where['a_lock'] = array("eq", 1);//文章是上线的状态
        $data = $article->where($where)->field('a_id,c_id,a_lock', true)->select();
        CommonController::json(1, $data);
    }

    /**
     * 用户发布活动
     */
    public function createAct()
    {
        $this->checkPost(array(I('post.ac_title'), I('post.ac_source'), I('post.ac_startime'), I('post.ac_endtime'), I('post.ac_content'), I('post.ac_mobile'), I('post.ac_address')));
        $activity = M('Activity');
        $activity->create();
        if ($activity->add()) {
            CommonController::json(1);
        } else {
            CommonController::json(-29);
        }
    }

    /**
     * 根据栏目获取文章
     */
    public function cateArt()
    {
        $this->checkPost(array(I('post.c_id')));
        $cate = M('cate');
        $where['c_id'] = I('post.c_id');
        $isExist = $cate->where($where)->field('c_id')->find();
        if ($isExist) {
            $article = M('article');
            $data['list'] = $article->field('g_article.c_id,g_cate.c_name,g_article.a_title,g_article.a_author,g_article.a_pic,g_article.a_content')->join('INNER JOIN g_cate on g_article.c_id=g_cate.c_id')->select();
            CommonController::json(1, $data);
        } else {
            CommonController::json(-9);
        }
    }

    /**
     * 收藏文章(取消收藏)
     */
    public function collArt()
    {
        $this->checkPost(array(I('post.a_id'), I('post.u_id')));
        $where['a_id'] = I('post.a_id');
        $where['u_id'] = I('post.u_id');
        $data['a_id'] = I('post.a_id');
        $collect = M('articleCollect');
        $isCollect = $collect->where($where)->find();
        $data['id'] = $isCollect['id'];
        if ($isCollect) {//如果存在的话
            if ($isCollect['status'] == 1) {
                $data['status'] = 0;
            } else {
                $data['status'] = 1;
            }

            if ($collect->save($data) != false) {

                CommonController::json(1);
            } else {
                CommonController::json(-25);
            }
        } else {//不存在的话
            $collect->create();
            if ($collect->add()) {
                CommonController::json(1);
            } else {
                CommonController::json(-32);
            }
        }
    }

    /**
     *收藏活动(取消收藏)
     */
    public function collAct()
    {
        $this->checkPost(array(I('post.ac_id'), I('post.u_id')));
        $where['ac_id'] = I('post.ac_id');
        $where['u_id'] = I('post.u_id');
        $data['ac_id'] = I('post.ac_id');
        $collect = M('activityCollect');
        $isCollect = $collect->where($where)->find();
        $data['id'] = $isCollect['id'];
        if ($isCollect) {//如果存在的话
            if ($isCollect['status'] == 1) {
                $data['status'] = 0;
            } else {
                $data['status'] = 1;
            }

            if ($collect->save($data) != false) {

                CommonController::json(1);
            } else {
                CommonController::json(-25);
            }
        } else {//不存在的话
            $collect->create();
            if ($collect->add()) {
                CommonController::json(1);
            } else {
                CommonController::json(-32);
            }
        }
    }

    /**
     * 获取文章详细内容
     */
    public function artInfo()
    {
        $this->checkPost(array(I('post.a_id')));
        $article = M('article');
        $where['g_article.a_id'] = I('post.a_id');
        $isExist = $article->where($where)->field('a_id')->find();
        if ($isExist) {
            $data = $article->where($where)->field("g_article.a_id,g_cate.c_name,g_article.a_title,g_article.a_author,g_article.a_pic,g_article.a_content,g_article.a_time,g_article.showcount,count('g_article_collect.id') as collectcount")->join('INNER JOIN g_cate on g_article.c_id=g_cate.c_id')->join('inner join g_article_collect on g_article_collect.a_id=g_article.a_id')->find();
            $update['showcount'] = $data['showcount'] + 0 + 1;
            $update['a_id'] = I('post.a_id');
            $article->save($update);
            CommonController::json(1, $data);
        } else {
            CommonController::json(-9);
        }
    }

    /**
     * 查看个人收藏的活动
     */
    public function collectAct()
    {
        $this->checkPost(array(I('post.u_id')));
        $collect = M('ActivityCollect');
        $where['status'] = 1;
        $where['u_id'] = I('post.u_id');
        $data = $collect->field('g_activity.ac_id,g_activity.ac_title,g_activity.ac_mobile,g_activity.ac_address')->where($where)->join('inner join g_activity on g_activity.ac_id=g_activity_collect.ac_id ')->where('g_activity.ac_lock=1')->select();//查询收藏的活动
        CommonController::json(1, $data);

    }

    /**
     * 查看个人收藏的文章
     */
    public function collectArt()
    {
        $this->checkPost(array(I('post.u_id')));
        $collect = M('articleCollect');
        $where['status'] = 1;
        $where['u_id'] = I('post.u_id');
        $data = $collect->field('g_article.a_id,g_article.a_title,g_article.a_author,g_article.a_pic')->join('inner join g_article on g_article_collect.a_id=g_article.a_id')->where($where)->select();
        CommonController::json(1, $data);
    }

    /**
     * 文章跟帖
     */
    public function comArt()
    {
        $this->checkPost(array(I('post.u_id'), I('post.content'), I('post.a_id')));
        $com = M('ArticleComment');
        $data = $com->create();
        if ($_FILES['c_img']['name'] != "") {
            $size = 5242880;
            $rootPath = './Public/uploads/comment/';
            $savePath = 'artpic/';
            $type = array('jpg', 'png', 'jpeg');
            $ret = $this->upload($type, $rootPath, $savePath, array('uniqid', ''), $size);
            if ($ret['success'] == true) {
                foreach ($ret['info'] as $key => $value) {
                    $images[] = substr($rootPath, 1) . $savePath . $ret['info'][$key]['savename'];
                    $data['c_img'] = implode(",", $images);
                }
                $this->thumb('.' . $data['c_img'], '.' . $data['c_img']);
                if ($com->create($data)) {
                    //保存数据库
                    if ($com->add()) {
                        CommonController::json(1);
                    } else {
                        CommonController::json(-29);  //新建失败
                    }
                } else {
                    CommonController::json($com->getError());
                }
            }

        } else {
            if ($com->add()) {
                CommonController::json(1);
            } else {
                CommonController::json(-29);  //新建失败
            }
        }
    }

    /**
     * 文章顶跟帖
     */
    public function argeeArt()
    {
        $this->checkPost(array(I('post.id')));
        $com = M('ArticleComment');
        $where['id'] = I('post.id');
        $isExist = $com->where($where)->field('id,argee')->find();
        if ($isExist) {
            $argeeCount['argee'] = $isExist['argee'] + 0 + 1;
            $argeeCount['id'] = I('post.id');
            if ($com->save($argeeCount)) {
                CommonController::json(1);
            } else {
                CommonController::json(-25);
            }
        } else {
            CommonController::json(-9);
        }

    }

    /**
     * 投诉文章跟帖
     */
    public function callArt()
    {
        $this->checkPost(array(I('post.comment'), I('post.u_id'), I('post.c_id')));
        $call = M('ArticleCall');
        $data = $call->create();
        if ($call->add()) {
            CommonController::json(1);
        } else {
            CommonController::json(-29);
        }

    }

    /**
     * 回复文章跟帖
     */
    public function replyArt()
    {
        $this->checkPost(array(I('post.c_id'), I('post.comtent')));
        $reply = M('ArticleReply');
        $data = $reply->create();
        if ($_FILES['img']['name'] != "") {
            $size = 5242880;
            $rootPath = './Public/uploads/comment/';
            $savePath = 'reply/';
            $type = array('jpg', 'png', 'jpeg');
            $ret = $this->upload($type, $rootPath, $savePath, array('uniqid', ''), $size);
            if ($ret['success'] == true) {
                foreach ($ret['info'] as $key => $value) {
                    $images[] = substr($rootPath, 1) . $savePath . $ret['info'][$key]['savename'];
                    $data['img'] = implode(",", $images);
                }
                $this->thumb('.' . $data['img'], '.' . $data['img']);
                if ($reply->create($data)) {
                    //保存数据库
                    if ($reply->add()) {
                        CommonController::json(1);
                    } else {
                        CommonController::json(-29);  //新建失败
                    }
                } else {
                    CommonController::json($reply->getError());
                }
            }

        } else {
            if ($reply->add()) {
                CommonController::json(1);
            } else {
                CommonController::json(-29);  //新建失败
            }
        }

    }

    /**
     *文章顶数量
     */
    public function argeeCount()
    {
        $this->checkPost(array(I('post.id')));
        $argee = M('articleComment');
        $where['id'] = I('post.id');
        $isExist = $argee->where($where)->field('id,argee')->find();
        if ($isExist) {
            CommonController::json(1, $isExist);
        } else {
            CommonController::json(-9);
        }


    }

    /**
     * 打赏文章显示订单//TODO
     */
    public function giveMoney()
    {
        $this->checkPost(array(I('post.u_id'), I('post.a_id'), I('post.price')));
        $price = I('post.price');
        if ($price <= 0) {
            CommonController::json(-35);
        } else {
            $data['sn'] = 'WKT' . date('Ymd', time()) . I('post.a_id') . I('post.u_id') . date('His', time()) . mt_rand(20, 90);
            $data['price'] = $price;
            $data['msg'] = '打赏文章';
            $data['accept'] = '逛天津';
            CommonController::json(1, $data);
        }
    }


    /**
     * 生成缩略图
     */
    public function thumb($pic, $icon)
    {
        $image = new \Think\Image();
        $image->open($pic);// 按照原图的比例生成一个最大为50*50
        $image->thumb(50, 50)->save($icon);
    }


    /**
     * 搜索历史记录
     */
    public function searHis()
    {
        //判断cookie中是否存在信息
        $his = cookie('his');
        if (isset($his)) {
            var_dump($his);
        } else {
            echo 's';
            cookie('his', 'woaini', 3600);
            echo cookie('his');
        }
    }

    /**
     * 用户登录
     */
    public function userLogin()
    {
        $userinfo = M('Userinfo');
        $mobile = I('post.u_phone');
        $password = I('post.u_password');
        $where['u_phone'] = array('eq', $mobile);
        $where['u_password'] = array('eq', md5($password));
        $this->checkPost(array($mobile, $password));  //检测传入值是否为空
        $exist = $userinfo->where('(u_phone="' . $mobile . '" and u_password="' . md5($password) . '") or (u_nickname="' . $mobile . '" and u_password="' . md5($password) . '")')->find();
        if ($exist) {
            //登录次数+1
            $LoginNumArr = $userinfo->where($where)->field('u_login_num')->find();
            $orgLoginNum = implode("", $LoginNumArr);
            $data['u_login_num'] = $orgLoginNum + 1;
            $userinfo->where($where)->save($data);
            //生成Token
            $exist['token'] = $this->createToken($exist['u_id'], $mobile);
            CommonController::json(1, $exist);
        } else {
            CommonController::json(-2); //用户名或者密码不正确
        }
    }


    /**
     * 用户登出
     * (不需要验证Token)
     */
    public function userLogout()
    {
        $userToken = M('UsersToken');
        $token = I('post.token');
        $where['token'] = array('eq', $token);
        $tokenExist = $userToken->where($where)->select();
        if ($tokenExist) {
            if ($userToken->where($where)->delete()) {
                CommonController::json(1);
            }
        } else {

        }

    }


    /**
     * 找回密码 返回用户名/手机号
     */
    public function forgotPwd()
    {
        $input = I('post.input');
        $m_user = M('Users');
        $where['nickname'] = array('eq', $input);
        $where['mobile'] = array('eq', $input);
        $where['_logic'] = 'OR';
        $reUser = $m_user->field('nickname,mobile')->where($where)->find();
        if ($reUser) {
            CommonController::json(1, $reUser);
        } else {
            CommonController::json(-15); //该用户不存在,请重新注册
        }
    }


    /**
     * 重置密码 保存新密码
     */
    public function resetPwd()
    {
        $mobile = I('post.mobile');
        $this->checkPost(array(I('post.password'))); //判断值不为空
        $this->checkPost(array(I('post.rpassword'))); //判断值不为空
        $m_user = M('Users');
        if (I('post.password') == I('post.rpassword')) {  //两次输入密码一致
            $data['password'] = md5(I('post.password'));
            $where['mobile'] = array('eq', $mobile);
            $s = $m_user->where($where)->save($data);
            if ($s !== false) {
                CommonController::json(1);
            } else {
                $orgPwd = $m_user->where($where)->field('password')->find();
                if ($data['password'] == implode("", $orgPwd)) {  //修改的密码和原数据库的一致
                    CommonController::json(1);
                } else {
                    CommonController::json(-17);  //密码重置失败
                }
            }
        } else {
            CommonController::json(-16);  //两次输入密码不一致
        }
    }






    /*
     * 【【【前台首页】】】
     */


    /**
     * 首页获取切换热门城市选项卡
     */
    public function getSwitchCity()
    {
        $m_club = M('Club');
        $combine = $m_club
            ->join('z_city ON z_club.city = z_city.code')
            ->field('z_club.city as cityCode,z_city.city as cityName')
            ->where('z_club.status=1')
            ->group('z_club.city')
            ->select();
        CommonController::json(1, $combine);
    }


    /**
     * 用户保存个人资料/上传头像
     */
    /*
    public function saveUserInfo()
    {
        $userId = $this->getUserIdFromToken(); //通过Token获取userId
        $where['id'] = array('eq', $userId);

        $m_users = M('Users');

        if ($_FILES['avatar']['name'] == "") {  //保存资料接口
            $data['country'] = I('post.country');
            $data['city'] = I('post.city');
            $data['area'] = I('post.area');
            $data['address'] = I('post.address');
            $data['zipcode'] = I('post.zipcode');
            $data['sex'] = I('post.sex');

            $s = $m_users->where($where)->save($data);
            if ($s !== false) {
                CommonController::json(1, $data);
            } else {
                CommonController::json(-32); //设置失败
            }
        } else {  //上传头像接口
            $type = array('jpg', 'png');
            $rootPath = './Public/uploads/user/';
            $savePath = 'avatar/';
            $saveName = 'avatar_' . $userId;
            $ret = $this->upload($type, $rootPath, $savePath, $saveName, 5242880);
            if ($ret['success'] === true) {
                $data['avatar'] = substr($rootPath, 1) . $savePath . $ret['info']["avatar"]['savename'];
                $s = $m_users->where($where)->save($data);
                if ($s !== false) {
                    CommonController::json(1, $data);
                } else {
                    //删除图片文件
                    unlink('./Public/uploads/user/avatar/' . $ret['info']["avatar"]['savename']);
                    CommonController::json(-31); //图片上传失败
                }
            } else {
                CommonController::json(-19);  //图片上传失败，请按规范上传
            }
        }
    }
*/
    /**
     * 显示用户所有收货地址
     */
    public function showShAddress()
    {
        $this->checkPost(array(I('post.u_id')));
        $address = M('Shaddress');
        $where['u_id'] = I('post.u_id');
        $data = $address->where($where)->select();
        CommonController::json(1, $data);
    }

    /**
     * 用户新增/修改收货地址
     */
    public function addReceiptAddress()
    {
        $userId = $this->getUserIdFromToken(); //通过Token获取userId
        $addressId = I('post.id');
        $data['name'] = I('post.name');
        $data['province'] = I('post.province');
        $data['city'] = I('post.city');
        $data['region'] = I('post.region');
        $data['address'] = I('post.address');
        $data['zipcode'] = I('post.zipcode');
        $data['mobile'] = I('post.mobile');
        $data['fixedTelephone'] = I('post.fixedTelephone');
        $data['email'] = I('post.email');


        $where['u_id'] = array('eq', $userId);
        $where['name'] = array('eq', I('post.name'));
        $where['province'] = array('eq', I('post.province'));
        $where['city'] = array('eq', I('post.city'));
        $where['region'] = array('eq', I('post.region'));
        $where['address'] = array('eq', I('post.address'));
        $where['zipcode'] = array('eq', I('post.zipcode'));
        $where['mobile'] = array('eq', I('post.mobile'));
        $where['fixedTelephone'] = array('eq', I('post.fixedTelephone'));

        $m_address = M('Shaddress');

        if (!$addressId) {  //新增
            $this->checkPost(array(I('post.name'), I('post.province'), I('post.city'), I('post.region'), I('post.address'), I('post.zipcode'), I('post.mobile')));
            //判断地址不能重复提交
            $exist = $m_address->where($where)->find();
            if ($exist) {  //该地址已存在
                CommonController::json(-48);  //该地址已存在,请勿重复提交
            } else {  //地址不存在 可新建
                //判断是否设置过默认地址 因为默认地址只能设置1个
                if (I('post.isDefault') == 1) { //设置了默认地址
                    //需要去查是否已设置过-查询地址表里isDefault字段为1的个数
                    $d = $m_address->where('u_id=' . $userId)->field('isDefault')->select();
                    $selectCount = 0;
                    for ($i = 0; $i < sizeof($d); $i++) {
                        if ($d[$i]["isDefault"] == 1) {
                            $selectCount++;
                        }
                    }
                    if ($selectCount == 1) {  //如果值为1就是设置过
                        //取消原来设置的置成0
                        $z = $m_address->where('isDefault=1')->field('id')->find();
                        $set['isDefault'] = 0;
                        $setOld = $m_address->where('id=' . implode("", $z))->save($set);
                        if ($setOld !== false) {
                            //设置新的
                            $data['isDefault'] = 1;
                        } else {
                            CommonController::json(-32);  //设置失败
                        }
                    } else {  //如果值不存在就没有设置过，就可以设置
                        $data['isDefault'] = 1;
                    }
                } elseif (I('post.isDefault') == 0) {
                    $data['isDefault'] = 0;
                } else {
                    CommonController::json(-9);  //非法操作
                }
                $data['u_id'] = $userId;
                $newId = $m_address->add($data);
                if ($newId > 0) {
                    CommonController::json(1, $newId);
                } else {
                    CommonController::json(-29);  //新建失败
                }
            }
        } else {

            //修改
            $this->checkPost(array($addressId));
            //判断地址不能重复提交
            $exist = $m_address->where($where)->find();
            if ($exist) {  //该地址已存在
                CommonController::json(-48);  //该地址已存在,请勿重复提交
            } else {  //地址不存在 可修改

                $whereU['u_id'] = array('eq', $userId);
                $whereU['id'] = array('eq', $addressId);

                //判断是否设置过默认地址 因为默认地址只能设置1个
                if (I('post.isDefault') == 1) { //设置了默认地址
                    //需要去查是否已设置过-查询地址表里isDefault字段为1的个数
                    $d = $m_address->where('userId=' . $userId)->field('isDefault')->select();
                    $selectCount = 0;
                    for ($i = 0; $i < sizeof($d); $i++) {
                        if ($d[$i]["isDefault"] == 1) {
                            $selectCount++;
                        }
                    }
                    if ($selectCount == 1) {  //如果值为1就是设置过
                        //取消原来设置的置成0
                        $z = $m_address->where('isDefault=1')->field('id')->find();
                        $set['isDefault'] = 0;
                        $setOld = $m_address->where('id=' . implode("", $z))->save($set);
                        if ($setOld !== false) {
                            //设置新的
                            $data['isDefault'] = 1;
                        } else {
                            CommonController::json(-32);  //设置失败
                        }
                    } else {  //如果值不存在就没有设置过，就可以设置
                        $data['isDefault'] = 1;
                    }
                } elseif (I('post.isDefault') == 0) {
                    $data['isDefault'] = 0;
                } else {
                    CommonController::json(-9);  //非法操作
                }
                $saveId = $m_address->where($whereU)->save($data);
                if ($saveId !== false) {
                    CommonController::json(1);
                } else {
                    CommonController::json(-25);  //修改失败
                }
            }
        }
    }

    /**
     * 退出登录
     */
    public function logout()
    {
        $this->checkPost(array(I('post.u_id')));
        $where['u_id'] = I('post.u_id');
        $token = M('UsersToken');
        if ($token->where($where)->delete()) {
            CommonController::json(1);
        }else{
            CommonController::json(-68);
        }
    }


}