<?php

namespace App\Http\Controllers\Wechat;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\WxUserModel;
use Illuminate\Support\Facades\Redis;

use GuzzleHttp\Client;

class WechatController extends Controller
{

    protected $access_token;

    public function __construct()
    {
        // 获取access_token
        $this->access_token = $this->getAccessToken();

    }

    public function test()
    {
        echo $this->access_token;
    }

    /**
     * 获取access_token
     */
    protected function getAccessToken()
    {
        $key = 'wx_access_token';

        $access_token = Redis::get($key);
        if ($access_token){
            return $access_token;
        }

        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.env('WX_APPID').'&secret='.env('WX_APPSECRET');
        $data_json = file_get_contents($url);
        $arr = json_decode($data_json,true);

        Redis::set($key,$arr['access_token']);
        Redis::expire($key,3600);       //过期时间
        return $arr['access_token'];
    }

    /**
     * 处理接入请求
     */
    public function checkSignature()
	{
		$token = '981118';
	    $signature = $_GET["signature"];
	    $timestamp = $_GET["timestamp"];
	    $nonce = $_GET["nonce"];
		$echostr = $_GET['echostr'];

	    $tmpArr = array($token, $timestamp, $nonce);
	    sort($tmpArr, SORT_STRING);
	    $tmpStr = implode( $tmpArr );
	    $tmpStr = sha1( $tmpStr );

	    if( $tmpStr == $signature ){
	        echo $echostr;
	    }else{
	        die('Not OK!');
	    }
	}


    /**
     * 获取用户基本信息
     */
    public function getUserInfo($access_token,$openid)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token='.$access_token.'&openid='.$openid.'&lang=zh_CN';
        // 发送网络请求
        $json_str = file_get_contents($url);
        $log_file = 'wx_user.log';
        file_put_contents($log_file,$json_str,FILE_APPEND);
    }

    /**
     * 接收微信推送事件
     */
	public function receiv()
    {
        $log_file = "wx.log";
        // 将接受的数据记录到日志文件中
        $xml_str = file_get_contents('php://input');
        $data = date('Y-m-d H:i:s') . " >>>>>> \n" . $xml_str . "\n\n";
        file_put_contents($log_file,$data,FILE_APPEND);     //追加写入

        // 处理xml数据
        $xml_obj = simplexml_load_string($xml_str);

        $event = $xml_obj->Event;       //获取事件类型
        if ($event=='subscribe'){
            $openid = $xml_obj->FromUserName;       // 获取用户的openID
            //判断用户是不是已存在
            $u = WxUserModel::where(['openid' => $openid])->first();
            if ($u){
                //TODO  How old are you ?
                $msg = '怎么又是你 ?';
                $xml = '<xml>
                    <ToUserName><![CDATA['.$openid.']]></ToUserName>
                    <FromUserName><![CDATA['.$xml_obj->ToUserName.']]></FromUserName>
                    <CreateTime>'.time().'</CreateTime>
                    <MsgType><![CDATA[text]]></MsgType>
                    <Content><![CDATA['.$msg.']]></Content>
                </xml>';
                echo $xml;
            }else{
//                echo __LINE__;die;

                // 获取用户信息
                $url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token='.$this->access_token.'&openid='.$openid.'&lang=zh_CN';
                $user_info = file_get_contents($url);       //
//                echo "<pre>";print_r($u);echo "</pre>";die;
                $u = json_decode($user_info,true);
                //入库用户信息
                $user_data = [
                    'openid'       => $openid,
                    'nickname'    => $u['nickname'],
                    'sex'           => $u['sex'],
                    'headimgurl'  => $u['headimgurl'],
                    'subscribe_time'=> $u['subscribe_time']
                ];

                //openID入库
                $uid = WxUserModel::insertGetId($user_data);

                //回复用户关注
                $msg = '怎么是你 ?';
                $xml = '<xml>
                    <ToUserName><![CDATA['.$openid.']]></ToUserName>
                    <FromUserName><![CDATA['.$xml_obj->ToUserName.']]></FromUserName>
                    <CreateTime>'.time().'</CreateTime>
                    <MsgType><![CDATA[text]]></MsgType>
                    <Content><![CDATA['.$msg.']]></Content>
                </xml>';
                    echo $xml;
            }

        }

        //判断消息类型
        $msg_type = $xml_obj->MsgType;

        $toUser = $xml_obj->FromUserName;       //接收回复消息用户的openID
        $fromUser = $xml_obj->ToUserName;       //开发者公众号的ID
        $time = time();

        $media_id = $xml_obj->MediaId;

        if ($msg_type=='text'){                 // 图片消息

            $content = date('Y-m-d H:i:s') . $xml_obj->Content;

            $response_text = '<xml>
                  <ToUserName><![CDATA['.$toUser.']]></ToUserName>
                  <FromUserName><![CDATA['.$fromUser.']]></FromUserName>
                  <CreateTime>'.$time.'</CreateTime>
                  <MsgType><![CDATA[text]]></MsgType>
                  <Content><![CDATA['.$content.']]></Content>
                </xml>';
            echo $response_text;        //回复用户消息


            // TODO 消息入库
        }elseif ($msg_type=='image'){       // 图片消息
            // TODO 下载图片
            $this->getMedia($media_id,$msg_type);
            // TODO 回复图片
        }elseif ($msg_type=='voice'){        // 语音消息
            // TODO 下载语音
            $this->getMedia($media_id,$msg_type);
            // TODO 回复语音
        }
    }

    /**
     * 获取素材(TEST)
     */
    public function testMedia()
    {
        $midia_id = 'zlLa9YLUER1mOIf-7iMUmpkB2AXMXUDUbpmwd5PbvR9KiC6twlZxqXG0sSGgGB8D';
        $url = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token='.$this->access_token.'&media_id='.$midia_id;

        //获取素材内容
        $data = file_get_contents($url);
        //保存文件
        $file_name = date('YmdHis') . mt_rand(11111,99999) . '.amr';
        file_put_contents($file_name,$data);

        echo '素材下载成功';echo "</br>";
        echo "文件名： " . $file_name;
    }

    public function getMedia($media_id,$media_type)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token='.$this->access_token.'&media_id='.$media_id;

        //获取素材内容
//        $data = file_get_contents($url);
//        $finfo = finfo_open(FILEINFO_MIME_TYPE);
//        $file_info = finfo_file($finfo,$data);
//        var_dump($file_info);die;

        $client = new Client();
        $response = $client->request('GET',$url);
        // 获取文件类型
        $content_type = $response->getHeader('Content-Type')[0];
//        echo $content_type;echo "</br>";
        $pos = strpos($content_type,'/');
//        echo '/:' . $pos;
        $extension = '.' . substr($content_type,$pos+1);
//        echo "</br>ext:" . $extension;die;
        // 获取文件内容
        $file_content = $response->getBody();

        //保存文件
        $save_path = 'wx_media/';
        if ($media_type=='image'){      // 保存图片文件
            $file_name = date('YmdHis') . mt_rand(11111,99999) . $extension;
            $save_path = $save_path . 'imgs/' . $file_name;
        }elseif ($media_type=='voice'){     // 保存语音文件
            $file_name = date('YmdHis') . mt_rand(11111,99999) . $extension;
            $save_path = $save_path . 'voice/' . $file_name;
        }

        file_put_contents($save_path,$file_content);
        echo "save success!" . $save_path;die;
    }
}
