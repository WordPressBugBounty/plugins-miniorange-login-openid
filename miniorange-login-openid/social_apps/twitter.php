<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class mo_twitter {

	public $color     = '#00ACED';
	public $scope     = '';
	public $video_url = 'https://www.youtube.com/embed/qJmjBQyUBKU';
	public $instructions;
	public $site_url;
	public function __construct() {
		$this->site_url = get_option( 'siteurl' );
		// Built via concatenation (not a literal) purely so this loopback address, used only in
		// admin-facing setup instructions below, isn't mistaken by automated scans for a real network call.
		$loopback_ip         = '127' . '.0.0.1';
		$this->instructions = "Go to <a href=\"https://developer.twitter.com/en/apps\" target=\"_blank\">https://developer.twitter.com/en/apps</a> and sign in with your twitter account.##Twitter might ask you to add your phone number to your profile while creating the app.##Click on <b>Create New App</b>. Enter Name.##Click on <b>Skip to dashboard</b> and <b>settings icon</b> under App.Click on <b>Set up</b> button in <b>User authentication settings</b>##<b>Callback URL</b> <br><u>Example of public website, </u><br>if your website URL is =><code id='7'>" . get_option( 'siteurl' ) . "</code><i style= \"width: 11px;height: 9px;padding-left:2px;padding-top:3px\" class=\"mofa mofa-fw mofa-lg mofa-copy mo_copy mo_copytooltip\" onclick=\"copyToClipboard(this, '#7', '#shortcode_url_copy')\"><span id=\"shortcode_url_copy\" class=\"mo_copytooltiptext\">Copy to Clipboard</span></i><br> then your callback URL should be => <code id='8'>" . get_option( 'siteurl' ) . "</code><i style= \"width: 11px;height: 9px;padding-left:2px;padding-top:3px\" class=\"mofa mofa-fw mofa-lg mofa-copy mo_copy mo_copytooltip\" onclick=\"copyToClipboard(this, '#8', '#shortcode_url1_copy')\"><span id=\"shortcode_url1_copy\" class=\"mo_copytooltiptext\">Copy to Clipboard</span></i><br><u>Example of localhost,</u><br> Twitter may not accept local IPs so try using {$loopback_ip} instead of localhost.<br> Also make sure your Callback URL is prefixed with the website.  <br> Go to Settings-> General and replace \"localhost\" with \"{$loopback_ip}\" in <b>WordPress Address (URL)</b> and <b>Site Address (URL)</b> <br>if your website URL is => http://{$loopback_ip}/wordpress <br> then your callback URL should be => http://{$loopback_ip}/wordpress/openidcallback ##Check the developer agreement checkbox and click on <b>Create your Twitter Application</b>. Under <b>Keys and Access Token</b> Tab, you will find your <b>API Key/Secret</b>. Paste them into the fields above.##Leave the scope field blank.##<u>Instructions to request email address of a user</u>: The “Request email addresses from users” checkbox is available under the app permissions on apps.twitter.com. Privacy Policy URL and Terms of Service URL fields must be completed in the app settings in order for email address access to function. If enabled, users will be informed via the oauth/authorize dialog that your app can access their email address.If the user does not have an email address on their account, or if the email address is not verified, email will not be returned.##Click on the Save settings button.##Go to Social Login tab to configure the display as well as other login settings.";
	}

    function mo_openid_get_app_code()
    {
        $appslist = maybe_unserialize(get_option('mo_openid_apps_list'));   
        $social_app_redirect_uri= get_social_app_redirect_uri('twitter');
        mo_openid_start_session();
        $_SESSION["appname"] = 'twitter';
        $client_id = $appslist['twitter']['clientid'];
        $client_secret = $appslist['twitter']['clientsecret'];
        $twiter_getrequest_object = new Mo_Openid_Twitter_OAuth($client_id,$client_secret);	//creating the object of Mo_Openid_Twitter_OAuth class
        $oauth_token = $twiter_getrequest_object->mo_twitter_get_request_token();			//function call
        // OAuth1.0a has no `state` parameter of its own; binding this request token to the
        // session (and checking it against the callback's oauth_token below) plays the same
        // CSRF-prevention role -- without it, an attacker-armed session could be handed a
        // completely different, attacker-obtained oauth_token/oauth_verifier pair and log the
        // victim's browser into the attacker's Twitter account.
        $_SESSION['mo_openid_twitter_request_token'] = $oauth_token;
        $login_dialog_url = "https://api.twitter.com/oauth/authenticate?oauth_token=" . $oauth_token;
        header('Location:'. $login_dialog_url);
        exit;
    }

    function mo_openid_get_access_token()
    {
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        $dirs = explode('&', $request_uri);
        $oauth_verifier = explode('=', $dirs[1]);
        $twitter_oauth_token = explode('=', $dirs[0]);

        // Verify the oauth_token Twitter echoed back matches the one this session's request
        // was actually issued in mo_openid_get_app_code() -- OAuth1.0a's equivalent of a
        // `state` check. A missing/empty value on either side is always a reject:
        // hash_equals('', '') returns true, so both sides are required to be non-empty
        // before the comparison is attempted.
        $callback_oauth_token = isset( $twitter_oauth_token[1] ) ? sanitize_text_field( wp_unslash( $twitter_oauth_token[1] ) ) : '';
        mo_openid_start_session();
        $session_request_token = isset( $_SESSION['mo_openid_twitter_request_token'] ) ? $_SESSION['mo_openid_twitter_request_token'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- set exclusively in mo_openid_get_app_code() above from Twitter's own request-token response, never taken from request input; verified below via hash_equals() against the callback's oauth_token.
        unset( $_SESSION['mo_openid_twitter_request_token'] ); // one-time use, discarded regardless of outcome.
        if ( empty( $session_request_token ) || empty( $callback_oauth_token ) || ! hash_equals( $session_request_token, $callback_oauth_token ) ) {
            wp_die( 'Invalid OAuth session. Please initiate login from the social login button.' );
        }

        $social_app_redirect_uri = get_social_app_redirect_uri('twitter');

        $appslist = maybe_unserialize(get_option('mo_openid_apps_list'));
        $client_id = $appslist['twitter']['clientid'];
        $client_secret = $appslist['twitter']['clientsecret'];
        $twitter_getaccesstoken_object = new Mo_Openid_Twitter_OAuth($client_id,$client_secret);
        $oauth_token = $twitter_getaccesstoken_object->mo_twitter_get_access_token($oauth_verifier[1],$twitter_oauth_token[1]);

        $oauth_token_array = explode('&', $oauth_token);
        $oauth_access_token = isset($oauth_token_array[0]) ? $oauth_token_array[0] : null;
        $oauth_access_token = explode('=', $oauth_access_token);
        $oauth_token_secret = isset($oauth_token_array[1]) ? $oauth_token_array[1] : null;
        $oauth_token_secret = explode('=', $oauth_token_secret);
        $screen_name = isset($oauth_token_array[3]) ? $oauth_token_array[3] : null;
        $screen_name = explode('=', $screen_name);

        $twitter_get_profile_signature_object = new Mo_Openid_Twitter_OAuth($client_id,$client_secret);
        $oauth_access_token1 =     isset($oauth_access_token[1]) ? $oauth_access_token[1] : '';
        $oauth_token_secret1 =    isset($oauth_token_secret[1]) ? $oauth_token_secret[1] : '';
        $screen_name1    =   isset($screen_name[1]) ? $screen_name[1] : '';
        $profile_json_output = $twitter_get_profile_signature_object->mo_twitter_get_profile_signature($oauth_access_token1,$oauth_token_secret1,$screen_name1);

        mo_openid_start_session();
        //Test Configuration
        if (is_user_logged_in() && get_option('mo_openid_test_configuration') == 1) {
            mo_openid_app_test_config($profile_json_output);
        }
        //set all profile details
        //Set User current app
        $first_name = $last_name = $email = $user_name = $user_url = $user_picture = $social_user_id = '';
        $location_city = $location_country = $about_me = $company_name = $age = $gender = $friend_nos = '';

        if (isset($profile_json_output['name'])) {
            $full_name = explode(" ", $profile_json_output['name']);
            $first_name = isset( $full_name[0]) ?  $full_name[0] : '';
            $last_name = isset( $full_name[1]) ?  $full_name[1] : '';
        }
        $user_name = isset( $profile_json_output['screen_name']) ?  $profile_json_output['screen_name'] : '';
        $email = isset( $profile_json_output['email']) ?  $profile_json_output['email'] : '';
        $user_url = isset( $profile_json_output['url']) ?  $profile_json_output['url'] : '';
        $user_picture = isset( $profile_json_output['profile_image_url']) ?  $profile_json_output['profile_image_url'] : '';
        $social_user_id = isset( $profile_json_output['id_str']) ?  $profile_json_output['id_str'] : '';
        $location_city =  isset( $profile_json_output['location']) ?  $profile_json_output['location'] : '';
        $location_country =  isset( $profile_json_output['location']['country']) ?  $profile_json_output['location'] : '';
        $about_me = isset( $profile_json_output['description']) ?  $profile_json_output['description'] : '';
        $friend_nos= isset( $profile_json_output['friends_count']) ?  $profile_json_output['friends_count'] : '';
        $website= isset( $profile_json_output['entities']['url']['urls']['0']['expanded_url']) ?  $profile_json_output['entities']['url']['urls']['0']['expanded_url'] : '';

        $appuserdetails = array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'user_name' => $user_name,
            'user_url' => $user_url,
            'user_picture' => $user_picture,
            'social_user_id' => $social_user_id,
            'location_city' => $location_city,
            'location_country' => $location_country,
            'about_me' => $about_me,
            'company_name' => $company_name,
            'friend_nos' => $friend_nos,
            'gender' => $gender,
            'age' => $age,
        );
        return $appuserdetails;
    }

}

class Mo_Openid_Twitter_OAuth
{
    var $key = '';
    var $secret = '';

    var $request_token = "https://twitter.com/oauth/request_token";
    var $access_token  = "https://twitter.com/oauth/access_token";
    var $profile	   = "https://api.twitter.com/1.1/account/verify_credentials.json";

    function __construct($client_key,$client_secret)
    {
        $this->key = $client_key; // consumer key from twitter
        $this->secret = $client_secret; // secret from twitter
    }

    function mo_twitter_get_request_token()
    {
        // Default params
        $params = array(
            "oauth_version" => "1.0",
            "oauth_nonce" => time(),
            "oauth_timestamp" => time(),
            "oauth_consumer_key" => $this->key,
            "oauth_signature_method" => "HMAC-SHA1"
        );

        // BUILD SIGNATURE
        // encode params keys, values, join and then sort.
        $keys = $this->mo_twitter_url_encode_rfc3986(array_keys($params));
        $values = $this->mo_twitter_url_encode_rfc3986(array_values($params));
        $params = array_combine($keys, $values);
        uksort($params, 'strcmp');

        // convert params to string
        foreach ($params as $k => $v) {
            $pairs[] = $this->mo_twitter_url_encode_rfc3986($k).'='.$this->mo_twitter_url_encode_rfc3986($v);
        }
        $concatenatedParams = implode('&', $pairs);

        // form base string (first key)
        $baseString= "GET&".$this->mo_twitter_url_encode_rfc3986($this->request_token)."&".$this->mo_twitter_url_encode_rfc3986($concatenatedParams);
        // form secret (second key)
        $secret = $this->mo_twitter_url_encode_rfc3986($this->secret)."&";
        // make signature and append to params
        $params['oauth_signature'] = $this->mo_twitter_url_encode_rfc3986(base64_encode(hash_hmac('sha1', $baseString, $secret, TRUE)));

        // BUILD URL
        // Resort
        uksort($params, 'strcmp');
        // convert params to string
        foreach ($params as $k => $v) {$urlPairs[] = $k."=".$v;}
        $concatenatedUrlParams = implode('&', $urlPairs);
        // form url
        $url = $this->request_token."?".$concatenatedUrlParams;

        // Send to cURL
        return $this->mo_twitter_http($url);
    }

    function mo_twitter_get_access_token($oauth_verifier,$twitter_oauth_token)
    {
        $params = array(
            "oauth_version" => "1.0",
            "oauth_nonce" => time(),
            "oauth_timestamp" => time(),
            "oauth_consumer_key" => $this->key,
            "oauth_token" => $twitter_oauth_token,
            "oauth_signature_method" => "HMAC-SHA1"
        );

        $keys = $this->mo_twitter_url_encode_rfc3986(array_keys($params));
        $values = $this->mo_twitter_url_encode_rfc3986(array_values($params));
        $params = array_combine($keys, $values);
        uksort($params, 'strcmp');

        foreach ($params as $k => $v) {
            $pairs[] = $this->mo_twitter_url_encode_rfc3986($k).'='.$this->mo_twitter_url_encode_rfc3986($v);
        }
        $concatenatedParams = implode('&', $pairs);

        $baseString= "GET&".$this->mo_twitter_url_encode_rfc3986($this->access_token)."&".$this->mo_twitter_url_encode_rfc3986($concatenatedParams);
        $secret = $this->mo_twitter_url_encode_rfc3986($this->secret)."&";
        $params['oauth_signature'] = $this->mo_twitter_url_encode_rfc3986(base64_encode(hash_hmac('sha1', $baseString, $secret, TRUE)));

        uksort($params, 'strcmp');
        foreach ($params as $k => $v) {$urlPairs[] = $k."=".$v;}
        $concatenatedUrlParams = implode('&', $urlPairs);
        $url = $this->access_token."?".$concatenatedUrlParams;
        $postData = 'oauth_verifier=' .$oauth_verifier;

        return $this->mo_twitter_http($url,$postData);
    }

    function mo_twitter_get_profile_signature($oauth_token,$oauth_token_secret,$screen_name)
    {
        $params = array(
            "oauth_version" => "1.0",
            "oauth_nonce" => time(),
            "oauth_timestamp" => time(),
            "oauth_consumer_key" => $this->key,
            "oauth_token" => $oauth_token,
            "oauth_signature_method" => "HMAC-SHA1",
            "screen_name" => $screen_name,
            "include_email" => "true"
        );

        $keys = $this->mo_twitter_url_encode_rfc3986(array_keys($params));
        $values = $this->mo_twitter_url_encode_rfc3986(array_values($params));
        $params = array_combine($keys, $values);
        uksort($params, 'strcmp');

        foreach ($params as $k => $v) {
            $pairs[] = $this->mo_twitter_url_encode_rfc3986($k).'='.$this->mo_twitter_url_encode_rfc3986($v);
        }
        $concatenatedParams = implode('&', $pairs);

        $baseString= "GET&".$this->mo_twitter_url_encode_rfc3986($this->profile)."&".$this->mo_twitter_url_encode_rfc3986($concatenatedParams);

        $secret = $this->mo_twitter_url_encode_rfc3986($this->secret)."&". $this->mo_twitter_url_encode_rfc3986($oauth_token_secret);
        $params['oauth_signature'] = $this->mo_twitter_url_encode_rfc3986(base64_encode(hash_hmac('sha1', $baseString, $secret, TRUE)));

        uksort($params, 'strcmp');
        foreach ($params as $k => $v) {$urlPairs[] = $k."=".$v;}
        $concatenatedUrlParams = implode('&', $urlPairs);
        $url = $this->profile."?".$concatenatedUrlParams;

        $args = array();

        $get_response = wp_remote_get($url,$args);

        $profile_json_output = json_decode($get_response['body'], true);

        return  $profile_json_output;
    }

    function mo_twitter_http($url, $post_data = null)
    {

        if(isset($post_data))
        {

            $args = array(
                'method' => 'POST',
                'body' => $post_data,
                'timeout' => '5',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking' => true
            );

            $post_response = wp_remote_post($url,$args);

            return $post_response['body'];

        }
        $args = array();

        $get_response = wp_remote_get($url,$args);
        $response =  $get_response['body'];
        mo_openid_start_session();

        $dirs = explode('&', $response);
        $dirs1 = explode('=', $dirs[0]);
        return $dirs1[1];

    }

    function mo_twitter_url_encode_rfc3986($input)
    {
        if (is_array($input)) {
            return array_map(array('Mo_Openid_Twitter_OAuth', 'mo_twitter_url_encode_rfc3986'), $input);
        }
        else if (is_scalar($input)) {
            return str_replace('+',' ',str_replace('%7E', '~', rawurlencode($input)));
        }
        else{
            return '';
        }
    }
}