<?php
return array(
	//'配置项'=>'配置值'
    'DB_TYPE' => 'mysql',     // 数据库类型
    'DB_HOST' => 'localhost', // 服务器地址
    'DB_NAME' => 'guangtianjin',          // 数据库名
    'DB_USER' => 'root',      // 用户名
    'DB_PWD' => 'root',          // 密码
    'DB_PORT' => '3306',        // 端口
    'DB_PREFIX' => 'g_',    // 数据库表前缀
    /*其他配置*/
    'URL_MODEL' => '2', //URL模式
    'SESSION_AUTO_START' => true, //是否开启session

    'SHOW_ERROR_MSG' => true,    // 显示错误信息
    'SHOW_PAGE_TRACE' => false,
//    'MODULE_ALLOW_LIST' => array('Home'), //允许访问的模块
    'MODULE_DENY_LIST' => array('Common', 'Runtime'), // 禁止访问的模块列表
    'DEFAULT_MODULE' => 'Home',  // 默认模块
    'URL_MODULE_MAP' => array('Admin' => 'Admin'),
    'COOKIE_EXPIRE'         =>  3600,       // Cookie有效期
    'COOKIE_DOMAIN'         =>  '127.0.0.1:8080',      // Cookie有效域名
    'COOKIE_PATH'           =>  '/',     // Cookie路径
    'COOKIE_SECURE'         =>  false,   // Cookie安全传输
    'COOKIE_HTTPONLY'       =>  '',      // Cookie httponly设置
    // qq登录成功后跳转的页面
//    'URL_WEB_COMMON' =>    'http://192.168.3.22',      //测试
//    'SHOW_PAGE_TRACE'       =>true,



);