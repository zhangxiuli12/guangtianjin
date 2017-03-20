<?php
/**
 * Created by PhpStorm.
 * User: sleep
 * Date: 16/12/26
 * Time: 下午6:26
 */
namespace Home\Controller;

use Think\Controller;

class BackController extends Controller
{
    public function _initialize()
    {
        $cmd = I('post.cmd');
        if (!method_exists($this, $cmd)) {
            CommonController::json(-1);
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

    /**
     * 判断是否需要token
     * @param $cmd
     * @return bool
     */
    private function needToken($cmd)
    {
        $noNeedTokenCmd = array(
            'login'
        );
        if (in_array($cmd, $noNeedTokenCmd)) {
            return false;
        } else {
            if (S(I('post.token')) === false) {
                CommonController::json(-6);  //Token无效
                return false;
            } else {
                return true;
            }
        }
    }

    /**
     * 检查数据是否为空
     * @param $postArray
     * @return bool
     */
    private function checkPost($postArray)
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
     * 获取用户列表
     */
    private function userList()
    {
        $this->checkPost(array(I('post.page'), I('post.perpage')));
        $page = I('post.page');
        $perPage = I('post.perpage') + 0;
        $userinfo = M('userinfo');
        $data['prolist'] = $userinfo->limit(($page - 1) * $perPage, $perPage)->field('u_id, u_nickname,u_username,u_phone,u_lock')->select();
        $data['count'] = $userinfo->count('u_id');
        $data['totalpage'] = ceil(($data['count'] + 0) / $perPage);
        CommonController::json(1, $data);
    }

    /**
     * 获取用户详细信息
     */
    /**
     * private function userinfo()
     * {
     * $this->checkPost(array(I('post.u_id')));
     * $id = I('post.u_id');
     *
     * $userinfo = M('userinfo');
     * $where['u_id'] = array('eq', $id);
     * $isExist = $userinfo->where($where)->field('u_id')->find();
     * if ($isExist) {//如果存在的话
     * $data = $userinfo->where($where)->field('weixin_unionid,qq_openid,u_origin,u_reg_time,u_login_num')->find();
     * CommonController::json(1, $data);
     * } else {
     * CommonController::json(-9);  //非法操作
     * }
     * }
     **/
    /**
     * 用户管理-开启/锁定用户
     */
    public function userIsLock()
    {
        $this->checkPost(array(I('post.u_id')));
        $userinfo = M('Userinfo');
        // 判断该id是否存在
        $where['u_id'] = array('eq', I('post.u_id'));
        $isExist = $userinfo->where($where)->field('u_id,u_lock')->find();
        if ($isExist) { //存在记录
            if ($isExist['u_lock'] == 1) {  //正常
                $data['u_lock'] = 0;
            } else { //锁定
                $data['u_lock'] = 1;
            }
            $s = $userinfo->where($where)->save($data);
            if ($s) {
                CommonController::json(1);
            } else {
                CommonController::json(-54);  //操作失败
            }
        } else {
            CommonController::json(-9);  //非法操作
        }
    }

    /**
     * 获取用户扩展资料
     */
    private function extUserInfo()
    {

    }

    /**
     * 生成缩略图
     */
    private function thumb($pic, $icon)
    {
        $image = new \Think\Image();
        $image->open($pic);// 按照原图的比例生成一个最大为50*50
        $image->thumb(50, 50)->save($icon);
    }

    /**
     * 新建分类
     */
    public function createCate()
    {
        $this->checkPost(array(I('post.c_name')));
        $cate = D('cate');
        $data = $cate->create();
        $user = $cate->where(array('c_name' => I('post.c_name')))->find();
        if ($user) {
            CommonController::json(-22);
        } else {
            if ($_FILES['c_icon']['name'] != "") {
                $size = 5242880;
                $rootPath = './Public/uploads/cate/';
                $savePath = 'catePic/';
                $type = array('jpg', 'png', 'jpeg');
                $ret = $this->upload($type, $rootPath, $savePath, array('uniqid', ''), $size);
                if ($ret['success'] == true) {
                    foreach ($ret['info'] as $key => $value) {
                        $images[] = substr($rootPath, 1) . $savePath . $ret['info'][$key]['savename'];
                        $data['c_icon'] = implode(",", $images);
                    }
                    $this->thumb('.' . $data['c_icon'], '.' . $data['c_icon']);
                    if ($cate->create($data)) {
                        //保存数据库
                        if ($cate->add()) {
                            CommonController::json(1);
                        } else {
                            CommonController::json(-29);  //新建失败
                        }
                    } else {
                        CommonController::json($cate->getError());
                    }
                }
            } else {
                CommonController::json(-19);  //图片上传失败，请按规范上传
            }
        }
    }

    /**
     *获取分类列表
     */
    private function getCate()
    {

        $data['prolist'] = $this->cateTree();
        CommonController::json(1, $data);
    }

    public function cateTree()
    {
        $userinfo = M('cate');
        $data = $userinfo->select();
        return $this->restsort($data);
    }

    private function restsort($data, $parentid = 0, $level = 0)
    {
        static $arr = [];
        foreach ($data as $k => $v) {
            if ($v['pid'] == $parentid) {
                $v['level'] = $level;
                $arr[] = $v;
                $this->restsort($data, $v['c_id'], $level + 1);
            }
        }
        return $arr;

    }


    /**
     * 上传单张图片
     */
    private function uploadOne($filename, $size, $rootPath, $savePath, $type, $saveName = array('uniqid', ''), $subName = '')
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
        return $ret;
    }

    /**
     *获取分类详细信息
     */
    private function cateInfo()
    {
        $this->checkPost(array(I('post.c_id')));
        $cate = M('cate');
        $c_id = I('post.c_id');
        $where['c_id'] = array('eq', $c_id);
        $isExist = $cate->where($where)->field('c_id,c_name,c_icon')->find();
        if ($isExist) {
            CommonController::json(1, $isExist);
        } else {
            CommonController::json(-9);
        }
    }

    /**
     * 修改分类
     */
    private function editCate()
    {

        $cate = D('cate');
        $c_id = I('post.c_id');
        $where['c_id'] = array('eq', $c_id);
        $user = $cate->where(array('c_name' => I('post.c_name')))->find();
        $isExist = $cate->where($where)->field('c_id')->find();
        if ($user) {//不能重复
            CommonController::json(-22);
        } else {
            if ($isExist) {//存在的话
                $data = $cate->create();
                if ($_FILES['c_icon']['name'] != '') {
                    $size = 5242880;
                    $rootPath = './Public/uploads/cate/';
                    $savePath = 'catePic/';
                    $type = array('jpg', 'png', 'jpeg');
                    $ret = $this->upload($type, $rootPath, $savePath, $c_id, $size);
                    if ($ret['success'] == true) {
                        foreach ($ret['info'] as $key => $value) {
                            $images[] = substr($rootPath, 1) . $savePath . $ret['info'][$key]['savename'];
                            $data['c_icon'] = implode(",", $images);
                        }
                        $this->thumb('.' . $data['c_icon'], '.' . $data['c_icon']);

                    } else {
                        CommonController::json(-19);  //图片上传失败，请按规范上传
                    }
                }

                //保存数据库
                $addId = $cate->save($data);
                if ($addId !== false) {
                    CommonController::json(1);
                    exit();
                } else {
                    CommonController::json(-30);  //修改失败
                    exit();
                }
            } else {
                CommonController::json(-9);//非法操作
            }
        }
    }

    /**
     * 删除分类
     */
    public function deleteCate()
    {
        $this->checkPost(array(I('post.c_id')));
        $c_id = I('post.c_id');
        $cate = M('cate');
        $where['c_id'] = $c_id;
        $isExist = $cate->where($where)->field('c_id,c_icon')->find();
        if ($isExist) {
            $data = $cate->select();
            $this->delCate($isExist['c_id'], $data);
            if ($cate->delete($isExist['c_id'])) {
                //删除分类图片
                $res = unlink('.' . $isExist['c_icon']);
                $artcile = M('article');//删除分类下的文章
                $artcile->where($where)->delete();
                CommonController::json(1);

            } else {
                CommonController::json(-53);//删除失败
            }
        } else {
            CommonController::json(-9);//非法操作
        }

    }

    /**
     * 无限极分类删除栏目
     * @param $c_id
     * @param $data
     * @return bool
     */

    public function delCate($c_id, $data)
    {
        $cate = M('cate');
        foreach ($data as $k => $v) {
            if ($v['pid'] == $c_id) {
                $where['c_id'] = $v['c_id'];
                $icon = $cate->where($where)->field('c_icon')->find();
                unlink('.' . $icon['c_icon']);//删除子级分类下面的图片
                $cate->delete($v['c_id']);//删除子级分类
                $this->delCate($v['c_id'], $data);
                $artcile = M('article');//删除分类下的文章
                $artcile->where("c_id=" . $v['c_id'])->delete();
            }
        }

    }

    /**
     * 推荐分类
     */
    public function recCate()
    {
        $this->checkPost(array(I('post.c_id')));
        $where['c_id'] = array('eq', I('post.c_id'));
        $cate = M('cate');
        $isExist = $cate->where($where)->field('c_id,c_rec')->find();
        if ($isExist) { //存在记录
            if ($isExist['c_rec'] == 1) {  //正常
                $data['c_rec'] = 0;
            } else { //推荐
                $data['c_rec'] = 1;
            }
            $s = $cate->where($where)->save($data);
            if ($s) {
                CommonController::json(1);
            } else {
                CommonController::json(-54);  //操作失败
            }
        } else {
            CommonController::json(-9);  //非法操作
        }
    }

    /**
     * 隐藏分类
     */
    public function hideCate()
    {
        $this->checkPost(array(I('post.c_id')));
        $where['c_id'] = array('eq', I('post.c_id'));
        $cate = M('cate');
        $isExist = $cate->where($where)->field('c_id,c_hide')->find();
        if ($isExist) { //存在记录
            if ($isExist['c_hide'] == 1) {  //正常
                $data['c_hide'] = 0;
            } else { //推荐
                $data['c_hide'] = 1;
            }
            $s = $cate->where($where)->save($data);
            if ($s) {
                CommonController::json(1);
            } else {
                CommonController::json(-54);  //操作失败
            }
        } else {
            CommonController::json(-9);  //非法操作
        }
    }

    /**
     * 添加文章
     */
    private function addArc()
    {
        $this->checkPost(array(I('post.a_title'), I('post.a_author'), I('post.a_content'), I('post.c_id')));
        $data['a_title'] = I('post.a_title');
        $data['a_author'] = I('post.a_author');
        $data['a_content'] = I('post.a_content');
        $data['c_id'] = I('post.c_id');
        $article = M('article');
        if ($_FILES['a_pic']['name'] != "") {
            $size = 5242880;
            $rootPath = './Public/uploads/art/';
            $savePath = 'pic/';
            $type = array('jpg', 'png', 'jpeg');

            $ret = $this->upload($type, $rootPath, $savePath, $_FILES['a_pic']['name'], $size);
            if ($ret['success'] == true) {

                foreach ($ret['info'] as $key => $value) {
                    $images[] = substr($rootPath, 1) . $savePath . $ret['info'][$key]['savename'];
                    $data['a_pic'] = implode(",", $images);
                }
            } else {
                CommonController::json(-19);
            }

        }
        //保存数据库
        $addId = $article->add($data);
        if ($addId > 0) {
            CommonController::json(1);
            exit();
        } else {
            CommonController::json(-29);  //新建失败
            exit();
        }

    }

    /**
     * 编辑文章
     */
    private function editArt()
    {
        $this->checkPost(array(I('post.a_id')));
        $article = M('article');
        $where['a_id'] = I('post.a_id');
        $isExist = $article->where($where)->field('a_id')->find();//判断id是否存在
        if ($isExist) {
            $data = $article->create();
            if ($_FILES['a_pic']['name'] != "") {//如果更改了图片的话
                $size = 5242880;
                $rootPath = './Public/uploads/art/';
                $savePath = 'pic/';
                $type = array('jpg', 'png', 'jpeg');
                $ret = $this->upload($type, $rootPath, $savePath, $_FILES['a_pic']['name'], $size);
                if ($ret['success'] == true) {
                    foreach ($ret['info'] as $key => $value) {
                        $images[] = substr($rootPath, 1) . $savePath . $ret['info'][$key]['savename'];
                        $data['a_pic'] = implode(",", $images);
                    }
                } else {
                    CommonController::json(-19);//图片上传失败，请按规范上传

                }
            }
        } else {
            CommonController::json(-9);//非法操作
            exit();
        }
        //保存数据库
        $addId = $article->save($data);
        if ($addId > 0) {
            CommonController::json(1);

        } else {
            CommonController::json(-29);  //新建失败

        }
    }

    /**
     * 获取文章列表
     */
    private function artList()
    {
        $this->checkPost(I('post.perpage'), I('post.page'), I('post.c_id'));
        $page = I('post.page') + 0;
        $perPage = I('post.perpage') + 0;
        $c_id = I('post.c_id');
        $art = M('article');
        if ($c_id != 0) {
            $where['c_id'] = $_POST['c_id'];
            $isExist = $art->where($where)->select();//判断id是否存在
            if ($isExist) {
                $data['prolist'] = $art->where($where)->limit(($page - 1) * $perPage, $perPage)->select();
                $data['count'] = $art->where($where)->count('a_id');
                $data['count'] = $art->where($where)->count('a_id');
                $data['totalpage'] = ceil(($isExist['count'] + 0) / $perPage);
                CommonController::json(1, $data);
            } else {
                CommonController::json(-10);//该分类下无文章
            }
        } else {

            $data['prolist'] = $art->limit(($page - 1) * $perPage, $perPage)->select();
            $data['count'] = $art->count('a_id');
            $data['totalpage'] = ceil(($data['count'] + 0) / $perPage);
            CommonController::json(1, $data);
        }

    }

    /**
     * 获取单个文章信息
     */
    private function getOneArt()
    {
        $this->checkPost(array(I('post.a_id')));
        $article = M('article');
        $a_id = I('post.a_id');
        $where['a_id'] = $a_id;
        $isExist = $article->where($where)->field('a_time', true)->find();//判断id是否存在
        if ($isExist) {
            CommonController::json(1, $isExist);
        } else {
            CommonController::json(-9);
        }
    }

    /**
     * 获取所有分类不需要传入任何条件
     */
    private function allCate()
    {
        $data['prolist'] = $this->cateTree();
        CommonController::json(1, $data);
    }

    /**
     * 删除文章
     */
    private function deleteArt()
    {
        $this->checkPost(array(I('post.a_id')));
        $article = M('article');
        $where['a_id'] = I('post.a_id');
        $isExist = $article->where($where)->field('a_id')->find();//判断id是否存在
        if ($isExist) {
            $result = $article->delete($where['a_id']);
            if ($result) {
                CommonController::json(1);//删除成功
            } else {
                CommonController::json(-53);//删除失败
            }
        }
    }

    /**
     * 更改文章上线和下线状态
     */
    private function changeArtLock()
    {
        $this->checkPost(array(I('post.a_id')));
        $article = M('article');
        $where['a_id'] = I('post.a_id');
        $isExist = $article->where($where)->field('a_id,a_lock')->find();//判断id是否存在
        if ($isExist) { //存在记录
            if ($isExist['a_lock'] == 1) {  //正常
                $data['a_lock'] = 0;
            } else { //推荐
                $data['a_lock'] = 1;
            }
            $result = $article->where($where)->save($data);//更新
            if ($result) {//如果更新成功的话
                CommonController::json(1);
            } else {
                CommonController::json(-54);  //操作失败
            }
        } else {
            CommonController::json(-9);  //非法操作
        }
    }

    /**
     * 新建活动
     */
    private function createAct()
    {
        $this->checkPost(array(I('post.ac_title'), I('post.ac_source'), I('post.ac_startime'), I('post.ac_endtime'), I('post.ac_content'), I('post.ac_address'), I('post.ac_mobile')));
        $activity = M('activity');
        $activity->create();
        if ($activity->add()) {
            CommonController::json(1);//新建活动成功
        } else {
            CommonController::json(-29);//新建活动失败
        }

    }


    /**
     * 编辑活动
     */
    private function editAct()
    {
        $this->checkPost(array(I('post.ac_id')));

        $activity = M('activity');
        $where['ac_id'] = I('post.ac_id');
        $isExist = $activity->where($where)->field('ac_id')->find();//判断id是否存在
        if ($isExist) {
            $data = $activity->create();
            if ($activity->save($data)) {
                CommonController::json(1);//修改活动成功
            } else {
                CommonController::json(-25);//修改活动失败
            }
        }
    }

    /**
     * 删除活动
     */
    private function deleteAct()
    {
        $this->checkPost(array(I('post.ac_id')));
        $activity = M('activity');
        $where['ac_id'] = I('post.ac_id');
        $isExist = $activity->where($where)->field('ac_id')->find();//判断id是否存在
        if ($isExist) {
            if ($activity->delete($where['ac_id'])) {
                CommonController::json(1);//删除活动成功
            } else {
                CommonController::json(-53);//删除活动失败
            }
        }
    }

    /**
     * 更改活动发布和关闭状态
     */
    private function changeAcLock()
    {
        $this->checkPost(array(I('post.ac_id')));
        $activity = M('activity');
        $where['ac_id'] = I('post.ac_id');
        $isExist = $activity->where($where)->field('ac_id,ac_lock')->find();//判断id是否存在
        if ($isExist) { //存在记录
            if ($isExist['ac_lock'] == 1) {  //正常
                $data['ac_lock'] = 0;
            } else { //推荐
                $data['ac_lock'] = 1;
            }
            $result = $activity->where($where)->save($data);//更新
            if ($result) {//如果更新成功的话
                CommonController::json(1);
            } else {
                CommonController::json(-54);  //操作失败
            }
        } else {
            CommonController::json(-9);  //非法操作
        }
        /*
        $now = time();
        $d = strtotime('2017-3-15');
        if(date('d',$now) == date('d',$d))
        {
            echo '是';
        }
        else
        {
            echo 11111;
        }
        */

    }


    /**
     * 获取活动列表
     */
    private function actList()
    {
        $this->checkPost(array(I('post.page'), I('post.perpage')));
        $page = I('post.page');
        $perPage = I('post.perpage') + 0;
        $act = M('activity');
        $data['prolist'] = $act->limit(($page - 1) * $perPage, $perPage)->select();
        $data['count'] = $act->count('ac_id');
        $data['totalpage'] = ceil(($data['count'] + 0) / $perPage);
        CommonController::json(1, $data);
    }

    /**
     * 查询app日下载量
     */
    private function appDownByDay()
    {
        $this->checkPost(array(I('post.d_date')));
        $where['d_date'] = I('post.d_date');
        $download = M('download');
        if ($data = $download->where($where)->field('d_day')->find()) {

            CommonController::json(1, $data);//查询成功，返回日下载量
        } else {

            CommonController::json(-41);//查询失败
        }

    }

    /**
     * 查询app下载总量
     */
    private function appDownCount()
    {
        $download = M('download');
        if ($data = $download->field('d_count')->order('d_id desc')->limit(0, 1)->find()) {
            CommonController::json(1, $data);//查询成功
        } else {
            CommonController::json(-41);//查询失败
        }

    }

    /**
     * 查询月下载量
     */
    private function appDownMonth()
    {
        $this->checkPost(array(I('post.month')));
        $month = I('post.month');
        $where['d_month_date'] = $month;//传入的月份和数据库里面的月份一样
        $download = M('download');
        if ($data = $download->field("d_month")->order('d_id desc')->where($where)->limit(0, 1)->find()) {
            CommonController::json(1, $data);//查询成功
        } else {
            CommonController::json(-41);//查询失败
        }
    }

    /**
     * 查询周下载量
     */
    private function appDownWeek()
    {
        $this->checkPost(array(I('post.d_week')));

        $week = I('post.d_week');
        $where['d_week_date'] = $week;//传入的周和数据库里面的周一样
        $download = M('download');
        $data = $download->field("d_week")->order('d_id desc')->where($where)->limit(0, 1)->find();
        if ($data = $download->field("d_week")->order('d_id desc')->where($where)->limit(0, 1)->find()) {
            CommonController::json(1, $data);//查询成功
        } else {
            CommonController::json(-41);//查询失败
        }
    }

    /**
     * 查询日注册用户数
     */
    private function regByDay()
    {
        $this->checkPost(array(I('post.r_date')));
        $where['r_date'] = I('post.r_date');
        $regnum = M('regnum');
        if ($data = $regnum->where($where)->field('r_day')->find()) {
            CommonController::json(1, $data);//查询成功，返回日下载量
        } else {
            CommonController::json(-41);//查询失败
        }
    }

    /**
     * 查询注册总用户数
     */
    private function regCount()
    {
        $regnum = M('regnum');
        if ($data = $regnum->order('r_id desc ')->field('r_count')->limit(1)->find()) {
            CommonController::json(1, $data);//查询成功，返回总注册数
        } else {
            CommonController::json(-41);//查询失败
        }
    }

    /**
     * 查询月注册用户数
     */
    private function regByMonth()
    {
        $this->checkPost(array(I('post.r_month_date')));
        $where['r_month_date'] = I('post.r_month_date');
        $regnum = M('regnum');
        if ($data = $regnum->where($where)->field('r_month')->limit(1)->find()) {
            CommonController::json(1, $data);//查询成功，返回总注册数
        } else {
            CommonController::json(-41);//查询失败
        }
    }

    /**
     * 查询周注册数
     */
    private function regByWeek()
    {
        $this->checkPost(array(I('post.r_week_date')));
        $week = I('post.r_week_date');
        $where['r_week_date'] = $week;//传入的周和数据库里面的周一样
        $download = M('regnum');
        if ($data = $download->field("r_week")->order('r_id desc')->where($where)->limit(0, 1)->find()) {
            CommonController::json(1, $data);//查询成功
        } else {
            CommonController::json(-41);//查询失败
        }
    }

    /**
     * 查询总点击量
     */
    private function clickCount()
    {

    }

    /**
     * 查询总转发量
     */
    private function zhuanFa()
    {

    }

    /**
     * 更改系统设置 关于我们，SEO设置
     */
    private function changeSystem()
    {
        $system = M('system');
        $data = $system->create();

        $data['s_id'] = 1;
        //保存数据库
        $updateId = $system->save($data);
        if ($updateId > 0) {
            CommonController::json(1);
            exit();
        } else {
            CommonController::json(-25);  //修改失败
            exit();
        }
    }

    /**
     *更改网站logo
     */
    private function changeSystemlogo()
    {
        if ($_FILES['s_logo']['name'] != "") {
            $size = 5242880;
            $rootPath = './Public/uploads/logo/';
            $savePath = 'logo/';
            $type = array('jpg', 'png', 'jpeg');
            $ret = $this->upload($type, $rootPath, $savePath, 'logo', $size);
            if ($ret['success'] == true) {
                foreach ($ret['info'] as $key => $value) {
                    $images[] = substr($rootPath, 1) . $savePath . $ret['info'][$key]['savename'];
                    $data['s_logo'] = implode(",", $images);
                }
                $system = M('system');
                $result = $system->field('s_logo')->find(1);
                if ($result['s_logo'] == $data['s_logo']) {//logo名字和数据库里面的名字一样
                    CommonController::json(1);//更新成功
                } else {//如果logo的名字和数据库里面的名字不一样的话
                    $data['s_id'] = 1;
                    $system->create($data);
                    if ($system->save($data)) {
                        CommonController::json(1);//更新成功
                    } else {
                        CommonController::json(-19);//图片上传失败，请按规范上传
                    }
                }
            }
        }
    }

    /**
     * 更该网站文章的水印
     */
    private function changeWater()
    {
        if ($_FILES['s_watermark']['name'] != "") {
            $size = 5242880;
            $rootPath = './Public/uploads/watermark/';
            $savePath = 'watermark/';
            $type = array('jpg', 'png', 'jpeg');
            $ret = $this->upload($type, $rootPath, $savePath, 'logo', $size);
            if ($ret['success'] == true) {
                foreach ($ret['info'] as $key => $value) {
                    $images[] = substr($rootPath, 1) . $savePath . $ret['info'][$key]['savename'];
                    $data['s_watermark'] = implode(",", $images);
                }
                $system = M('system');
                $result = $system->field('s_watermark')->find(1);
                if ($result['s_watermark'] == $data['s_watermark']) {//水印名字和数据库里面的名字一样
                    CommonController::json(1);//更新成功
                } else {//如果水印的名字和数据库里面的名字不一样的话
                    $data['s_id'] = 1;
                    $system->create($data);
                    if ($system->save($data)) {
                        CommonController::json(1);//更新成功
                    } else {
                        CommonController::json(-19);//图片上传失败，请按规范上传
                    }
                }
            } else {
                CommonController::json(-19);//图片上传失败，请按规范上传
            }
        }
    }

    /**
     *  上传多张图片
     * @param $type
     * @param $rootPath
     * @param $savePath
     * @param array $saveName
     * @param int $size
     * @param string $subName
     * @return array
     */
    private function upload($type, $rootPath, $savePath, $saveName = array('uniqid', ''), $size = 3145728, $subName = '')
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

    public function index()
    {

    }


    /**
     * 管理员登录
     */
    public function login()
    {
        $this->checkPost(array(I('post.username'), I('post.password')));
        $username = I('post.username');
        $pwd = I('post.password');
        $m_admin = M('Admin');
        $where['username'] = array('eq', $username);
        $where['password'] = array('eq', md5($pwd));
        if ($data = $m_admin->where($where)->find()) {
            $token = md5($data['id'] . $username . $pwd . time());
            $adminInfo['token'] = $token;
            $adminInfo['admin'] = $data['username'];
            S($token, $data['id'], 60 * 60 * 100);    //设置缓存有效期
            CommonController::json(1, $adminInfo);
        } else {
            CommonController::json(-2);
        }
    }


    /**
     * 管理员登出
     */
    public function logout()
    {
        $token = I('post.token');
        S($token, null);
        CommonController::json(1);
    }

    /**
     * 显示爆料邮箱
     */
    public function getBroke()
    {
        $broke=M('broke');
        $email=$broke->field('email')->select();
        CommonController::json(1,$email);

    }

    /**
     * 爆料修改
     */

    public function broke()
    {
        $Broke = D('Broke');
        $data = array(
            'email' => I('post.email'),
            'id' => 0
        );
        $this->checkPost(array(I('post.email')));
        CommonController::checkEmail(I('post.email'));

        if ($Broke->where(array('id' => 0))->save($data) !== false) {
            CommonController::json(1);
        } else {
            CommonController::json(-23);
        }
    }


}