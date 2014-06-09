<?php

class DT
{
	// 動画情報取得
	public static function getMovie($movieID)
	{
		global $memcache, $db;

		$key = "m_".$movieID."_dt".MEMCACHE_Prefix;

		if( ($movieDT = $memcache->get($key)) == false )
		{
			$movieDT = self::setMovie($movieID);
		}
		else
		{
			// データの再更新
			$memcache->set($key, $movieDT, MEMCACHE_Flg, MEMCACHE_Timeout);
			error_log( date("Y-m-d H:i:s ")." set:{$key} re\n", 3, LogPath.'mem.log');
		}

		return $movieDT;
	}

	// 動画情報設定
	public static function setMovie($movieID)
	{
		global $memcache, $db;

		$key = "m_".$movieID."_dt".MEMCACHE_Prefix;

		$sql = 'SELECT id, movieID, filename, created,likesCount,title,sURL FROM d_movie WHERE flg = 1 and movieID = "'.$db->escape($movieID).'"';
		$dtx = $db->get_results($sql);
		if( $db->num_rows == 1 )
		{
			$dt = $dtx[0];

			// コメント数取得
			$dtcc = $db->get_results('SELECT count(*) as cnt FROM d_comments WHERE movieID = "'.$db->escape($movieID).'" and flg = 1');
			$commentCount = $dtcc[0]->cnt;

			// コメント読み込み(最大5個)
			$sql = 'SELECT id,commID,text FROM d_comments WHERE movieID = "'.$db->escape($movieID).'" and flg = 1 ORDER BY created desc LIMIT 5';
			$dtcc = $db->get_results($sql);
			$comm = array();
			foreach( $dtcc as $dtc)
			{
				$userDT = self::getUser($dtc->id);

				$comm[] = array(
								"id"			=> intval($dtc->id),
								"screen_name"	=> $userDT['uname'],
								"avatar_url"	=> $userDT['avatarUrl'],
								"description"	=> $userDT['description'],
								"comments_id"	=> intval($dtc->commID),
								"text"			=> $dtc->text,
								);
			}

			$userDT = self::getUser($dt->id);

			// タグ情報の読み込み
			$dtxs = $db->get_results('SELECT tag, d_tags.tagID FROM d_tagsLink, d_tags where d_tagsLink.movieID = '.intval($movieID).' and d_tagsLink.tagID = d_tags.tagID group by d_tagsLink.tagID');
			$tags = array();
			foreach( $dtxs as $dtt )
				$tags[] = array( "tag" => $dtt->tag, "tag_id" => intval($dtt->tagID));

			$tag_list = array( "count" => count($tags), "data" => $tags);

			$movieDT = array(
							'title'			=> $dt-> title,
							'author'		=> array(
													"id"			=> intval($dt->id),
													"screen_name"	=> $userDT['uname'],
													"avatar_url"	=> $userDT['avatarUrl'],
													"description"	=> $userDT['description'],
													),
							'movie_url'		=> movieURL. $dt->filename. ".mp4",
							'thumbnail_url'	=> thumbnailURL. $dt->filename. ".jpg",
							'url'			=> URL. $dt->sURL,
							'movie_id'		=> intval($dt->movieID),
							'comments'		=> array(
													"count" => intval($commentCount),
													"data" => $comm,
													),
							'tags'			=> $tag_list,
							'likes_count'	=> intval($dt->likesCount),
							'liked'			=> Lib::is_like($dt->id, $dt->movieID),
							'created'		=> strtotime($dt->created),
						);

			$memcache->set($key, $movieDT, MEMCACHE_Flg, MEMCACHE_Timeout);
			error_log( date("Y-m-d H:i:s ")." set:{$key}\n", 3, LogPath.'mem.log');
		}
		else
		{
			$memcache->delete($key);
			$movieDT = false;
		}

		return $movieDT;
	}


	public static function getUser($id)
	{
		global $memcache;

		$key = "u_".$id."_dt".MEMCACHE_Prefix;

		if( ($userDT = $memcache->get($key)) == false )
		{
			$userDT = self::setUser($id);
		}
		else
		{
			// データの再更新
			$memcache->set($key, $userDT, MEMCACHE_Flg, MEMCACHE_Timeout);
			error_log( date("Y-m-d H:i:s ")." set:{$key} re\n", 3, LogPath.'mem.log');
		}

		return $userDT;
	}

	public static function setUser($id)
	{
		global $memcache, $db;

		$key = "u_".$id."_dt".MEMCACHE_Prefix;

		$sql = 'SELECT id,uname,avatarUrl,description FROM userList WHERE id = "'.$db->escape($id).'" ';
		$dtx = $db->get_results($sql);
		if( $db->num_rows == 1 )
		{
			$sql = 'SELECT movieID FROM d_likes WHERE id = "'.$db->escape($id).'" and flg = 1';
			$dtcc = $db->get_results($sql);

			$likeList = array();
			foreach( $dtcc as $dtc )
				$likeList[] = $dtc->movieID;

			$dt = $dtx[0];
			$userDT = array(
							"id"			=> $dt->id,
							"uname"			=> $dt->uname,
							"avatarUrl"		=> $dt->avatarUrl,
							"description"	=> $dt->description,
							"likeList"		=> $likeList,
							);

			$memcache->set($key, $userDT, MEMCACHE_Flg, MEMCACHE_Timeout);
			error_log( date("Y-m-d H:i:s ")." set:{$key}\n", 3, LogPath.'mem.log');

			self::serchMovieUserMemDelete($id);
		}
		else
		{
			$memcache->delete($key);
			$userDT = false;
		}
		return $userDT;
	}

	private static function serchMovieUserMemDelete($id)
	{
		global $memcache, $db;

		// d_movie no data delete
		$sql = 'SELECT movieID FROM d_movie WHERE id = "'.$db->escape($id).'"';
		$dtx = $db->get_results($sql);
		foreach( $dtx as $dt )
		{
			$key = "m_".$dt->movieID."_dt".MEMCACHE_Prefix;
			if($memcache->delete($key))
				error_log( date("Y-m-d H:i:s ")." delete:{$key}\n", 3, LogPath.'mem.log');
		}

		// d_comments no data delete
		$sql = 'SELECT movieID FROM d_comments WHERE id = "'.$db->escape($id).'"';
		$dtx = $db->get_results($sql);
		foreach( $dtx as $dt )
		{
			$key = "m_".$dt->movieID."_dt".MEMCACHE_Prefix;
			if($memcache->delete($key))
				error_log( date("Y-m-d H:i:s ")." delete:{$key}\n", 3, LogPath.'mem.log');
		}
	}


}
