<?php

class Channel
{
	private $url;
	private $user;
	private $pass;

	private $channels = array();

	function __construct( $channelHandlerUrl ) {
		$this->url = $channelHandlerUrl;
	}

	function setAuthentication( $user, $password ) {
		$this->user = $user;
		$this->pass = $password;
	}

	function push( $channelName, $data ) {
		$this->channels[] = array_merge( $data, array( 'channel_name' => $channelName ) );
	}

	function process() {
		$header = "Content-Type: application/x-www-form-urlencoded\r\n";
		if( $this->user && $this->pass ) {
			$encoded = base64_encode( $this->user . ':' . $this->pass );
			$header .= "Authorization: Basic $encoded\r\n";
		}

		$content = http_build_query( array( 'channels' => $this->channels ), '', '&' );

		$context = stream_context_create( array(
			'http' => array(
				'method' => 'POST',
				'header' => $header,
				'content' => $content,
				'timeout' => 10,
			),
		) );

		file_get_contents( $this->url, false, $context );
	}
}
