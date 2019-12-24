<?php

class Telegram_model extends CI_Model
{
    /**
     * Creates an array containing the required body for telegram HTTP request
     */
    public function sendMessage($userData)
    {
        $this->senderAction($userData, "typing");
        $data = [
                    'chat_id'=>$userData['chat_id'],
                    'text'=> $userData['bot_response'],
                    'parse_mode'=>'HTML',
                    'reply_to_message_id'=>null,
                    'reply_markup'=>null
                ];
        return $this->telegram(array('type'=>'sendMessage', 'data'=>$data));
    }

    public function sendInlineKeyboard($userData){
        $this->senderAction($userData, "typing");
		$data_arr = array();
		$data_desc = array();
		foreach($userData['bot_data'] as $res_data){
			$data_desc[] = $res_data['title'];
			$data_arr[] = array("inline_message_id"=>null,"text"=>$res_data['title'],"callback_data"=>$res_data['payload']);
		}
		if(count($data_arr) > 3){
			if(max(array_map('strlen', $data_desc)) >= 15){
				$keyboard_data = array_chunk($data_arr, 1);
			}	
			else{
				$keyboard_data = array_chunk($data_arr, 3);
			}		
		}
		else{
			$keyboard_data = [$data_arr];
		}
		
		$keyboard= array("inline_keyboard"=>$keyboard_data);
		
		$data = [
			'chat_id'=>$userData['chat_id'],
			'text'=>$userData['bot_response'],
			'parse_mode'=> 'HTML',
			'reply_to_message_id'=>null,
			'reply_markup'=>json_encode($keyboard)
			];
		
        return $this->telegram(array('type'=>'sendMessage', 'data'=>$data));
	}
    /**
     * Sends the 'bot is typing' message
     */
    public function senderAction($userData, $sender_action)
    {
        $data = [
                'chat_id'=>$userData['chat_id'],
                'action'=>$sender_action
                ];
        return $this->telegram(array('type'=>'sendChatAction','data'=>$data));
    }

     /**
     * Sends response back to the user
     */
    public function telegram($data)
    {
        $token = getenv('TELEGRAM_ACCESS_TOKEN');
        $headers = array();
        $body = $data['data'];
        $url = "https://api.telegram.org/bot$token/".$data['type']."?".http_build_query($body);
        return $this->doCurl($url, $headers, '', '');
    }

     /**
     * Makes HTTP request
     */
    public function doCurl($url, $headers)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $res = curl_exec($ch);
        if (curl_error($ch)) {
            print_r(curl_error($ch));
        }
        curl_close($ch);
        return json_decode($res, true);
    }
}