<?php

$conf = require('config.php');

if(@$_GET['oauth'] == 'callback')
{
	header('Content-Type: text/plain; charset=utf-8');

	$code = @$_GET['code'];
	if(!$code)
		die("The code-paramete is required in callback.\nDid you deny access to the mighty Luckycat?\n\nStart again at $conf[baseurl], if you want.");



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

	$data = curl_exec($ch);
	$info = curl_getinfo($ch);

	$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if(200 != $httpcode)
		die("authorization_code-Request failed with httpcode $httpcode: $data;\n\nStart again at $conf[baseurl], if you want.");

	$data = json_decode($data, true);
	if(!$data)
		die("authorization_code-Request returned invalid json: $data\n\nStart again at $conf[baseurl], if you want.");



	/***** request channel-name *****/
	curl_reset($ch);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/youtube/v3/channels?'.http_build_query(array(
		'part' => 'id,brandingSettings',
		'mine' => 'true',
	)));
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Authorization: Bearer '.$data['access_token'],
	));

	$channel = curl_exec($ch);
	$info = curl_getinfo($ch);

	$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if(200 != $httpcode)
		die("Channels-Request failed with httpcode $httpcode: $channel;\n\nStart again at $conf[baseurl], if you want.");

	$channel = json_decode($channel, true);
	if(!$channel)
		die("channels-Request returned invalid json: $channel\n\nStart again at $conf[baseurl], if you want.");



	/***** save aquired information *****/
	$channel = reset($channel['items']);
	$data['channel'] = $channel['id'];
	$data['channelname'] = $channel['brandingSettings']['channel']['title'];

	$filename = preg_replace('/[^a-z0-9_\-]/i', '-', $data['channelname']);

	if(!file_put_contents($conf['storage'].'/'.$filename.'.json', json_encode($data, JSON_PRETTY_PRINT)))
		die("saving json to $conf[storage] failed");



	/***** send email to voc@c3voc.de *****/
	mail(
		$conf['email'],
		'YouTube-Accountdaten-Lieferung: '.$data['channelname'],
		'Der Kanalbesitzer von '.$data['channelname'].' hat seine YouTube-Accountdaten auf '.trim(shell_exec('hostname -f')).' eingeworfen. Ich habe sie in '.$conf['storage']." abgelegt."
	);





	/***** be nice and say "Thank you!" :) *****/
	echo "Thank you!\nThe mighty Luckycat will now publish wonderful cat-content to your channel '$data[channelname]'";
}
else
{
	header('Location: https://accounts.google.com/o/oauth2/auth?'.http_build_query(array(
		'client_id' => $conf['client_id'],
		'redirect_uri' => $conf['baseurl'].'?oauth=callback',
		'response_type' => 'code',
		'scope' => 'https://www.googleapis.com/auth/youtube.readonly https://www.googleapis.com/auth/youtube.upload',
		'access_type' => 'offline',
	)));
}
