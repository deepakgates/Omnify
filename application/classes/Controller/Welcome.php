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
        $client->setAuthConfig(__DIR__ . '/../../../client_secret.json');
        $client->setAccessType("offline");        // offline access
        $client->setIncludeGrantedScopes(true);   // incremental auth
        $client->addScope('openid profile email https://www.googleapis.com/auth/calendar');
        $client->setRedirectUri('http://localhost:8001/welcome/authenticateCallback');
        $auth_url = $client->createAuthUrl();
        self::redirect(filter_var($auth_url, FILTER_SANITIZE_URL));
    }

    public function action_authenticateCallback()
    {
        $client = new Google_Client();
        $client->setAuthConfig(__DIR__ . '/../../../client_secret.json');
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
        }

        $config = Kohana::$config->load('server');
        self::redirect(filter_var('http://'.$config->get('host').':'.$config->get('port').'/welcome/success/'. $userInfo['email'], FILTER_SANITIZE_URL));


    }
	public function action_success()
    {
        $email = $this->request->param('id');
        $user = ORM::factory('User')
            ->where('email', '=', $email)
            ->find();
        if (!$user->loaded()) {
            $config = Kohana::$config->load('server');
            self::redirect(filter_var('http://'.$config->get('host').':'.$config->get('port').'/welcome/index', FILTER_SANITIZE_URL));

        }

        $calendars = ORM::factory('Calendar')
            ->where('user_id', '=', $user->id)
            ->find_all();
        $view = View::factory('success');
        $view->calendars = $calendars->as_array();
        $this->response->body($view);


    }

	private function getUser()
	{

	}


} // End Welcome