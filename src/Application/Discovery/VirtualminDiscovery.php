<?php


namespace TikiManager\Application\Discovery;

class VirtualminDiscovery extends LinuxDiscovery
{

    protected function detectWebrootOS()
    {
        $user = $this->detectUser();
        if ($user === "root") {
            return parent::detectWebrootOS();
        }
        $domain = parse_url($this->instance->weburl)['host'];

        $folders = [
            [
                'base' => '/home/' . $user . '/domains/' . $domain,
                'target' => '/home/' . $user . '/domains/' . $domain . '/public_html',
                'tmp' => '/home/' . $user . '/domains/' . $domain . '/tmp',
            ],
            [
                'base' => '/home/' . $user,
                'target' => '/home/' . $user . '/public_html',
                'tmp' => '/home/' . $user . '/tmp',
            ]
        ];

        return array_merge($folders, parent::detectWebrootOS());
    }

    public function isAvailable()
    {
        return $this->access->fileExists('/usr/sbin/virtualmin');
    }

    public function detectDistro()
    {
        return parent::detectDistro() . ' with Virtualmin';
    }
}
