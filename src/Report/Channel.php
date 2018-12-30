<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Report;

class Channel
{
    private $url;
    private $user;
    private $pass;

    private $channels = [];

    public function __construct($channelHandlerUrl)
    {
        $this->url = $channelHandlerUrl;
    }

    public function setAuthentication($user, $password)
    {
        $this->user = $user;
        $this->pass = $password;
    }

    public function push($channelName, $data)
    {
        $this->channels[] = array_merge($data, ['channel_name' => $channelName]);
    }

    public function process()
    {
        $header = "Content-Type: application/x-www-form-urlencoded\r\n";
        if ($this->user && $this->pass) {
            $encoded = base64_encode($this->user . ':' . $this->pass);
            $header .= "Authorization: Basic $encoded\r\n";
        }

        $content = http_build_query(['channels' => $this->channels ], '', '&');

        $context = stream_context_create(
            [
                'http' => [
                    'method' => 'POST',
                    'header' => $header,
                    'content' => $content,
                    'timeout' => 10,
                ],
            ]
        );

        file_get_contents($this->url, false, $context);
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
