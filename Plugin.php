<?php
/**
 * 社交平台登录
 * 
 * @package SocialLogin
 * @author acisky
 * @version 1.0.0
 * @link https://www.aciuz.com
 *
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class SocialLogin_Plugin implements Typecho_Plugin_Interface
{
    public static function activate()
    {
        Helper::addRoute('google_oauth_callback', '/oauth2callback', 'SocialLogin_Action', 'action');
        Helper::addRoute('google_oauth_logout', '/oauth2logout', 'SocialLogin_Action', 'logout');
        Helper::addRoute('google_oauth_token', '/oauth2token', 'SocialLogin_Action', 'token');
        Typecho_Plugin::factory('Widget_Archive')->header = array('SocialLogin_Plugin', 'addScripts');
        Typecho_Plugin::factory('Widget_Archive')->footer = array('SocialLogin_Plugin', 'handleAuthCallback');

        $db= Typecho_Db::get();

        // 创建表
        $dbname =$db->getPrefix() . 'socialuser';
        $sql = "SHOW TABLES LIKE '%" . $dbname . "%'";
        if (count($db->fetchAll($sql)) == 0) {
            $sql = '
            DROP TABLE IF EXISTS `'.$dbname.'`;
            CREATE TABLE `'.$dbname.'` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `mail` varchar(255) DEFAULT NULL,
                `screenName` varchar(255) NOT NULL,
                `googleId` varchar(255) DEFAULT NULL,
                `twitterId` varchar(255) DEFAULT NULL,
                `created` int(10) NOT NULL,
                `group` varchar(255) NOT NULL,
                `picture` varchar(255) DEFAULT NULL,
                `source` varchar(50) NOT NULL, -- 用于区分twitter和google
                PRIMARY KEY (`id`),
                UNIQUE KEY `googleId` (`googleId`(191)),
                UNIQUE KEY `twitterId` (`twitterId`(191))
            ) DEFAULT CHARSET=utf8mb4';
 
            $sqls = explode(';', $sql);
            foreach ($sqls as $sql) {
                $db->query($sql);
            }
        } else {
            $db->query($db->delete('table.socialuser')->where('id >= ?', 0));
        }


        return _t('插件已激活');
    }

    public static function deactivate()
    {

        // Drop 表
        $db= Typecho_Db::get();
        $dbname =$db->getPrefix() . 'socialuser';
        $sql = 'DROP TABLE IF EXISTS `'.$dbname.'`';
        $db->query($sql);

        Helper::removeRoute('google_oauth_callback');
        Helper::removeRoute('google_oauth_logout');
        Helper::removeRoute('google_oauth_token');
        return _t('插件已禁用');
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $googleClientId = new Typecho_Widget_Helper_Form_Element_Text('googleClientId', NULL, '', _t('Google Client ID'));
        $googleClientSecret = new Typecho_Widget_Helper_Form_Element_Text('googleClientSecret', NULL, '', _t('Google Client Secret'));
        $googleRedirectUri = new Typecho_Widget_Helper_Form_Element_Text('googleRedirectUri', NULL, '', _t('Google Redirect URI'), _t('请输入你的重定向 URI'));
        $form->addInput($googleClientId);
        $form->addInput($googleClientSecret);
        $form->addInput($googleRedirectUri);

        $twitterClientId = new Typecho_Widget_Helper_Form_Element_Text('twitterClientId', NULL, '', _t('Twitter Client ID'));
        $twitterClientSecret = new Typecho_Widget_Helper_Form_Element_Text('twitterClientSecret', NULL, '', _t('Twitter Client Secret'));
        $form->addInput($twitterClientId);
        $form->addInput($twitterClientSecret);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    public static function addScripts()
    {
        $options = Typecho_Widget::widget('Widget_Options')->plugin('SocialLogin');
        $googleClientId = $options->googleClientId;
        $redirectUri = $options->googleRedirectUri;

        // 输出 Google 登录的 JavaScript 代码
        echo '<script src="https://accounts.google.com/gsi/client" async defer></script>';
        echo '<script type="text/javascript">
        function handleLogout() {
            $.ajax({
                url: "' . Helper::options()->index . '/oauth2logout",
                type: "POST",
                dataType: "json",
                contentType: "application/json; charset=utf-8",
                success: function(data) {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert("Logout failed: " + data.message);
                    }
                },
                error: function(xhr, status, error) {
                    alert("Logout failed: " + error);
                }
            });
        }
        </script>';
        if (!Typecho_Cookie::get('__socialuser_id')) {
        echo '<script type="text/javascript">
        window.onload = function () {
            google.accounts.id.initialize({
              client_id: "'.$googleClientId.'",
              callback: handleCredentialResponse
            });
            google.accounts.id.prompt();
          };

          function handleCredentialResponse(param) {
            var data = {
                credential: param.credential,
            };
            $.ajax({
                url: "' . Helper::options()->index . '/oauth2callback",
                type: "POST",
                data: data,
                dataType: "json",
                contentType: "application/x-www-form-urlencoded",
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert("Login failed: " + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error(error);
                }
            });
          }

          function handleTwitterOauthToken() {
            $.ajax({
                url: "' . Helper::options()->index . '/oauth2token",
                type: "POST",
                dataType: "json",
                contentType: "application/json; charset=utf-8",
                success: function(data) {
                    if (data.success) {
                        window.location.href = "https://api.twitter.com/oauth/authenticate?oauth_token=" + data.oauth_token;
                    } else {
                        alert("Login failed: " + data.message);
                    }
                },
                error: function(xhr, status, error) {
                    alert("Login failed: " + error);
                }
            });
          }
      </script>';
  }
    }

    public static function renderLoginButtons($className='login-box',$beforeGoogle='',$beforeTwitter='')
    {
        if (Typecho_Cookie::get('__socialuser_id')) {
                        $db= Typecho_Db::get();
            $user = $db->fetchRow($db->select()->from('table.socialuser')->where('id = ?', Typecho_Cookie::get('__socialuser_id')));
            $userName = Typecho_Cookie::get('__socialuser_name');
            $userEmail = Typecho_Cookie::get('__socialuser_email');
            echo '<div class="'.$className.'"><span>'.htmlspecialchars($userName).'</span>';
            echo '<a href="javascript:void(0);" onclick="handleLogout()">Login Out</a></div>';
        } else {
            $options = Typecho_Widget::widget('Widget_Options')->plugin('SocialLogin');
        $googleClientId = $options->googleClientId;
        $redirectUri = $options->googleRedirectUri;
        $authUrl = "https://accounts.google.com/o/oauth2/auth?response_type=code&client_id=" . $googleClientId . "&redirect_uri=" . urlencode($redirectUri) . "&scope=email%20profile";

                echo '<div id="g_id_onload"
             data-client_id="'.$googleClientId.'"
             data-login_uri="'.$redirectUri.'"
             data-auto_select="true">
        </div><div class="'.$className.'"><a href="'.$authUrl.'" id="googleLoginButton">'.$beforeGoogle.'Signup with Google</a>';
        echo '<a href="javascript:void(0)" onclick="handleTwitterOauthToken()">'.$beforeTwitter.'Signup with X</a></div>';
        }
        
    }

    public static function handleAuthCallback()
    {
        if (isset($_GET['code'])) {
            require_once 'GoogleLogin.php';
            GoogleLoginHandler::handleCallback($_GET['code']);
        }

        if(isset($_GET['oauth_token']) && isset($_GET['oauth_verifier'])) {
            require_once 'TwitterLogin.php';
            TwitterLogin::getAccessToken($_GET['oauth_token'],$_GET['oauth_verifier']);
        }
    }
}
?>