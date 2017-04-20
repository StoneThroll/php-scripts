<?php

include "../../inc/str.php";
/**
 * Created by PhpStorm.
 * User: shaolei
 * Date: 16/12/16
 * Time: 上午8:45
 */
header("Content-type: text/html; charset=utf-8"); 
class CurlRequest {
    /**
     * Http协议调用
     * @param $url api地址
     * @param $params 参数
     * @param string $type 提交类型
     * @return bool|mixed
     * @throws Exception
     */
    private $_response = array();

    protected function http($url, $params = null, $type = 'get', $cookies = null)
    {
        $curl = curl_init();

        switch ($type) {
            case 'get':
                is_array($params) && $params = http_build_query($params);
                !empty($params) && $url .= (stripos($url, '?') === false ? '?' : '&') . $params;
                break;
            case 'post':
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
                break;
            default:
                throw new Exception("Invalid http type '{$type}.' called.");
        }

        if( $cookies ) {
//            $headers = array(
//            'Origin: http://',
//            'Upgrade-Insecure-Requests: 1',
//            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.133 Safari/537.36',
//            'Content-Type: application/x-www-form-urlencoded',
//            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
//            'Referer: http://',
//            'Accept-Encoding: gzip, deflate',
//            'Accept-Language: zh-CN,zh;q=0.8,en;q=0.6,fr;q=0.4,ja;q=0.2'
//            );
//            $headers[] =  "Cookie:{$cookiesStr}";
            $cArr = array();
            foreach( $cookies as $key=>$value ) {
                $cArr[] =" {$key}={$value}";
            }
            $cookiesStr = implode(';', $cArr );
            curl_setopt($curl, CURLOPT_HTTPHEADER, array("Cookie:{$cookiesStr}"));
        }

        if (stripos($url, "https://") !== false) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1); // 微信官方屏蔽了ssl2和ssl3, 启用更高级的ssl
        }
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADERFUNCTION,array('self','curlResponseHeaderCallback'));

        $content = curl_exec($curl);
        $status = curl_getinfo($curl);

        curl_close($curl);

        if (isset($status['http_code']) && intval($status['http_code']) == 200) {
            $this->_response['body'] = $content;
        }
        $this->_response['http_code'] = $status['http_code'];
    }

    public function httpGet($url, $params = null, $cookies = null){
        $this->http($url, $params, 'get', $cookies);
        return $this->getResponse();
    }

    public function httpPost($url, $params = null, $cookies = null){
        // var_dump( $params );
        $this->http($url, $params, 'post', $cookies);
        return $this->getResponse();
    }

    public function curlResponseHeaderCallback($curl, $line) {
        if( preg_match("/^Set-Cookie:\s*([^;]*)/mi", $line, $matches ) ) {
            $pair = explode('=', $matches[1], 2 );
            $this->_response['cookies'][$pair[0]] = $pair[1];
        }
        return strlen($line);
    }

    public function getResponse() {
        return $this->_response;
    }
}


$method = isset($_GET['method'])?$_GET['method']:'';

if( $method == 'getyzm' ) {
    $r = new CurlRequest();
//    var_dump( $r );
    $response = $r->httpGet('http://xxx/getYZM');
    if( $response['http_code'] == 200 ) {
        header('Content-Type: image/jpg' );
        foreach( $response['cookies'] as $key=> $value ) {
            setcookie( 'XXXX_'.$key, $value, time()+600 );
        }
        echo $response['body'];
    }
}else if( $method == 'select' ){
}else if( $method == 'query' ){
    setcookie( 'XXXX_JSESSIONID', null, -1 );
    setcookie( 'XXXX_BIGipServerjszg_XXXXchafen_pool', null, -1 );

    if(!isset($_COOKIE['XXXX_BIGipServerjszg_XXXXchafen_pool']) && !isset($_COOKIE['XXXX_JSESSIONID'])){
            $response['body'] = '<body><div align="center" style="padding-top:100px"> 验证码已过期<br> <a href="//'.$_SERVER['HTTP_HOST'].'/tools/XXXX_score/index.html">重新查询</a> </div></body>';
    }else{

        //返回查询结果
        $cookies = array(
            'BIGipServerjszg_XXXXchafen_pool'=>$_COOKIE['XXXX_BIGipServerjszg_XXXXchafen_pool'],
            'JSESSIONID'=>$_COOKIE['XXXX_JSESSIONID'],
        );
        $r = new CurlRequest();
        $response = $r->httpPost('http://xxxx/selectScore.do?method=getMyScore',
            array(
                'name'=>$_GET['name'],
                'zjhm'=>$_GET['zjhm'],
                'yzm'=>$_GET['yzm'],
            ),
            $cookies
        ); 

        if(preg_match('/姓名或证件号码输入有误\w*/',$response['body']) || preg_match('/未找到姓名\w*/',$response['body']) || preg_match('/验证码输入有误\w*/',$response['body'])){ 
            $response['body'] = preg_replace('/\/Student\/selectScore.jsp/','//'.$_SERVER['HTTP_HOST'].'/tools/XXXX_score/index.html',$response['body']);
        }else if(strlen($response['body']) > 3000){
            $response['body'] = preg_replace('/\/Student\/images\/login_p_1128.css/','./styles/login_p_1128.css',$response['body']);
            $response['body'] = preg_replace('/\<div id\=\"top\"\>\<\/div\>/','',$response['body']);
            $response['body'] = preg_replace('/window.close\(\)/','closeWebPage()',$response['body']);
        }

    //var_dump(htmlspecialchars($response['body']));die();
    }

    echo <<<HTML
{$response['body']}
HTML;

}else{
    header('Location: http://'.$_SERVER['HTTP_HOST'].'/tools/XXXX_score/index.html');
}

