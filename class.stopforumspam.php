<?php
/**
 * Akismet anti-comment spam service
 *	@package	stopforumspam
 *	@name		StopForumSpam
 *	@version	0.1
 *  @author		David Kobia
 *  @link		http://www.dkfactor.com
 */

class StopForumSpam {
	
	private $ip_address;
	private $email;
	
	public function __construct()
	{
		$this->ip_address = $_SERVER['REMOTE_ADDR'] != getenv('SERVER_ADDR') ? $_SERVER['REMOTE_ADDR'] : getenv('HTTP_X_FORWARDED_FOR');
	}
	
	public function setUserIP($userip)
	{
		$this->ip_address = $userip;
	}	
	
	public function setEmail($authorEmail)
	{
		$this->email = $authorEmail;
	}
	
	public function isCommentSpam()
	{
		// Test IP Address
		if ($this->_sendRequest("ip"))
		{
			return TRUE;
		}
		
		// Test Email
		if ($this->_sendRequest("email"))
		{
			return TRUE;
		}
	}
	
	private function _sendRequest($type = "ip")
	{
		$request_url = "";
		
		if ($type == "ip" AND $this->ip_address)
		{
			$request_url = "http://www.stopforumspam.com/api?ip=".$this->ip_address;
		}
		elseif ($type == "email" AND $this->email)
		{
			$request_url = "http://www.stopforumspam.com/api?email=".$this->email;
		}
		
		if ($request_url)
		{
			$curl_handle=curl_init();
			curl_setopt($curl_handle,CURLOPT_URL,$request_url);
			curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER,true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 4);
			curl_setopt($ch, CURLOPT_FAILONERROR,1);
			$xml_string = curl_exec($curl_handle);
			curl_close($curl_handle);
			$xml = new SimpleXMLElement($xml_string);
			if($xml->appears == "yes")
			{
				return TRUE;
			}
		}
		
		return FALSE;
	}
}