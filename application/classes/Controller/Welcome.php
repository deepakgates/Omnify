<?php defined('SYSPATH') or die('No direct script access.');
require_once __DIR__ . '/../../../vendor/autoload.php';
class Controller_Welcome extends Controller {

	public function action_index()
	{
		$this->response->body(View::factory('login'));
	}

    public function action_authenticate()
    {
        $client = new Google_Client();
        $client->setAuthConfig(json_decode(getenv('client_secret'),true));
        $client->setAccessType("offline");        // offline access
        $client->setIncludeGrantedScopes(true);   // incremental auth
        $client->addScope('openid profile email https://www.googleapis.com/auth/calendar');
        $client->setRedirectUri(getenv('site_host').'/welcome/authenticateCallback');
        $auth_url = $client->createAuthUrl();
        self::redirect(filter_var($auth_url, FILTER_SANITIZE_URL));
    }

    public function action_authenticateCallback()
    {
        $client = new Google_Client();
        $client->setAuthConfig(json_decode(getenv('client_secret'),true));
        $client->setAccessType("offline");        // offline access
        $client->setIncludeGrantedScopes(true);   // incremental auth
        $client->addScope('openid profile email https://www.googleapis.com/auth/calendar');

       $code= $this->request->query('code');
        $client->authenticate($code);
        $access_token = $client->getAccessToken();
        $refresh_token=$client->getRefreshToken();

        $client->setAccessToken(json_encode($access_token));
        $oauth2 = new \Google_Service_Oauth2($client);
        $userInfo = $oauth2->userinfo->get();

        $user = ORM::factory('User')
            ->where('email', '=', $userInfo['email'])
            ->find();
        if ($user->loaded()) {
            if($refresh_token!=null) {
                $user->token = json_encode($access_token);
                $user->save();
            }
        } else {
            $user = new Model_User();
            $user->email = $userInfo['email'];
            $user->g_id = $userInfo['id'];
            $user->token = json_encode($access_token);
            $user->save();
        }

        if($refresh_token!=null){
            $service = new Google_Service_Calendar($client);
            $calendarId = 'primary';
            $optParams = array(
                'maxResults' => 100,
                'orderBy' => 'startTime',
                'singleEvents' => TRUE,
                'timeMin' => date('c'),
            );
            $results = $service->events->listEvents($calendarId, $optParams);

            foreach ($results['items'] as $item){
                $calendar = ORM::factory('Calendar')
                    ->where('g_id', '=', $item['id'])
                    ->find();
                if (!$calendar->loaded()) {
                    $calendar = new Model_Calendar();
                    $calendar->id = $item['email'];
                    $calendar->title = $item['summary'];
                    $calendar->g_id = $item['id'];
                    $calendar->user_id = $user->id;
                    $calendar->save();
                }

            }
            // adding channel

            $channel_db = new Model_Channel();
            $channel_db->user_id = $user->id;
            $channel_db->channel_id = uniqid();
            $channel_db->save();

            $channel = new Google_Service_Calendar_Channel($client);
            $channel->setId($channel_db->channel_id);
            $channel->setType('web_hook');
            $channel->setAddress(getenv('site_host').'/welcome/calendarWebhook');
            $watchEvent = $service->events->watch('primary', $channel, array());

        }



        self::redirect(filter_var(getenv('site_host').'/welcome/success/'. $userInfo['email'], FILTER_SANITIZE_URL));


    }
	public function action_success()
    {
        $email = $this->request->param('id');
        $user = ORM::factory('User')
            ->where('email', '=', $email)
            ->find();
        if (!$user->loaded()) {
            $config = Kohana::$config->load('server');
            self::redirect(filter_var(getenv('site_host').'/welcome/index', FILTER_SANITIZE_URL));

        }

        $calendars = ORM::factory('Calendar')
            ->where('user_id', '=', $user->id)
            ->find_all();
        $view = View::factory('success');
        $view->calendars = $calendars->as_array();
        $this->response->body($view);


    }

    public function action_calendarWebhook()
    {
        ini_set("log_errors", 1);
        error_log('callback response2 - '.json_encode($_SERVER));
        error_log('callback response3 - '.$_SERVER['HTTP_X_GOOG_CHANNEL_ID']);
     }



} // End Welcome
