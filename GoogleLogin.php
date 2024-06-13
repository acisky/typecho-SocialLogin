<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class GoogleLoginHandler
{
    public static function handleCallback($code)
    {
        $options = Typecho_Widget::widget('Widget_Options')->plugin('SocialLogin');
        $clientId = $options->googleClientId;
        $clientSecret = $options->googleClientSecret;
        $redirectUri = $options->googleRedirectUri;

        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $params = [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
            'access_type' => 'offline'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $tokenData = json_decode($response, true);
        if (isset($tokenData['access_token'])) {
            $accessToken = $tokenData['access_token'];

            $userInfoUrl = 'https://www.googleapis.com/oauth2/v1/userinfo?access_token=' . $accessToken;
            $userInfo = file_get_contents($userInfoUrl);
            $userInfoData = json_decode($userInfo, true);
            if (isset($userInfoData['id'])) {
                self::loginUser($userInfoData);
            }
        }
    }

    private static function loginUser($userInfo)
    {
        var_dump($userInfo);
        // 根据需要创建或登录用户
        // 示例代码，实际情况需要根据 Typecho 用户系统调整
        $googleId = !empty($userInfo['id']) ? $userInfo['id'] : null;
        $email = !empty($userInfo['email']) ? $userInfo['email'] : null;
        $name = !empty($userInfo['name']) ? $userInfo['name'] : 'GoogleUser';
        $picture = !empty($userInfo['picture']) ? $userInfo['picture'] : 'GooglePicture';
        $screenName = !empty($userInfo['name']) ? $userInfo['name'] : 'GoogleUser';
        $created = time();

        // 查找或创建用户
        $db = Typecho_Db::get();
        $user = $db->fetchRow($db->select()->from('table.socialuser')->where('googleId = ?', $googleId)->where('source = ?', '1'));

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
                    'googleId' => $googleId,
                    'picture' => $picture,
                    'created' => $created,
                    'group' => 'subscriber',
                    'source' => '1',
                )));
            $uid = $db->lastInsertId();
            Typecho_Cookie::set('__socialuser_id', $uid, 0, '/');
            Typecho_Cookie::set('__socialuser_name', $name, 0, '/');
        }

        // 跳转到主页
        header('Location: ' . Typecho_Common::url('/', Helper::options()->index));
        exit;
    }
}
?>