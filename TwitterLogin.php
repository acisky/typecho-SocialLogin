<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class TwitterLogin
{
    public static function login()
    {
        $options = Typecho_Widget::widget('Widget_Options')->plugin('SocialLogin');
        $clientId = $options->twitterClientId;
        $clientSecret = $options->twitterClientSecret;
        $redirectUri = $options->googleRedirectUri;

        $oauth_timestamp = time();
        $oauth_signature_method = 'HMAC-SHA1';
        $oauth_version = '1.0';

        $requestUrl = 'https://api.twitter.com/oauth/request_token';
        $requestMethod = "POST";

        // 构造 Authorization 头部
        $oauth = array(
            'oauth_callback' => $redirectUri,
            'oauth_consumer_key' => $clientId,
            'oauth_nonce' => self::generateNonce(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => $oauth_timestamp,
            'oauth_version' => '1.0'
        );

        $base_info = self::buildBaseString($requestUrl, $requestMethod, $oauth);
        $composite_key = rawurlencode($clientSecret) . '&';
        $oauth_signature = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true));
        $oauth['oauth_signature'] = $oauth_signature;

        $header = array(self::buildAuthorizationHeader($oauth), 'Expect:');
        $options = array(
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_HEADER => false,
            CURLOPT_URL => $requestUrl,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false
        );

        $feed = curl_init();
        curl_setopt_array($feed, $options);
        $response = curl_exec($feed);
        curl_close($feed);

        parse_str($response, $result);

        if(isset($result['oauth_callback_confirmed'])) {
            echo json_encode(['success' => true,'oauth_token' => $result['oauth_token']]);
        } else {
            echo json_encode(['success' => false,'oauth_token' => null]);
        }
        exit;
    }

    public static function getAccessToken($oauth_token, $oauth_verifier)
    {
        $options = Typecho_Widget::widget('Widget_Options')->plugin('SocialLogin');
        $clientId = $options->twitterClientId;
        $clientSecret = $options->twitterClientSecret;
        $requestUrl = 'https://api.twitter.com/oauth/access_token';
        $requestMethod = "POST";

        $oauth_timestamp = time();
        $oauth_nonce = self::generateNonce();

        // 构造 Authorization 头部
        $oauth = array(
            'oauth_consumer_key' => $clientId,
            'oauth_token' => $oauth_token,
            'oauth_nonce' => $oauth_nonce,
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => $oauth_timestamp,
            'oauth_verifier' => $oauth_verifier,
            'oauth_version' => '1.0'
        );

        $base_info = self::buildBaseString($requestUrl, $requestMethod, $oauth);
        $composite_key = rawurlencode($clientSecret) . '&' . rawurlencode($oauth_token);
        $oauth_signature = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true));
        $oauth['oauth_signature'] = $oauth_signature;

        $header = array(self::buildAuthorizationHeader($oauth), 'Expect:');
        $options = array(
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_HEADER => false,
            CURLOPT_URL => $requestUrl,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POSTFIELDS => http_build_query(array('oauth_verifier' => $oauth_verifier))
        );

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        curl_close($ch);

        parse_str($response, $result);

        self::loginUser($result);

        // self::getUserProfile($result['access_token'],$result['access_token_secret'],$result['user_id'],$result['screen_name']);
    }

    public static function getUserProfile($access_token, $access_token_secret, $user_id, $screen_name)
    {
        $options = Typecho_Widget::widget('Widget_Options')->plugin('SocialLogin');
        $clientId = $options->twitterClientId;
        $clientSecret = $options->twitterClientSecret;
        $requestUrl = 'https://api.twitter.com/1.1/account/verify_credentials.json';
        $requestMethod = "GET";

        $oauth_timestamp = time();
        $oauth_nonce = self::generateNonce();

        // 构造 Authorization 头部
        $oauth = array(
            'oauth_consumer_key' => $clientId,
            'oauth_token' => $access_token,
            'oauth_nonce' => $oauth_nonce,
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => $oauth_timestamp,
            'oauth_version' => '1.0'
        );

        $base_info = self::buildBaseString($requestUrl, $requestMethod, $oauth);
        $composite_key = rawurlencode($clientSecret) . '&' . rawurlencode($access_token_secret);
        $oauth_signature = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true));
        $oauth['oauth_signature'] = $oauth_signature;

        $header = array(self::buildAuthorizationHeader($oauth), 'Expect:');
        $options = array(
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_HEADER => false,
            CURLOPT_URL => $requestUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false
        );

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        curl_close($ch);

        parse_str($response, $result);
        var_dump($result);
    }

    private static function loginUser($userInfo)
    {
        // 根据需要创建或登录用户
        // 示例代码，实际情况需要根据 Typecho 用户系统调整
        $twitterId = !empty($userInfo['user_id']) ? $userInfo['user_id'] : null;
        $email = !empty($userInfo['email']) ? $userInfo['email'] : null;
        $name = !empty($userInfo['screen_name']) ? $userInfo['screen_name'] : 'TwitterUser';
        $picture = !empty($userInfo['picture']) ? $userInfo['picture'] : 'TwitterPicture';
        $screenName = !empty($userInfo['screen_name']) ? $userInfo['screen_name'] : 'TwitterUser';
        $created = time();

        // 查找或创建用户
        $db = Typecho_Db::get();
        $user = $db->fetchRow($db->select()->from('table.socialuser')->where('twitterId = ?', $twitterId)->where('source = ?', '2'));

        if ($user) {
            // 用户已存在，直接登录
            Typecho_Cookie::set('__socialuser_id', $user['id'], 0, '/');
            Typecho_Cookie::set('__socialuser_name', $user['name'], 0, '/');
        } else {
            // 用户不存在，创建新用户
            $db->query($db->insert('table.socialuser')
                ->rows(array(
                    'name' => $name,
                    'mail' => $email,
                    'screenName' => $screenName,
                    'twitterId' => $twitterId,
                    'picture' => $picture,
                    'created' => $created,
                    'group' => 'subscriber',
                    'source' => '2',
                )));
            $uid = $db->lastInsertId();
            Typecho_Cookie::set('__socialuser_id', $uid, 0, '/');
            Typecho_Cookie::set('__socialuser_name', $name, 0, '/');
        }

        // 跳转到主页
        header('Location: ' . Typecho_Common::url('/', Helper::options()->index));
        exit;
    }

    public static function generateNonce($length = 32) {
        $nonce = "";
        while (strlen($nonce) < $length) {
            $bytes = random_bytes(16); // 随机字节
            $nonce .= bin2hex($bytes);
        }
        return substr($nonce, 0, $length);
    }

    public static function buildBaseString($baseURI, $method, $params) {
        $r = array(); // 列表存储键值对
        ksort($params); // 对参数进行排序
        foreach($params as $key=>$value){
            $r[] = "$key=" . rawurlencode($value); // 编码并加入到数组
        }
        return $method . "&" . rawurlencode($baseURI) . '&' . rawurlencode(implode('&', $r)); // 生成基本字符串
    }

    public static function buildAuthorizationHeader($oauth) {
        $r = 'Authorization: OAuth '; // 构建Authorization头
        $values = array();
        foreach($oauth as $key=>$value)
            $values[] = "$key=\"" . rawurlencode($value) . "\""; // 编码键值对
        $r .= implode(', ', $values); // 用逗号连接键值对
        return $r; // 返回Authorization头
    }
}
?>