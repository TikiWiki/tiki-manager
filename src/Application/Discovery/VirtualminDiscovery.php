<?php


namespace TikiManager\Application\Discovery;

class VirtualminDiscovery extends LinuxDiscovery
{
    protected function detectPHPOS()
    {
        $webroot = $this->getConf('webroot') ?: $this->detectWebroot();

        $searchOrder = [
            ['command', ['-v', 'php']],
            ['locate', ['-e', '-r', 'bin/php[1-9\.]*$']],
            ['find', ['/usr/bin', '-name', 'php[1-9\.]*']],
        ];

        if ($this->access->fileExists($webroot . '/../bin/php')) {
            $command = $this->access->createCommand('realpath', [$webroot . '/../bin/php']);
            $command->run();
            if ($command->getReturn() === 0) {
                $path = $command->getStdoutContent();
                $searchOrder = [
                    ['command', ['-v', $path]],
                ];
            }
        }

        return $this->detectPHPLinux([], $searchOrder);
    }

    protected function detectWebrootOS()
    {
        $user = $this->detectUser();
        $domain = parse_url($this->instance->weburl)['host'];

        if (! strstr($domain, $user) || $user === 'root') {
            // try subdomain parts in the Virtualmin directory structure:
            // /home/main-domain-user/domains/subdomain
            $parts = explode('.', $domain);
            // tld not relevant
            array_pop($parts);
            $base = null;
            $subs = [];
            foreach ($parts as $subdomain) {
                if ($this->access->fileExists('/home/' . $subdomain)) {
                    $base = '/home/' . $subdomain;
                    break;
                }
                $subs[] = $subdomain;
            }
            if ($base && $subs) {
                foreach (array_reverse($subs) as $subdomain) {
                    $newBase = $base . '/domains/' . $subdomain;
                    if (! $this->access->fileExists($newBase)) {
                        $newBase = $base . '/domains/' . $domain;
                        if (! $this->access->fileExists($newBase)) {
                            continue;
                        }
                    }
                    $base = $newBase;
                }
            }
            if ($base && $this->access->fileExists($base)) {
                return [[
                    'base' => $base,
                    'target' => $base . '/public_html',
                    'tmp' => $base . '/tmp',
                ]];
            }
        }

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
