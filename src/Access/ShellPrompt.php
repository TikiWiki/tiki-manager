<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Access;

interface ShellPrompt
{
    public function shellExec($command, $output = false);

    public function openShell($workingDir = '');

    public function chdir($location);

    public function setenv($var, $value);

    public function hasExecutable($name);

    public function createCommand($bin, $args = [], $stdin = '');

    public function runCommand($command, $options = []);
}
