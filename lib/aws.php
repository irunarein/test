<?php

class awsLib
{
	public	$deviceToken;
	public $devTokenList = array(
							"CDFBFB395797F396C6A950B56D1A5370FF5F8E3D6A2E8BFC0E1150D2DA44092A",		// ishihama
							"9568193A34D531152C08596E03C8970954AC0B3CCAFE843BE750A3AEC8891878",		// alice
									);

	function __construct()
	{
		require_once ('autoload.php');
	}

	function setEndpoint($deviceToken)
	{
		try
		{
			$sns = Aws\Sns\SnsClient::factory(array(
												'key'    => AWSAccessKeyId,
												'secret' => AWSSecretKey,
												'region' => Aws\Common\Enum\Region::TOKYO
											));

			$options = array(
				'PlatformApplicationArn' => in_array($deviceToken, $this->devTokenList) ? PlatformApplicationArnSANDBOX : PlatformApplicationArn,
				'Token'                  => $deviceToken,
				'CustomUserData'		 => "peets",
			);
			error_log(date("Y-m-d H:i:s ")." options:".print_r($options,true)."\n", 3, LogPath.'dbg.log');

			$endpointArn = $sns->createPlatformEndpoint($options);

			return $endpointArn['EndpointArn'];
		}
		catch (Exception $e)
		{
			error_log(date("Y-m-d H:i:s ")." AWS error:".$e->getMessage()."\n", 3, LogPath.'dbg.log');
		}

		return "";
	}

	function pushEndPointArn($deviceToken, $endpointArn, $message)
	{
		try
		{
			$sns = Aws\Sns\SnsClient::factory(array(
												'key'    => AWSAccessKeyId,
												'secret' => AWSSecretKey,
												'region' => Aws\Common\Enum\Region::TOKYO
											));

//									'sound' => 'default'

			$apns = array('aps' =>
								array(
									'alert' => $message,
									'badge' => 1,
									'sound' => 'notif.caf'
									),
							'content-available' => 1,
						);

			$apns_type = in_array($deviceToken, $this->devTokenList) ? "APNS_SANDBOX" : "APNS";

			$push_parameter = array(
				'MessageStructure' => 'json',
				'Message' => json_encode(array(
							'default' => 'peets Message',
							$apns_type => json_encode($apns, JSON_UNESCAPED_UNICODE),
						), JSON_UNESCAPED_UNICODE),
				'TargetArn' => $endpointArn,
				); 
			$sts = $sns->publish($push_parameter); 
		}

		catch (Exception $e)
		{
			error_log(date("Y-m-d H:i:s ")." AWS Send NG:".$e->getMessage()."\n", 3, LogPath.'dbg.log');
			$sts = false;
		}

		return $sts;
	}


}
