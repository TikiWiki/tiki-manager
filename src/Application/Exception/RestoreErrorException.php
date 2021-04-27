<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Application\Exception;

class RestoreErrorException extends \Exception
{
    const CREATEDIR_ERROR = 1;
    const DECOMPRESS_ERROR = 2;
    const MANIFEST_ERROR = 3;
    const COPY_ERROR = 4;
    const INVALID_PATHS = 5;
    const LOCK_ERROR = 6;
}
