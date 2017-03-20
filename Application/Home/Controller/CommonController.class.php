<?php
/**
 * Created by PhpStorm.
 * User: sleep
 * Date: 16/12/7
 * Time: 上午10:34
 */

namespace Home\Controller;

use Think\Controller;

class CommonController extends Controller
{
    public function _initialize()
    {
        $cmd = I('post.cmd');
        if (!method_exists($this, $cmd)) {
            $this::json(-1);
        } else {
            //如果需要Token
            if ($this->needToken($cmd)) {
                if (!I('post.token')) {
                    CommonController::json(-14); //请传递Token
                }
            }
            $this->$cmd();
        }
    }


    //不需要Token的列表 
    private function needToken($cmd)
    {
        $noNeedTokenCmd = array(
            'checkMobileExist',
            'sendSms',
            'checkCode',
            'thirdQQGetUserInfo',
            'bindQQ',
            'userRegister',
            'userLogin',
            'createYzm',
            'forgotPwd',
            'resetPwd',
            'selectCityDrop',
            'searchBox',
            'getExistCityDrop',
            'getExistClubDrop',
            'searchClubName',
            'getClubShow',
            'getSwitchCity',
            'getCourseShow',
            'getCurrentCity',
            'selectAllCityDrop',
            'clubPickList',
            'getPerClubDetail',
            'getPerClubAlbum',
            'getOwnClubCourse',
            'getTeachPower',
            'clubGetUserEvaluate',
            'userLogout',
            'coursePickList',
            'getPerCourseDetail',
            'courseGetUserEvaluate',
            'clubGetPickList',
            'courseGetPickList',
            'homeRankList',
            'qqlogin',
            'qqCallBack',
            'getCulList',
            'getCulDetails',
            'culGetEvaluateList',
            'culGetReplyEvaluateList',
            'culSearchList',
            'rankClubList',
            'rankCourseList',
            'culSearchQinQuList',
            'rankCultureList',
            'homeSearchClub',
            'homeSearchCourse',
            'homeSearchCulture',
            'homeSearchKeyWords',
            'thirdWeChatGetUserInfo',
            'bindWeChat',
            'qinWantToBuy',
            'getAllCate',
            'getRecomOrHot',
            'getTailsList',
            'getTailsDetails',
            'sendFeedBack',
            'emailSubscribe',
            'userCenter',
            'searArt',
            'searAct',
        );
        if (in_array($cmd, $noNeedTokenCmd)) {
            return false;
        } else {
            // 检查Token
            $userToken = M('UsersToken');
            $where['token'] = I('post.token');
            $data = $userToken->where($where)->find();
            if (!$data) {
                CommonController::json(-6);
            }

        }
    }


    public function index()
    {
    }

    /**
     * 判断传入的数据是否都存在
     * @param $postArray string 传递的参数
     */
    public function checkPost($postArray)
    {
        $rst = true;
        foreach ($postArray as $value) {
            if ($value === "") {
                CommonController::json(-3);  //所填数据不能为空
                return false;
            } else {
                $rst = true;
            }
        }
        if ($rst) {
            return $rst;
        } else {
            CommonController::json(-3);  //所填数据不能为空
            return false;
        }
    }

    /**
     * 判断手机号是否唯一
     * @param $m unknown  数据库表名
     * @param $data_field string 数据库手机号字段名
     * @param $post_field string 接收的手机号参数
     */
    public function checkTelUnique($m, $data_field, $post_field)
    {
        $where[$data_field] = array('eq', $post_field);
        $telExist = $m->where($where)->find();
        if ($telExist) {  //该手机号已存在
            CommonController::json(-4);  //该用户已注册，请直接登录
        } else {
            return true;
        }
    }

    /**
     * 判断字段值是否唯一
     * @param $m unknown  数据库表名
     * @param $data_field string 数据库手机号字段名
     * @param $post_field string 接收的手机号参数
     */
    public function checkFieldUnique($m, $data_field, $post_field)
    {
        $where[$data_field] = array('eq', $post_field);
        $fieldExist = $m->where($where)->find();
        if ($fieldExist) {  //存在即正常
            return true;
        } else {
            CommonController::json(-8);  //传入的字段错误
        }
    }


    /**
     * 生成新Token
     * @param $userId
     * @param $mobile
     */
    public function createToken($userId, $mobile)
    {
        $this->checkPost(array($userId, $mobile));  //判断传入值不能为空
        $userinfo = M('Userinfo');
        $this->checkFieldUnique($userinfo, 'u_id', $userId);   //判断传入值是否存在于数据库
        $data['token'] = md5($userId . $mobile . time());
        $usersToken = M('UsersToken');
        $where['u_id'] = array('eq', $userId);
        try {  //新增
            $data['u_id'] = $userId;
            $newId = $usersToken->add($data);
        } catch (\Exception $e) {  //更新
            $msg = $e->getMessage();
            $msg = explode(':', $msg);
            if ($msg[0] === '1062') {
                $newId = $usersToken->where($where)->save($data);
            }
        }
        if ($newId > 0) {
            return $data['token'];
        } else {
            CommonController::json(-7);  //Token生成失败
        }
    }


    /**
     * 检查Token
     * @param $userId
     * @param int $timeout token有效期30天=720小时
     * @return mixed|string
     */
    public function checkToken($token)
    {
        $userToken = M('UsersToken');
        $where['token'] = $token;
        $data = $userToken->where($where)->find();
        $now = date('Y-m-d H:i:s');
        $hour = floor((strtotime($now) - strtotime($data['validDate'])) % 86400 / 3600);
        if ($data && $hour < 720) {
            return true;
        } else {
            return false;
//            CommonController::json(-6);  //Token无效
        }
    }


    /**
     * 验证短信是否发送成功
     */
    public function sms($mobile, $code, $template)
    {
        $sms = new \Home\Utils\SendSms();
        $status = $sms->send_verify($mobile, $code, $template);
        if (!$status) {
            return $sms->error;
        }
        return true;
    }


    /**
     * 生成手机验证码
     */
    public function genExchangeCode()
    {
        $charset = '1234567890';
        $codeLen = 4;
        $code = '';
        $_len = strlen($charset) - 1;
        for ($i = 0; $i < $codeLen; $i++) {
            $code .= $charset[mt_rand(0, $_len)];
        }
        return $code;
    }


    /**
     * 生成普通验证码
     * @param int $fontSize 验证码字号
     * @param bool $useCurve 干扰线
     * @param bool $useNoise 干扰点
     * @param int $imageW 验证码宽度 设置为0为自动计算
     * @param int $imageH 验证码高度 设置为0为自动计算
     * @param int $length 验证码位数
     */
    public function yzm($fontSize = 12, $useCurve = true, $useNoise = true, $imageW = 90, $imageH = 25, $length = 4)
    {
        $this->Verify->fontSize = $fontSize;
        $this->Verify->useCurve = $useCurve;
        $this->Verify->useNoise = $useNoise;
        $this->Verify->imageW = $imageW;
        $this->Verify->imageH = $imageH;
        $this->Verify->length = $length;
        $this->Verify->entry();
    }


    /**
     * 上传图片
     * @param int $size
     * @param array $type
     * @param $rootPath
     * @param $savePath
     * @param array $saveName
     * @return array
     */
    public function upload($type, $rootPath, $savePath, $saveName = array('uniqid', ''), $size = 3145728, $subName = '')
    {
        $upload = new \Think\Upload();// 实例化上传类
        $upload->maxSize = $size;// 设置附件上传大小
        $upload->exts = $type;// 设置附件上传类型
        $upload->rootPath = $rootPath; // 设置附件上传根目录
        $upload->savePath = $savePath; // 设置附件上传（子）目录
        $upload->saveName = $saveName;
        $upload->subName = $subName;
        $upload->autoSub = true;
        $upload->replace = true;
        // 上传文件
        $info = $upload->upload();
        $ret = array();
        if (!$info) {// 上传错误提示错误信息
            $ret['success'] = false;
            $ret['msg'] = $upload->getError();
        } else {// 上传成功
            $ret['success'] = true;
            $ret['info'] = $info;
        };
        //var_dump($ret);
        return $ret;
    }


    /**
     * 上传单张图片
     * @param $size
     * @param $rootPath
     * @param $savePath
     * @param $type
     * @param array $saveName
     * @param string $subName
     * @return array
     */
    public function uploadOne($filename, $size, $rootPath, $savePath, $type, $saveName = array('uniqid', ''), $subName = '')
    {
        $upload = new \Think\Upload();
        $upload->maxSize = $size;
        $upload->rootPath = $rootPath;
        $upload->savePath = $savePath;
        $upload->exts = $type;
        $upload->saveName = $saveName;
        $upload->subName = $subName;
        $upload->replace = true; //存在同名是否覆盖

        // 上传文件
        $info = $upload->uploadOne($_FILES[$filename]);
        $ret = array();
        if (!$info) {// 上传错误提示错误信息
            $ret['success'] = false;
            $ret['msg'] = $upload->getError();
        } else {// 上传成功
            $ret['success'] = true;
            $ret['info'] = $info;
        };
        //var_dump($ret);
        return $ret;
    }


    /**
     * 按json方式输出通信数据
     * @param integer $code 状态码
     * @param string $message 提示信息
     * @param array $data 数据
     * return string
     */
    public static function json($code, $data = array())
    {
        if (!is_numeric($code)) {
            return '';
        }
        $result = array(
            'code' => $code,
            'message' => getMsgByCode($code),
            'data' => $data,
        );
        header('Content-Type:application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Cache-Control");
        header('Access-Control-Allow-Methods: GET, POST, PUT');
        exit(json_encode($result));
    }


    /**
     * 批量上传图片到临时文件
     * @param $picName string 文件名
     * @param $count int 图片至多的数量+1
     * @param $type  array 图片文件支持的类型
     * @return bool|void
     */
    public function upLotPicToTmp($picName, $count, $type)
    {
        if (count($_FILES[$picName]['name']) > 0 && count($_FILES[$picName]['name']) < $count) {
            $rootPath = './Public/uploads/';
            $savePath = 'tmp/';
            $ret = $this->upload($type, $rootPath, $savePath, array('uniqid', ''), 5242880);
            if ($ret['success'] == true && count($ret['info']) == count($_FILES[$picName]['name'])) {
                foreach ($ret['info'] as $key => $value) {
                    $data[] = $ret['info'][$key]['savename'];
                }
                $tmpString = implode(",", $data);
                CommonController::json(1, $tmpString);
            } else {
                CommonController::json(-19);  //图片上传失败，请按规范上传
            }
        } else {
            return false;
        }
    }


    /**
     * 通过Token获取用户ID
     */
    public function getUserIdFromToken()
    {
        $m_usersToken = M('UsersToken');
        $whereToken['token'] = array('eq', I('post.token'));
        //通过Token获取userId
        $userIdArr = $m_usersToken->where($whereToken)->field('u_id')->find();
        $userId = implode("", $userIdArr);
        return $userId;
    }


    /**
     * 获取全国城市三级联动下拉列表
     */
    public function getThreeLevelCityDrop()
    {
        $provinceCode = I('post.provinceCode');
        $cityCode = I('post.cityCode');
        $zhixiashiArr = array("110000", "120000", "310000", "500000");  //直辖市编号的数组
        $shixiaquiArr = array("110100", "120100", "310100", "500100");  //市辖区编号的数组
        $specialArr = array("710000", "810000", "820000"); //台湾/香港/澳门

        $m_city = M('City');
        if (!$provinceCode && !$cityCode) {  //查找全部省份 SELECT * FROM `z_city` WHERE `code` LIKE "%0000";
            $where['code'] = array('like', '%0000');
            $province = $m_city->where($where)->select();
            if ($province) {
                CommonController::json(1, $province);
            } else {
                CommonController::json(-24);  //获取数据失败
            }
        }
        if ($provinceCode && $cityCode) {
            CommonController::json(-8); //传入的字段错误
        }
        if ($provinceCode) {  //接收到省份编号 查城市下拉 SELECT * FROM `z_city` WHERE `code` LIKE '14%00' AND `code` != '140000';
            if (in_array($provinceCode, $zhixiashiArr)) {  //直辖市的搜索规则和普通的不一样(北京、天津、上海、重庆)
                $str1 = (int)$provinceCode + 100;
                $city = $m_city->where('code=' . $provinceCode . ' or code=' . $str1)->select();

                if ($city) {
                    CommonController::json(1, $city);
                } else {
                    CommonController::json(-24);  //获取数据失败
                }
            }
//            elseif(in_array($provinceCode, $specialArr)){ //台湾/香港/澳门
//                $city[0]['city'] = '——';
//                $city[0]['code'] = $provinceCode;
//                CommonController::json(1,$city);
//            }
            else {  //非直辖市
                $str = substr($provinceCode, 0, 2);
                $city = $m_city->where('code like "' . $str . '%00" and code !=' . $provinceCode . ' ')->select();
                if ($city) {
                    CommonController::json(1, $city);
                } else {
                    CommonController::json(-24);  //获取数据失败
                }
            }
        }
        if ($cityCode) {  //接收到城市编号 查地区下拉 SELECT * FROM `z_city` WHERE `code` > 140300 AND `code` < 140400;
            if (in_array($cityCode, $zhixiashiArr)) {  //直辖市的搜索规则和普通的不一样
                $str = substr($cityCode, 0, 2);
                $origin = $m_city->where('code like "' . $str . '%" and code !=' . $cityCode . ' ')->select();
                if ($origin) {
                    CommonController::json(1, $origin);
                } else {
                    CommonController::json(-24);  //获取数据失败
                }
            }
//            elseif(in_array($cityCode, $specialArr)){ //台湾/香港/澳门
//                $city[0]['city'] = '——';
//                $city[0]['code'] = $cityCode;
//                CommonController::json(1,$city);
//            }
            elseif (in_array($cityCode, $shixiaquiArr)) {  //选的是“市辖区” 返回的是“县”
                $origin = $m_city->where('code=110200')->select();
                if ($origin) {
                    CommonController::json(1, $origin);
                } else {
                    CommonController::json(-24);  //获取数据失败
                }
            } else {  //非直辖市
                $str1 = (int)$cityCode + 100;
                $origin = $m_city->where("code>'$cityCode' and code<'$str1'")->select();
                if ($origin) {
                    CommonController::json(1, $origin);
                } else {
                    CommonController::json(-24);  //获取数据失败
                }
            }
        }
    }


    /**
     * 商户中心通过clubId和userId检测所选店铺是否在其名下
     */
    public function checkClubIsBelongUser()
    {
        $userId = $this->getUserIdFromToken(); //通过Token获取userId
        $m_seller = M('Seller');
        $whereUserId['userId'] = array('eq', $userId);
        $whereUserId['status'] = array('in', '1,3');
        //通过userId从seller表获取clubId
        $clubIdArr = $m_seller->where($whereUserId)->field('clubId')->select();
        foreach ($clubIdArr as $key => $value) {
            $clubIdA[] = $value['clubId'];
        }
        if ($clubIdA) {  //有开店记录
            if (in_array(I('post.clubId'), $clubIdA)) {  //检查接收到的clubId是否存在与该用户名下的所有店铺Id中
                return true;
            } else {  //用户所选店铺ID并非在其名下
                return false;
            }
        } else {  //没有开店记录
            CommonController::json(-49);  //该用户尚无开店记录
        }
    }


    /**
     * ? 通过城市下拉获取所拥有下拉琴馆列表的name+id
     */
    public function getOwnClubFromDropCity()
    {
        $userId = $this->getUserIdFromToken(); //通过Token获取userId
        $where['userId'] = array('eq', $userId);
        $where['status'] = 1;
        $m_club = M('Club');
        $m_seller = M('Seller');
        $clubIdArr = $m_seller->where($where)->field('clubId')->select();
        foreach ($clubIdArr as $key => $value) {
            $mapClubId['id'] = implode('', $value);
            $clubNameA = $m_club->where($mapClubId)->field('clubName')->find();
            $clubName = implode("", $clubNameA);
            $data[$key]['clubName'] = $clubName;
            $data[$key]['id'] = implode('', $value);
        }
        return $data;
    }


    /**
     * 获取客户端真实IP地址
     */
    public function getRealIP()
    {
        if (isset($_SERVER)) {
            if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
                $realip = $_SERVER["HTTP_X_FORWARDED_FOR"];
            } else if (isset($_SERVER["HTTP_CLIENT_IP"])) {
                $realip = $_SERVER["HTTP_CLIENT_IP"];
            } else {
                $realip = $_SERVER["REMOTE_ADDR"];
            }
        } else {
            if (getenv("HTTP_X_FORWARDED_FOR")) {
                $realip = getenv("HTTP_X_FORWARDED_FOR");
            } else if (getenv("HTTP_CLIENT_IP")) {
                $realip = getenv("HTTP_CLIENT_IP");
            } else {
                $realip = getenv("REMOTE_ADDR");
            }
        }
        return $realip;
    }


    /**
     * 生成订单号
     * @param $firstMark string 订单号第一位标识字母
     */
    public function makeOrderNum($firstMark)
    {
        $hSeconds = bcmul(substr(date('His'), 0, 2), 3600);
        $mSeconds = bcmul(substr(date('His'), 2, 2), 60);
        $sSeconds = substr(date('His'), 4, 2);
        $seconds = $hSeconds + $mSeconds + $sSeconds;
        $orderSn = $firstMark . substr(date('Ymd'), 2, 6) . $seconds . rand(100001, 999999);   //订单号
        return $orderSn;
    }


    /**
     * 店铺本身不能操作自己的方法
     * (用在：不能预约自己的琴馆,不能评论自己)
     * @param $clubId  int  当前用户所在店铺的ID
     * @param $userId  int  当前登录状态的用户ID
     * @return bool
     */
    public function clubCanNotDoOwn($clubId, $userId)
    {
        $m_seller = M('Seller');
        $whereClubId['clubId'] = array('eq', $clubId);
        //查询所在商户的商户userId
        $sellerUserIdArr = $m_seller->where($whereClubId)->field('userId')->find();
        $sellerUserId = implode('', $sellerUserIdArr);
        if ($userId == $sellerUserId) {  //当前登录的userId等于商户的userId
            return false;
//            CommonController::json(-56); //不能预约自己的琴馆
        } else {
            return true;
        }
    }


    /**
     * 课程店铺本身不能操作自己的方法
     * (用在：购买自己的课程,不能评论自己)
     * @param $courseId  int  当前用户所在课程的店铺的ID
     * @param $userId    int  当前登录状态的用户ID
     * @return bool
     */
    public function courseCanNotDoOwn($courseId, $userId)
    {
        $m_course = M('Course');
        $whereCourseId['id'] = array('eq', $courseId);
        $clubId = $m_course->where($whereCourseId)->field('clubId')->find();
        $m_seller = M('Seller');
        $whereClubId['clubId'] = array('eq', implode("", $clubId));
        $sellerUserIdArr = $m_seller->where($whereClubId)->field('userId')->find();
        $sellerUserId = implode('', $sellerUserIdArr);  //商户的userId
        if ($userId == $sellerUserId) {  //当前登录的userId等于商户的userId
            return false;
//            CommonController::json(-55); //不能购买自己的课程
        } else {
            return true;
        }
    }


    /**
     * 判断所选琴馆是否在该城市下的方法
     * (用在：首页选择琴馆操作)
     * @param $cityCode  int  所选城市编码
     * @param $clubId    int  所选琴馆ID
     * @return bool
     */
    public function checkWhetherClubInCity($cityCode, $clubId)
    {
        $m_club = M("Club");
        //先判断该琴馆存在否
        $w['id'] = array('eq', $clubId);
        $w['z_club.status'] = array('eq', 1);
        $isClub = $m_club->where($w)->field('id')->find();
        if ($isClub) { //琴馆存在
            //再判断该琴馆是否存在该城市下
            $w['city'] = array('eq', $cityCode);
            $exist = $m_club->where($w)->field('z_club.id')->find();
            if ($exist) {  //所选琴馆在该城市下
                return true;
            } else {  //所选琴馆不在该城市下
                //查询出该琴馆所属城市的城市码
                $belongCity = $m_club->join('z_city ON z_city.code=z_club.city')->where('z_club.id=' . $clubId)->field('z_club.city as cityCode,z_city.city as cityName')->find();
                CommonController::json(-74, $belongCity);  //您所选的琴馆不在当前城市下，马上为您跳转到所属城市...
            }
        } else {
            CommonController::json(-94);  //非法操作：该琴馆不存在
        }
    }


    /**
     * 判断所选课程是否在该城市下的方法
     * (用在：首页选择课程操作)
     * @param $cityCode  int  所选城市编码
     * @param $clubId    int  所选琴馆ID
     * @return bool
     */
    public function checkWhetherCourseInCity($cityCode, $courseId)
    {
        $m_course = M("Course");
        //先判断该课程存在否
        $w['z_course.id'] = array('eq', $courseId);
        $w['z_course.status'] = array('eq', 1);
        $isCourse = $m_course->where($w)->field('id')->find();
        if ($isCourse) {  //课程存在
            //再判断该课程所在琴馆是否存在于seller表里
            $clubId = $m_course->where('id=' . $courseId)->field('clubId')->find();
            $m_seller = M("Seller");
            $isExist = $m_seller->where('clubId=' . $clubId['clubId'])->find();
            if ($isExist) { //存在于seller表里
                //再判断该课程是否存在该城市下
                $w['z_club.city'] = array('eq', $cityCode);
                $w['z_club.status'] = array('eq', 1);
                $exist = $m_course->join('z_club ON z_course.clubId=z_club.id')->where($w)->field('z_course.id')->find();
                if ($exist) {  //该城市下存在该课程
                    return true;
                } else {  //所选课程不在该城市下
                    //查询出该课程所属城市的城市码
                    $m_club = M("Club");
                    $belongCity = $m_club->join('z_city ON z_city.code=z_club.city')->where('z_club.id=' . $clubId['clubId'])->field('z_club.city as cityCode,z_city.city as cityName')->find();
                    CommonController::json(-73, $belongCity);  //您所选的课程不在当前城市下，马上为您跳转到所属城市...
                }
            } else {
                CommonController::json(-9);  //非法操作(该课程所属琴馆不存在于seller表里)
            }
        } else {
            CommonController::json(-9);  //非法操作(该课程不存在)
        }
    }


    /**
     * 查出该琴馆下课程价格的最大最小值
     */
    public function findMaxMinCoursePriceInTheClub($clubId)
    {
        $m_course = M('Course');
        $w['clubId'] = array('eq', $clubId);
        $courseList = $m_course->where($w)->group('price')->field('price')->select();
        if ($courseList) {  //有一条或多条课程
            foreach ($courseList as $key => $value) {
                $priceArr[] = $courseList[$key]['price'];
            }
            $minKey = array_search(min($priceArr), $priceArr);
            $maxKey = array_search(max($priceArr), $priceArr);
            $r['minPrice'] = $priceArr[$minKey];
            $r['maxPrice'] = $priceArr[$maxKey];
            return $r;
        } else {  //暂无课程 价格显示0
            $r['minPrice'] = 0;
            $r['maxPrice'] = 0;
            return $r;
        }
    }


    /**
     * 验证用户是否为实名认证会员/古琴认证会员
     */
    public function checkIsAuthUser($userId)
    {
        $m_users = M('Users');
        $isAuth = $m_users->where('id=' . $userId . ' and (isQinAuth=1 or isUserAuth=1)')->field('id')->find();
        if ($isAuth) {
            return true;
        } else {
            CommonController::json(-64);  //实名认证/古琴认证后方可评论
        }
    }


    /**
     * 古琴认证部分获取单条变更认证页面头部信息公共方法
     */
    public function getPerUpdateQinPageCommon($qinId)
    {
        $this->checkPost(array($qinId));
        //判断该琴是否存在
        $whereQin['id'] = array('eq', $qinId);
        $m_qin = M('Qin');
        $existQin = $m_qin->where($whereQin)->find();
        if ($existQin) {  //该琴存在
            $where['z_qin.id'] = array('eq', I('post.qinId'));
            $where['z_qin.status'] = array("eq", 1);
            $qinInfo = $m_qin
                ->join('z_qin_auth ON z_qin_auth.id=z_qin.authId')
                ->join('z_club ON z_club.id=z_qin_auth.fromClubId')
                ->field('z_qin.id as qinId,z_qin.qinName,z_qin.qinSn,z_club.id as clubId,clubName,z_qin_auth.buyingPrice,qinPic')
                ->where($where)
                ->find();
            return $qinInfo;
        } else {
            CommonController::json(-9); //非法操作
        }
    }


    /**
     * 支付宝支付方法
     * @param $orderSn string 订单号
     * @param $orderName string 订单名称
     * @param $payPrice string 付款金额
     * @param $desc string 商品描述
     * @return \提交表单HTML文本
     */
    public function aliPayCommonApi($orderSn, $orderName, $payPrice, $desc)
    {
        require './app/Home/Org/alipayTimelyPHPSDK/alipay.config.php';
        require './app/Home/Org/alipayTimelyPHPSDK/lib/alipay_submit.class.php';

        //商户订单号，商户网站订单系统中唯一订单号，必填
        $out_trade_no = $orderSn;
        //订单名称，必填
        $subject = $orderName;
        //付款金额，必填
        $total_fee = $payPrice;
        //商品描述，可空
        $body = $desc;

        /************************************************************/

        //构造要请求的参数数组，无需改动
        $parameter = array(
            "service" => $alipay_config['service'],
            "partner" => $alipay_config['partner'],
            "seller_id" => $alipay_config['seller_id'],
            "payment_type" => $alipay_config['payment_type'],
            "notify_url" => $alipay_config['notify_url'],
            "return_url" => $alipay_config['return_url'],
            "anti_phishing_key" => $alipay_config['anti_phishing_key'],
            "exter_invoke_ip" => $alipay_config['exter_invoke_ip'],
            "out_trade_no" => $out_trade_no,
            "subject" => $subject,
            "total_fee" => $total_fee,
            "body" => $body,
            "_input_charset" => trim(strtolower($alipay_config['input_charset']))
            //其他业务参数根据在线开发文档，添加参数.文档地址:https://doc.open.alipay.com/doc2/detail.htm?spm=a219a.7629140.0.0.kiX33I&treeId=62&articleId=103740&docType=1
            //如"参数名"=>"参数值"
        );

        //建立请求
        $alipaySubmit = new \AlipaySubmit($alipay_config);
        $html_text = $alipaySubmit->buildRequestForm($parameter, "get", "确认");
        return $html_text;
    }


    /**
     * 银联支付方法
     * @param $orderId string 商户订单号
     * @param $txnTime string 订单发送时间
     * @param $txnAmt string 交易金额
     * @return \提交表单HTML文本
     */
    public function unionPayCommonApi($orderId, $txnTime, $txnAmt)
    {
        header('Content-type:text/html;charset=utf-8');
        require './app/Home/Org/unionPayGateWayPHPSDK/upacp_demo_b2c/sdk/acp_service.php';

        //构造要请求的参数数组，无需改动
        $params = array(
            //以下信息非特殊情况不需要改动
            'version' => '5.0.0',                 //版本号
            'encoding' => 'utf-8',                  //编码方式
            'txnType' => '01',                      //交易类型
            'txnSubType' => '01',                  //交易子类
            'bizType' => '000201',                  //业务类型
            'frontUrl' => \com\unionpay\acp\sdk\SDK_FRONT_NOTIFY_URL,  //前台通知地址
            'backUrl' => \com\unionpay\acp\sdk\SDK_BACK_NOTIFY_URL,      //后台通知地址
            'signMethod' => '01',                  //签名方法
            'channelType' => '07',                  //渠道类型，07-PC，08-手机
            'accessType' => '0',                  //接入类型
            'currencyCode' => '156',              //交易币种，境内商户固定156

            //TODO 以下信息需要填写
//            'merId' => '700000000000001',		//商户代码，请改自己的测试商户号，此处默认取demo演示页面传递的参数
            'merId' => '777290058141549',        //商户代码，请改自己的测试商户号，此处默认取demo演示页面传递的参数
            'orderId' => $orderId,    //商户订单号，8-32位数字字母，不能含“-”或“_”，此处默认取demo演示页面传递的参数，可以自行定制规则
            'txnTime' => $txnTime,    //订单发送时间，格式为YYYYMMDDhhmmss，取北京时间，此处默认取demo演示页面传递的参数
            'txnAmt' => $txnAmt,    //交易金额，单位分，此处默认取demo演示页面传递的参数
// 		'reqReserved' =>'透传信息',        //请求方保留域，透传字段，查询、通知、对账文件中均会原样出现，如有需要请启用并修改自己希望透传的数据

        );

        //建立请求
        \com\unionpay\acp\sdk\AcpService::sign($params);
        $uri = \com\unionpay\acp\sdk\SDK_FRONT_TRANS_URL;
        $html_form = \com\unionpay\acp\sdk\AcpService::createAutoFormHtml($params, $uri);
        return $html_form;
    }


    /**
     * 支付宝支付同步回调公共方法
     * payType:1-开通高级会员; 2-购买课程订单;
     */
    static public function commonReturn_url($character, $priMethod)
    {
        /* *
         * 功能：支付宝服务器异步通知页面
         * 版本：3.3
         * 日期：2012-07-23
         * 说明：
         * 以下代码只是为了方便商户测试而提供的样例代码，商户可以根据自己网站的需要，按照技术文档编写,并非一定要使用该代码。
         * 该代码仅供学习和研究支付宝接口使用，只是提供一个参考。

         *************************页面功能说明*************************
         * 创建该页面文件时，请留心该页面文件中无任何HTML代码及空格。
         * 该页面不能在本机电脑测试，请到服务器上做测试。请确保外部可以访问该页面。
         * 该页面调试工具请使用写文本函数logResult，该函数已被默认关闭，见alipay_notify_class.php中的函数verifyNotify
         * 如果没有收到该页面返回的 success 信息，支付宝会在24小时内按一定的时间策略重发通知
         */
        require './app/Home/Org/alipayTimelyPHPSDK/alipay.config.php';
        require './app/Home/Org/alipayTimelyPHPSDK/lib/alipay_notify.class.php';

        //计算得出通知验证结果
        $alipayNotify = new \AlipayNotify($alipay_config);
        $verify_result = $alipayNotify->verifyReturn();

        if ($verify_result) {  //验证成功
            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            //请在这里加上商户的业务逻辑程序代
            //——请根据您的业务逻辑来编写程序（以下代码仅作参考）——
            //获取支付宝的通知返回参数，可参考技术文档中服务器异步通知参数列表

            //商户订单号
            $out_trade_no = I('get.out_trade_no');

            //支付宝交易号
            $trade_no = I('get.trade_no');

            //交易状态
            $trade_status = I('get.trade_status');

            if (I('get.trade_status') == 'TRADE_FINISHED') {
                //判断该笔订单是否在商户网站中已经做过处理
                //如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
                //请务必判断请求时的total_fee、seller_id与通知时获取的total_fee、seller_id为一致的
                //如果有做过处理，不执行商户的业务程序
                //注意：
                //退款日期超过可退款期限后（如三个月可退款），支付宝系统发送该交易状态通知
                //调试用，写文本函数记录程序运行情况是否正常
                //logResult("这里写入想要调试的代码变量值，或其他运行的结果记录");
            } else if (I('get.trade_status') == 'TRADE_SUCCESS') {
                //判断该笔订单是否在商户网站中已经做过处理
                //如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
                //请务必判断请求时的total_fee、seller_id与通知时获取的total_fee、seller_id为一致的
                //如果有做过处理，不执行商户的业务程序
                //注意：
                //付款完成后，支付宝系统发送该交易状态通知
                //调试用，写文本函数记录程序运行情况是否正常
                //logResult("这里写入想要调试的代码变量值，或其他运行的结果记录");
                switch (strtoupper($character)) {
                    case 'R':  //开通高级会员
                        IndexController::$priMethod($out_trade_no, $trade_no);
                        break;
                    case 'C':  //购买课程订单
                        IndexController::$priMethod($out_trade_no, $trade_no);
                        break;
                }
            }
            return true;
            //——请根据您的业务逻辑来编写程序（以上代码仅作参考）——
        } else {  //验证失败
            return false;
            //调试用，写文本函数记录程序运行情况是否正常
            //logResult("这里写入想要调试的代码变量值，或其他运行的结果记录");
        }
    }


    /**
     * 支付宝支付异步回调公共方法
     */
    static public function commonNotify_url($character, $priMethod)
    {
        /* *
         * 功能：支付宝服务器异步通知页面
         * 版本：3.3
         * 日期：2012-07-23
         * 说明：
         * 以下代码只是为了方便商户测试而提供的样例代码，商户可以根据自己网站的需要，按照技术文档编写,并非一定要使用该代码。
         * 该代码仅供学习和研究支付宝接口使用，只是提供一个参考。

         *************************页面功能说明*************************
         * 创建该页面文件时，请留心该页面文件中无任何HTML代码及空格。
         * 该页面不能在本机电脑测试，请到服务器上做测试。请确保外部可以访问该页面。
         * 该页面调试工具请使用写文本函数logResult，该函数已被默认关闭，见alipay_notify_class.php中的函数verifyNotify
         * 如果没有收到该页面返回的 success 信息，支付宝会在24小时内按一定的时间策略重发通知
         */

        require './app/Home/Org/alipayTimelyPHPSDK/alipay.config.php';
        require './app/Home/Org/alipayTimelyPHPSDK/lib/alipay_notify.class.php';

        //计算得出通知验证结果
        $alipayNotify = new \AlipayNotify($alipay_config);
        $verify_result = $alipayNotify->verifyNotify();

        if ($verify_result) {//验证成功
            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            //请在这里加上商户的业务逻辑程序代
            //——请根据您的业务逻辑来编写程序（以下代码仅作参考）——
            //获取支付宝的通知返回参数，可参考技术文档中服务器异步通知参数列表
            //商户订单号
            $out_trade_no = I('post.out_trade_no');

            //支付宝交易号
            $trade_no = I('post.trade_no');

            //交易状态
            $trade_status = I('post.trade_status');

            if (I('post.trade_status') == 'TRADE_FINISHED') {
                //判断该笔订单是否在商户网站中已经做过处理
                //如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
                //请务必判断请求时的total_fee、seller_id与通知时获取的total_fee、seller_id为一致的
                //如果有做过处理，不执行商户的业务程序
                //注意：
                //退款日期超过可退款期限后（如三个月可退款），支付宝系统发送该交易状态通知
                //调试用，写文本函数记录程序运行情况是否正常
                //logResult("这里写入想要调试的代码变量值，或其他运行的结果记录");
            } else if ($_POST['trade_status'] == 'TRADE_SUCCESS') {
                //判断该笔订单是否在商户网站中已经做过处理
                //如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
                //请务必判断请求时的total_fee、seller_id与通知时获取的total_fee、seller_id为一致的
                //如果有做过处理，不执行商户的业务程序
                //注意：
                //付款完成后，支付宝系统发送该交易状态通知
                //调试用，写文本函数记录程序运行情况是否正常
                //logResult("这里写入想要调试的代码变量值，或其他运行的结果记录");
                switch (strtoupper($character)) {
                    case 'R':  //开通高级会员
                        IndexController::$priMethod($out_trade_no, $trade_no);
                        break;
                    case 'C':  //购买课程订单
                        IndexController::$priMethod($out_trade_no, $trade_no);
                        break;
                }
            }
            return true;
            //——请根据您的业务逻辑来编写程序（以上代码仅作参考）——
        } else {
            return false;
            //调试用，写文本函数记录程序运行情况是否正常
            //logResult("这里写入想要调试的代码变量值，或其他运行的结果记录");
        }
    }


    /**
     * 银联支付同步回调公共方法
     * payType:1-开通高级会员; 2-购买课程订单;
     */
    static public function commonFrontReceive($character, $priMethod)
    {
        header('Content-type:text/html;charset=utf-8');
        require './app/Home/Org/unionPayGateWayPHPSDK/upacp_demo_b2c/sdk/acp_service.php';
//        dump($_POST);

        if (isset($_POST ['signature'])) {
            $va = \com\unionpay\acp\sdk\AcpService::validate($_POST) ? '验签成功' : '验签失败';
            $orderId = $_POST ['orderId']; //其他字段也可用类似方式获取
            $respCode = $_POST ['respCode']; //计算得出通知验证结果  判断respCode=00或A6即可认为交易成功
        } else {
            echo '签名为空';
        }

        if ($respCode === '00' || $respCode === 'A6') {  //验证成功
            //获取支付宝的通知返回参数，可参考技术文档中服务器异步通知参数列表
            //商户订单号
            $out_trade_no = I('post.orderId');
            $trade_no = '(NULL)';

            switch (strtoupper($character)) {
                case 'R':  //开通高级会员
                    IndexController::$priMethod($out_trade_no, $trade_no);
                    break;
                case 'C':  //购买课程订单
                    IndexController::$priMethod($out_trade_no, $trade_no);
                    break;
            }
            return true;
            //——请根据您的业务逻辑来编写程序（以上代码仅作参考）——
        } else {  //验证失败
            return false;
            //调试用，写文本函数记录程序运行情况是否正常
            //logResult("这里写入想要调试的代码变量值，或其他运行的结果记录");
        }
    }


    /**
     * 商户后台判断店铺在审核中/审核未通过不允许操作
     */
    public function checkSellerCannotDo($clubId)
    {
        $m_seller = M('Seller');
        $isNo['clubId'] = array('eq', $clubId);
        $isNomal = $m_seller->where($isNo)->field('status')->find();
        if ($isNomal['status'] === '0') { //锁定
            CommonController::json(-78);  //您的店铺已被锁定
        } elseif ($isNomal['status'] === '2') { //审核中
            CommonController::json(-71);  //您的店铺还没有通过审核
        } elseif ($isNomal['status'] === '3') { //审核未通过
            CommonController::json(-79);  //您的店铺审核未通过
        } else {
            return true;
        }
    }


    /**
     * 商户后台判断店铺在审核中/审核未通过不允许操作(基本资料用这个)
     */
    public function checkSellerCannotDo2($clubId)
    {
        $m_seller = M('Seller');
        $isNo['clubId'] = array('eq', $clubId);
        $isNomal = $m_seller->where($isNo)->field('status')->find();
        if ($isNomal['status'] === '0') { //锁定
            CommonController::json(-78);  //您的店铺已被锁定
        } elseif ($isNomal['status'] === '2') { //审核中
            CommonController::json(-71);  //您的店铺还没有通过审核
        } else {
            return true;
        }
    }


    /**
     * 判断15天更新订单状态(琴馆预约/课程订单)
     * @param $tableName string 表名($m_clubOrder)
     * @param $sta string  where条件 ($sta = 'status=1 and clubId='.I('post.clubId');)
     * @param $field  string  查询字段($field = 'id,createTime,status';)
     * @param $createOrPayTime  string  用来判断时间的数据库字段('createTime')
     */
    public function updateOrderStatusDuringFifteen($tableName, $sta, $field, $createOrPayTime)
    {
        $oldStatus = $tableName
            ->where($sta)
            ->field($field)
            ->select();
        $nowTime = strtotime(date('Y-m-d H:i:s')); //当前时间戳
        foreach ($oldStatus as $key => $value) {
            if (($nowTime - strtotime($oldStatus[$key][$createOrPayTime])) >= 1296000) { //15*24*60*60=1296000秒 订单生成时间>=15天
                //状态：等待到店 -> 状态：交易成功(待评价)
                $saveData['status'] = 2;
                $tableName->where('status=1 and id=' . $oldStatus[$key]['id'])->save($saveData);
            }
        }
    }


    /**
     * 检查articleId是否存在
     * @param $articleId string 古琴文化库文章ID
     * @return bool
     */
    public function checkArticleIdIsExist($articleId)
    {
        $where['id'] = array('eq', $articleId);
        $where['status'] = array('eq', 1);
        $m_cultureArticle = M('CultureArticle');
        $isExist = $m_cultureArticle->where($where)->field('id')->find();
        if ($isExist) {
            return true;
        } else {
            CommonController::json(-75);  //所选内容不存在
        }
    }


    /**
     * 检查commentId是否存在
     * @param $commentId string 古琴文化库文章评论ID
     * @return bool
     */
    public function checkCommentIdIsExist($commentId)
    {
        $where['id'] = array('eq', $commentId);
        $where['status'] = array('eq', 1);
        $m_cultureArticleComment = M('CultureArticleComment');
        $isExist = $m_cultureArticleComment->where($where)->field('id')->find();
        if ($isExist) {
            return true;
        } else {
            CommonController::json(-77);  //所选评论不存在
        }
    }


    /**
     * 判断该认证id是否存在
     * @param $qinAuthId string 认证id
     * @return bool
     */
    public function checkAuthIdIsExist($qinAuthId)
    {
        $userId = $this->getUserIdFromToken();
        $m_qinAuth = M('QinAuth');
        $w['id'] = array('eq', $qinAuthId);
        $w['userId'] = array('eq', $userId);
        $isExist = $m_qinAuth->where($w)->field('id,status')->find();
        if ($isExist) {
            return $isExist;
        } else {
            CommonController::json(-81); //所选古琴认证信息不存在
        }
    }


    /**
     * 微信登录用到的方法
     * @param $url
     * @param null $data
     * @return mixed
     */
    public function https_request($url, $data = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }


    /**
     * 检测该用户是否已经申请认证过该琴
     * @param $qinId
     * @return bool
     */
    public function checkUserIsAuthThisQin($qinId)
    {
        $userId = $this->getUserIdFromToken();
        $check['userId'] = array('eq', $userId);
        $check['qinId'] = array('eq', $qinId);
        $m_qinAuth = M('QinAuth');
        $checkId = $m_qinAuth->where($check)->field('id')->find();
        if ($checkId) { //已申请过认证 不让认证了
            CommonController::json(-83); //您已对该古琴申请认证,请前往用户中心后台进行操作
        } else {
            return true;
        }
    }

    //邮箱验证
    public static function checkEmail($email)
    {
        //799781269@qq.com
        $patten = '/^([a-zA-Z0-9]+)\@([a-z]+)\.([a-z]{2,3})$/';
        if (preg_match($patten, $email)) {
            return true;
        } else {
            CommonController::json(-92);//邮箱格式错误
            return false;
        }
    }


}