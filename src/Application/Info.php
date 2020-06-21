<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Application;

use TikiManager\Config\Environment;

class Info
{
    const SQL_GET_VALUE = <<<SQL
SELECT i.value 
FROM info as i
WHERE i.name = :name;
SQL;

    const SQL_UPDATE_VALUE = <<<SQL
UPDATE info 
SET value = :value
WHERE name = :name;
SQL;

    /**
     * Get the value of a specific key
     * @param $key
     * @return string|null
     */
    public function get($key)
    {
        $result = query(self::SQL_GET_VALUE, [':name' => $key])->fetch();

        if (empty($result)) {
            return null;
        }

        return $result['value'];
    }

    /**
     * Update the value of a given key
     * @param $key
     * @param $value
     */
    public function update($key, $value)
    {
        query(self::SQL_UPDATE_VALUE, [':name' => $key, ':value' => $value]);
    }

    /**
     * Check if the login is locked based on previous login attempts amount
     * @return bool
     */
    public function isLoginLocked()
    {
        return $this->get('login_attempts') >= Environment::get('MAX_FAILED_LOGIN_ATTEMPTS', 10);
    }

    /**
     * Increment the login attempts
     * @return void
     */
    public function incrementLoginAttempts()
    {
        $attempts = self::get('login_attempts');
        $this->update('login_attempts', ++$attempts);
    }

    /**
     * Reset the login attempts to 0
     * @return void
     */
    public function resetLoginAttempts()
    {
        $this->update('login_attempts', 0);
    }
}
