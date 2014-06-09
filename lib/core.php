<?php

class Lib
{
	const DEVELOPMENT = 'development';
	const PRODUCTION = 'production';
	const STAGING = 'staging';
	const TEST = 'test';

	public static function getEnv()
	{
		return isset($_SERVER['APP_ENV']) ? $_SERVER['APP_ENV'] : 'DEV';
	}
	public static function init($file)
	{
		$env = isset($_SERVER['APP_ENV']) ? $_SERVER['APP_ENV'] : 'DEV';
		switch($env)
		{
			case 'DEV' :
				$loadfile = DOCROOT.'config'. DIRECTORY_SEPARATOR. Lib::DEVELOPMENT. DIRECTORY_SEPARATOR. $file;
				break;
			case 'STG':
				$loadfile = DOCROOT.'config'. DIRECTORY_SEPARATOR. Lib::STAGING. DIRECTORY_SEPARATOR. $file;
				break;
			case 'PROD':
				$loadfile = DOCROOT.'config'. DIRECTORY_SEPARATOR. Lib::PRODUCTION. DIRECTORY_SEPARATOR. $file;
				break;
			case 'TEST':
				$loadfile = DOCROOT.'config'. DIRECTORY_SEPARATOR. Lib::TEST.DIRECTORY_SEPARATOR. $file;
				break;

			default:
				$loadfile = DOCROOT. 'config'. DIRECTORY_SEPARATOR. $file;
				break;
		}

		if(file_exists($loadfile))
			require $loadfile;
		else
			require (DOCROOT. 'config'. DIRECTORY_SEPARATOR. $file);
	}

	public static function print_v($v)
	{
		echo "<pre>";
		var_dump($v);
		echo "</pre>";
	}


	public static function getRandomString($nLengthRequired = 8)
	{
		$sCharList = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_';
		mt_srand();
		$sRes = '';
		for($i = 0; $i < $nLengthRequired; $i++)
			$sRes .= $sCharList{mt_rand(0, strlen($sCharList) - 1)};
		return $sRes;
	}

	public static function encode62($number)
	{
		$char = array_merge(range('0','9'), range('a', 'z'), range('A', 'Z'));

		$result = "";
		$base = count($char);

		while($number > 0)
		{
			$result = $char[ fmod($number, $base) ] . $result;
			$number = floor($number / $base);
		}
		return ($result == "" ) ? 0 : $result;
	}
 
	public static function decode62($str)
	{
		$char = array_merge(range('0','9'), range('a', 'z'), range('A', 'Z'));

		$result = 0;
		$base = count($char);
		$table = array_flip($char);
		$digit = array_reverse(preg_split('//', $str, -1, PREG_SPLIT_NO_EMPTY));

		foreach($digit as $i => $value)
		{
			if(!isset($table[$value]))
				return false;
			$result += pow($base, $i) * $table[$value];
		}

		return $result;
	}

	public static function createToken($id)
	{
		global $memcache;

		$token = sha1(substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, rand(1,20)));

		$memcache->set('token_'.$id.MEMCACHE_Prefix ,$token , 0, 360);
		
		return $token;
	}

	public static function chechParametersList($pr , $chklist = array() , $default = '' )
	{
		if( !isset($pr) || !in_array($pr, $chklist) )
			return $default;

		return $pr;
	}

	public static function movieID2ID($movieID)
	{
		global $db;

		$dtxs = $db->get_results('SELECT id FROM d_movie where movieID = '.intval($movieID).' and flg = 1');
		if(	$db->num_rows == 1 )
			return $dtxs[0]->id;

		return 0;
	}

	public static function getsURL()
	{
		global $db;

		for( $i = 0 ; $i < 100 ; $i++ )
		{
			$surl = Lib::encode62(rand(238500,916132831));

			if(	$db->query('SELECT id FROM d_movie where sURL = "'.$db->escape($surl).'"') == 0 )
				return $surl;
		}

		error_log( "getsURL no URL", 3, LogPath.'error.log');
		return false;
	}

	public static function IDcheck($id)
	{
		return (DT::getUser($id) === false) ? false : true;
	}

	public static function movieIDcheck($movieID)
	{
		global $db;

		$sql = 'SELECT id FROM d_movie where movieID = '.intval($movieID).' and flg = 1';
		return $db->query($sql) == 1;

	}

	public static function tag2tagID($tag)
	{
		global $db;

		$dtxs = $db->get_results('SELECT tagID FROM d_tags where tag = "'.$db->escape($tag).'"');
		if(	$db->num_rows == 1 )
			return $dtxs[0]->tagID;

		return 0;

	}

	public static function uname2id($uname)
	{
		global $db;

		$dtxs = $db->get_results('SELECT id FROM userList where uname = "'.$db->escape($uname).'"');
		if(	$db->num_rows == 1 )
			return $dtxs[0]->id;
		else
			return 0;
	}

	public static function getPOST($val, $def = '')
	{
		return isset($_POST[$val]) ? $_POST[$val] : $def;
	}

	public static function is_like($id = 0, $movieID = 0)
	{
		if( $id == 0 || $movieID == 0 )
			return false;

		$userDT = DT::getUser($id);

		// 自分がlikeしたかチェック
		return in_array($movieID, $userDT['likeList']);
	}

	public static function regActivityLog( $type, $fromID, $toID, $toMovieID, $likesID = 0, $commID = 0, $flg = 1)
	{
		global $db;

		// 自分自身のアクションは取らない
		if( $toID == $fromID )
			return true;

		$dt = array(
					"type"		=> $type,
					"fromID"	=> $fromID,
					"toID"		=> $toID,
					"toMovieID"	=> $toMovieID,
					"likesID"	=> $likesID,
					"commID"	=> $commID,
					"flg"		=> $flg,
					);

		$db->insert("d_activityLog", $dt);

		if( $flg == 1 )
		{
			switch($type)
			{
				case "likes":
					$msg = sprintf("%sさんが、投稿にいいねしました", Lib::getUname($fromID));
					break;
				case "comments":
					$msg = sprintf("%sさんが、投稿に「%s」とコメントしました", Lib::getUname($fromID), mb_strimwidth(Lib::getComments($commID), 0, 50, "…"));
					break;
				default:
					$msg = "不明なエラー！";
			}

			if( ($ret = Lib::getPushInfo($toID)) !== false )
			{
				$aws = new awsLib();
				foreach( $ret as $rt )
				{
					$aws->pushEndPointArn($rt->deviceToken, $rt->endpointArn, $msg);
					error_log(date("Y-m-d H:i:s")." AWS push msg:[{$msg}] to:{$toID} endpoint:[{$rt->endpointArn}] \n", 3, LogPath.'dbg.log');
				}
			}
		}

		return true;
	}

	public static function getPushInfo($id)
	{
		global $db;

		$sql = 'SELECT userList.id, deviceToken, endpointArn FROM userList left join d_device on d_device.id = userList.id  WHERE userList.id = '.intval($id).' and endpointArn != ""';
		$dtx = $db->get_results($sql);

		return $db->num_rows == 0 ? false : $dtx;
	}

	public static function getUname($id)
	{
		return (($userDT = DT::getUser($id)) === false) ? "名無し" : $userDT['uname'];
	}

	public static function getComments($commID)
	{
		global $db;

		$sql = 'SELECT text FROM d_comments WHERE commID = '.intval($commID);
		$dtx = $db->get_results($sql);
		return $db->num_rows == 1 ? $dtx[0]->text : "えら～";
	}

}
