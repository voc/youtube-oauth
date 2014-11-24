<?php

require('PhpTemplate.php');

$conf = require('config.php');
$tpl = new PhpTemplate('template.phtml');
$tpl->set(array(
	'baseurl' => $conf['baseurl'],
));

header('Content-Type: text/html; charset=utf-8');

if(@$_GET['oauth'] == 'callback')
{
	$code = @$_GET['code'];
	if(!$code)
	{
		echo $tpl->render(array(
			'error' =>
				"The code-paramete is required in callback.\n".
				"Did you deny access to the mighty Luckycat?",
		));
		exit;
	}



	/***** exchange authorization_code to an auth-token *****/
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL, 'https://accounts.google.com/o/oauth2/token');
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
		'code' => $code,
		'client_id' => $conf['client_id'],
		'client_secret' => $conf['client_secret'],
		'redirect_uri' => $conf['baseurl'].'?oauth=callback',
		'grant_type' => 'authorization_code',
	)));

	$auth = curl_exec($ch);
	$info = curl_getinfo($ch);

	$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if(200 != $httpcode)
	{
		echo $tpl->render(array(
			'error' =>
				"authorization_code-Request failed with httpcode $httpcode: $auth",
		));
		exit;
	}

	$auth = json_decode($auth, true);
	if(!$auth)
	{
		echo $tpl->render(array(
			'error' =>
				"authorization_code-Request returned invalid json: $json",
		));
		exit;
	}

	curl_close($ch);



	/***** request channel-name *****/
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/youtube/v3/channels?'.http_build_query(array(
		'part' => 'id,brandingSettings',
		'mine' => 'true',
	)));
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Authorization: Bearer '.$auth['access_token'],
	));

	$channel = curl_exec($ch);
	$info = curl_getinfo($ch);

	$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if(200 != $httpcode)
	{
		echo $tpl->render(array(
			'error' =>
				"Channels-Request failed with httpcode $httpcode: $channel",
		));
		exit;
	}

	$channel = json_decode($channel, true);
	if(!$channel)
	{
		echo $tpl->render(array(
			'error' =>
				"channels-Request returned invalid json: $channel",
		));
		exit;
	}



	/***** save aquired information *****/
	$channel = reset($channel['items']);
	$channelname = $channel['brandingSettings']['channel']['title'];

	$filename = preg_replace('/[^a-z0-9_\-]/i', '-', $channelname);

	// write json
	if(!file_put_contents($conf['storage'].'/'.$filename.'.txt',
		"Set the following property on the Tracker-Project:\n".
		"Publishing.YouTube.Token = ".$auth['refresh_token']."\n"
	)) {
		echo $tpl->render(array(
			'error' =>
				"saving settings to $conf[storage] failed",
		));
		exit;
	}



	/***** send email to voc@c3voc.de *****/
	mail(
		$conf['email'],
		'YouTube-Accountdaten-Lieferung: '.$channelname,
		'Der Kanalbesitzer von '.$channelname.' hat seine YouTube-Accountdaten auf '.trim(shell_exec('hostname -f')).' eingeworfen. Ich habe sie in '.$conf['storage']." abgelegt."
	);





	/***** be nice and say "Thank you!" :) *****/
	echo $tpl->render(array(
		'success' => true,
		'channel' => $channelname,
	));
	exit;

	echo "Thank you!\nThe mighty Luckycat will now publish wonderful cat-content to your channel '$auth[channelname]'";
}
else
{
	echo $tpl->render(array(
		'goto' => 'https://accounts.google.com/o/oauth2/auth?'.http_build_query(array(
			'client_id' => $conf['client_id'],
			'redirect_uri' => $conf['baseurl'].'?oauth=callback',
			'response_type' => 'code',
			'scope' => 'https://www.googleapis.com/auth/youtube https://www.googleapis.com/auth/youtube.upload',
			'access_type' => 'offline',
			'approval_prompt' => 'force',
		))
	));
}
