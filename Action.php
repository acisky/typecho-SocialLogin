<?php

class SocialLogin_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function action()
    {
        require_once 'vendor/autoload.php';

        $options = Typecho_Widget::widget('Widget_Options')->plugin('SocialLogin');
        $googleClientId = $options->googleClientId;
        
        // 确保请求来自 POST 方法
        if ($_POST['credential']) {
            $token = $_POST['credential'];

            if (!empty($token)) {
                $client = new Google_Client(['client_id' => $googleClientId]);  // 使用您在 Google API Console 中创建的客户端 ID
                $payload = $client->verifyIdToken($token);
                if ($payload) {
                    $googleId = !empty($payload['sub']) ? $payload['sub'] : null;
                    $email = !empty($payload['email']) ? $payload['email'] : null;
                    $name = !empty($payload['name']) ? $payload['name'] : 'GoogleUser';
                    $picture = !empty($payload['picture']) ? $payload['picture'] : 'GooglePicture';
                    $screenName = !empty($payload['name']) ? $payload['name'] : 'GoogleUser';
                    $created = time();
                    // 令牌有效，处理用户数据
                    // 例如，您可以检查数据库中是否存在该用户，或者创建一个新的用户记录
                    // 查找或创建用户
                    $db = Typecho_Db::get();
                    $user = $db->fetchRow($db->select()->from('table.socialuser')->where('googleId = ?', $googleId));

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

                    echo json_encode(['status' => 'success', 'username' => $name]);
                } else {
                    // 无效的 ID 令牌
                    http_response_code(401);
                    echo json_encode(['status' => 'error', 'message' => 'Invalid ID Token']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'No token provided']);
            }
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
        }
    }

    public static function token()
    {
        require_once 'TwitterLogin.php';
        TwitterLogin::login();
    }

    public static function logout()
    {
        // 清除所有与用户相关的 cookie
        Typecho_Cookie::delete('__socialuser_id');
        Typecho_Cookie::delete('__socialuser_name');
        Typecho_Cookie::delete('__socialuser_email');

        // 返回 JSON 响应，或重定向
        echo json_encode(['success' => true, 'message' => 'Successfully logged out']);
        exit;
    }

}