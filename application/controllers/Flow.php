<?php
Predis\Autoloader::register();
use phpseclib\Net\SSH2;

defined('BASEPATH') or exit('No direct script access allowed');

class Flow extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Telegram_model');
    }

    /**
     * Telegram webhook method.
     */
    public function webhook()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $userData = array();
        $userData['chat_id'] = $input['message']['chat']['id'] ?? $input['callback_query']['message']['chat']['id'];
        $userData['message'] = $input['message']['text'] ?? $input['callback_query']['data'];
        $userData['message_id'] = $input['message']['message_id'] ?? $input['callback_query']['message']['message_id'];
        $userData['first_name'] = $input['message']['from']['first_name'] ?? $input['callback_query']['from']['first_name'];
        $userData['last_name'] = $input['message']['from']['last_name'] ?? $input['callback_query']['from']['last_name'];
        $userData['username'] = $input['message']['from']['username'] ?? $input['callback_query']['from']['username'];
        $userData['payload'] = $input['callback_query']['data'] ?? null;
        $userData['callback_id'] = $input['callback_query']['id'] ?? null;
        $userData['connection'] = $this->redis('get', 'ssh-'+$userData['chat_id']) ?? null;
        $userData['command'] = $userData['message'] ?? null;
        $this->start($userData);
    }
    
    /**
     * Handles the process flow
     */
    public function start($userData)
    {
        $menu = array(
            array( 'title'=>'ssh', 'payload'=>'ssh'),
            array( 'title'=>'cmd', 'payload'=>'cmd'),
            array( 'title'=>'reconnect', 'payload'=>'reconnect'),
            array( 'title'=>'disconnect', 'payload'=>'disconnect'),
            array( 'title'=>'help', 'payload'=>'help'),
        );
        if (strpos($userData['message'], '/start') !== false || strpos($userData['message'], 'help') !== false) {
            // if a user sends '/start', the default message is sent as a response
            $userData['bot_response'] = "*Hi there*, `".$userData['first_name']."`. Welcome to SSH!\n\nThis Simple BOT will allow you to establish a SSH connection. We currently support only password connection.\n\nAvailable Commands:";
            $userData['bot_data'] = $menu;
            $this->Telegram_model->sendInlineKeyboard($userData);
        } elseif (strpos($userData['message'], 'ssh') !== false) {
            $userData['connection'] = $userData['message'];
            if (strtolower($userData['message']) == 'ssh') {
                $this->ssh_default_response($userData);
            } else {
                $ssh = $this->login($userData);
                if (isset($ssh) && $ssh !== null) {
                    $host = $this->split_data($userData['connection'], 'host')[1];
                    $userData['bot_response'] = "You are now connected to $host";
                } else {
                    $userData['bot_response'] = "Login Failed";
                }
                $userData['bot_data'] = null;
                $this->Telegram_model->sendMessage($userData);
            }
        } elseif (strpos($userData['message'], 'cmd') !== false) {
            $userData['connection'] = $this->redis('get', 'ssh-'+$userData['chat_id']);
            $userData['command'] = $userData['message'];
            if (strtolower($userData['message']) == 'cmd') {
                return $this->cmd_default_response($userData);
            } else {
                return $this->cmd($userData);
            }
        } elseif (strpos($userData['message'], 'reconnect') !== false) {
            $userData['connection'] = $this->redis('get', 'ssh-'+$userData['chat_id']);
            $ssh = $this->login($userData);
            if (isset($ssh) && $ssh !== null) {
                $host = $this->split_data($userData['connection'], 'host')[1];
                $userData['bot_response'] = "You are now connected to $host";
            } else {
                $userData['bot_response'] = "Connection lost".$userData['connection'];
            }
            $userData['bot_data'] = null;
            $this->Telegram_model->sendMessage($userData);
        } elseif (strpos($userData['message'], 'disconnect') !== false) {
            $this->redis('set', 'ssh-'+$userData['chat_id'], '');
            $userData['bot_response'] = "You are now disconnected. Hope to see you again.";
            $userData['bot_data'] = null;
            $this->Telegram_model->sendMessage($userData);
        } else {
            $userData['bot_response'] = "Please type 'help' to view the available commands";
            $userData['bot_data'] = $menu;
            $this->Telegram_model->sendInlineKeyboard($userData);
        }
    }
	
	/**
     * SSH default message
     */
    public function ssh_default_response($userData)
    {
        $userData['bot_response'] = "Please send your message in this format to authenticate ssh: `ssh --host=<VALUE> --user=<VALUE> --password=<VALUE>`";
        $userData['bot_data'] = null;
        $this->Telegram_model->sendMessage($userData);
    }

    /**
     * CMD default message
     */
    public function cmd_default_response($userData)
    {
        $userData['bot_response'] = "cmd <COMMAND>. Example: cmd pwd";
        $userData['bot_data'] = null;
        $this->Telegram_model->sendMessage($userData);
	}
	
    /**
     * Splits the SSH connection string
     */
    public function split_data($data, $type)
    {
        $ssh = explode(" ", $data);
        if ($type == strtolower($ssh[0])) {
            return true;
        } elseif ($type == 'host') {
            return explode("=", $ssh[1]);
        } elseif ($type == 'user') {
            return explode("=", $ssh[2]);
        } elseif ($type == 'password') {
            return explode("=", $ssh[3]);
        } else {
            return null;
        }
    }

    /**
     * Authenticates the ssh username, password and hostname
     */
    public function login($userData)
    {
        $isSsh = $this->split_data($userData['connection'], 'ssh');
        if ($isSsh == true) {
            $host = $this->split_data($userData['connection'], 'host');
            $user = $this->split_data($userData['connection'], 'user');
            $password = $this->split_data($userData['connection'], 'password');
            $host_value = ($host[0] == '--host') ? $host[1] : null;
            $user_value = ($user[0] == '--user') ? $user[1] : null;
            $password_value = ($password[0] == '--password') ? $password[1] : null;
            if ($host_value == null || $user_value == null || $password_value == null) {
                if ($host_value == null) {
                    $userData['bot_response'] = 'Invalid Host';
                } elseif ($user_value == null) {
                    $userData['bot_response'] = 'Invalid User';
                } else {
                    $userData['bot_response'] = 'Invalid Password';
                }
                $userData['bot_data'] = null;
                $this->Telegram_model->sendMessage($userData);
                return null;
            }
            $ssh = new SSH2($host_value);
            if (!$ssh->login($user_value, $password_value)) {
                $userData['bot_response'] = 'Login Failed';
                $userData['bot_data'] = null;
                $this->Telegram_model->sendMessage($userData);
                return null;
            } else {
                $this->redis('set', 'ssh-'+$userData['chat_id'], $userData['connection']);
                return $ssh;
            }
        } else {
            return $this->ssh_default_response($userData);
        }
    }
    
    /**
     * Executes commands in the ssh server
     */
    public function cmd($userData)
    {
        $ssh = $this->login($userData);
        if (isset($ssh) && $ssh !== null) {
            $cmd = explode(" ", $userData['command']);
            if ($cmd[0] == 'cmd') {
                $exec = str_replace('cmd ', '', $userData['command']);
                $userData['bot_response'] = substr($ssh->exec($exec), 0, 4096);
                $userData['bot_data'] = null;
                return $this->Telegram_model->sendMessage($userData);
            } else {
                $userData['bot_response'] = 'Could not execute command';
                $userData['bot_data'] = null;
                $this->Telegram_model->sendMessage($userData);
                return $this->cmd_default_response($userData);
            }
        } else {
            $userData['bot_response'] = 'Authentication Failed';
            $userData['bot_data'] = null;
            return $this->Telegram_model->sendMessage($userData);
        }
    }

    /**
     * Handles storing and fetching data from the redis store
     */
    public function redis($op="", $key="", $value="")
    {
        $HOST = getenv('REDIS_HOST');
        $PORT = getenv('REDIS_PORT');
        $client = new Predis\Client("tcp://$HOST:$PORT");
        if ($op == 'set') {
            $client->set($key, $value);
            $client->persist($key);
        } elseif ($op == 'get') {
            return $client->get($key);
        }
    }

    
}
