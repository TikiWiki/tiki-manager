<?php

namespace TikiManager\Command\Helper;

use Symfony\Component\Console\Exception\InvalidOptionException;

class OptionValidatorHelper
{
    /**
     * Validate Email
     */
    public static function validateEmail(?string $value): string
    {
        if ($value && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidOptionException('Please insert a valid email address. Please use --email=<EMAIL>');
        }

        return $value;
    }

    /**
     * Validate Backup Permissions
     */
    public static function validateBackupPermissions($value): string
    {
        if (!$value || !is_numeric($value)) {
            throw new InvalidOptionException('Backup file permissions must be numeric. Please use --backup-permission=<PERM>');
        }

        return $value;
    }

    /**
     * Validate Backup Folder
     */
    public static function validateBackupUser($value, $discovery): string
    {
        if (! $discovery->userExists($value)) {
            throw new InvalidOptionException('Backup user does not exist on the local host.');
        }

        return $value;
    }

    /**
     * Validate Backup Group
     */
    public static function validateBackupGroup($value, $discovery): string
    {
        if (! $discovery->groupExists($value)) {
            throw new InvalidOptionException('Backup group does not exist on the local host.');
        }

        return $value;
    }


    /**
     * Validate URL
     */
    public static function validateWebUrl($value, $message = ''): string
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new InvalidOptionException($message ?: 'URL is invalid. Please use --url=<URL>');
        }
        return $value;
    }

    /**
     * Validate Name
     */
    public static function validateInstanceName(string $value): string
    {
        $value = trim($value);
        if (is_numeric($value)) {
            throw new InvalidOptionException('Name cannot be a numerical value (otherwise we can\'t differentiate from ID).');
        }

        if ($value === 'all') {
            throw new InvalidOptionException('Name cannot be "all" (which is a special keyword for instances listing).');
        }

        if (strpos($value, ',') !== false) {
            throw new InvalidOptionException('Name cannot contain ",".');
        }

        return self::validateInstanceNameUniqueness($value);
    }

    /**
     * @throws \Exception
     */
    public static function validatePath($path, $access)
    {
        $pathExists = $access->fileExists($path);
        if (! $pathExists) {
            $error = sprintf('Chosen directory (%s) does not exist.', $path);
            throw new \Exception($error);
        }
        return $path;
    }

    /**
     * @throws \Exception
     */
    public static function validatePathAndContent($path, $access)
    {
        self::validatePath($path, $access);
        if ($access->isEmptyDir($path)) {
            $error = sprintf('Chosen directory (%s) is empty.', $path);
            throw new \Exception($error);
        }
        return $path;
    }



    private static function validateInstanceNameUniqueness(string $name): string
    {
        global $db;
        $query = "SELECT COUNT(*) as numInstances FROM instance WHERE name = :name";
        $stmt = $db->prepare($query);
        $stmt->execute([':name' => $name]);
        $count = $stmt->fetchObject();

        if ($count->numInstances) {
            throw new InvalidOptionException('Instance name already in use. Please choose another name.');
        }

        return $name;
    }

    public static function validateNotEmpty($value, string $message): string
    {
        if (empty($value)) {
            throw new InvalidOptionException($message);
        }
        return $value;
    }
}
