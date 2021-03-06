<?php

namespace App\Http\Controllers\Index;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\WxUserModel;
class IndexController extends Controller
{
    public function index()
    {
        $code = $_GET['code'];
        $data = $this->getAccessToken($code);

        // 判断用户是否已存在
        $openid = $data['openid'];
        $u = WxUserModel::where(['openid'=>$openid])->first();
        if ($u){        // 用户已存在
            $user_info = $u->toArray();
        }else{
            $user_info = $this->getUserInfo($data['access_token'],$data['openid']);
            // 入库用户信息
            WxUserModel::insertGetId($user_info);
        }

        $data = [
            'u' => $user_info
        ];
        return view('index.index',$data);
    }

    /**
     * 根据code获取access_token
     */
    protected function getAccessToken($code)
    {
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.env('WX_APPID').'&secret='.env('WX_APPSECRET').'&code='.$code.'&grant_type=authorization_code';
        $json_data = file_get_contents($url);
        $data = json_decode($json_data,true);

        return $data;       // 返回access_token信息
    }

    /**
     * 获取用户基本信息
     */
    protected function getUserInfo($access_token,$openid)
    {
        $url = 'https://api.weixin.qq.com/sns/userinfo?access_token='.$access_token.'&openid='.$openid.'&lang=zh_CN';
        $json_data = file_get_contents($url);
        $data = json_decode($json_data,true);
//        echo "<pre>";print_r($user_info);echo "</pre>";
        if (isset($data['errcode'])){
            // TODO 错误处理
            die("出错了   40001");     // 40001   表示获取用户信息失败
        }
        return $data;       // 返回用户信息

    }
}
