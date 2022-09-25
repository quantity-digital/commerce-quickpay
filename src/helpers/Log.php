<?php

namespace QD\commerce\quickpay\helpers;

use Craft;
use craft\helpers\FileHelper;
use yii\base\ErrorException;
use yii\db\Exception;
use yii\log\Logger;

class Log
{
    /**
     * Message levels
     * @see https://www.yiiframework.com/doc/api/2.0/yii-log-logger#constants
     */
    const MESSAGE_LEVELS = [
        'error' => Logger::LEVEL_ERROR,
        'info' => Logger::LEVEL_INFO,
        'trace' => Logger::LEVEL_TRACE,
        'profile' => Logger::LEVEL_PROFILE,
        'profileBegin' => Logger::LEVEL_PROFILE_BEGIN,
        'profileEnd' => Logger::LEVEL_PROFILE_END,
    ];

    /**
     * @var bool
     */
    public static bool $logToCraft = true;

    /**
     * @var bool
     */
    public static bool $enableRotation = true;

    /**
     * @var int
     */
    public static int $maxFileSize = 10240; // in KB

    /**
     * @var int
     */
    public static int $maxLogFiles = 5;

    /**
     * @var bool
     */
    public static bool $rotateByCopy = true;

    /**
     * Logs an info message to a file with the provided handle.
     *
     * @param string $message
     * @return void
     */
    public static function info(string $message): void
    {
        self::log($message, 'info');
    }

    /**
     * Logs an error message to a file with the provided handle.
     *
     * @param string $message
     * @return void
     */
    public static function error(string $message): void
    {
        self::log($message, 'error');
    }

    /**
     * Logs the message to a file with the provided handle and level.
     *
     * @param string $message
     * @param string $level
     * @return void
     */
    public static function log(string $message, string $level = 'info'): void
    {
        // Clear stat cache to ensure getting the real current file size and not a cached one.
        // This may result in rotating twice when cached file size is used on subsequent calls.
        if (self::$enableRotation) {
            clearstatcache();
        }

        $file = Craft::getAlias('@storage/logs/quickpay.log');

        // Set IP address
        $ip = '';

        if (Craft::$app->getConfig()->getGeneral()->storeUserIps && !Craft::$app->getRequest()->isConsoleRequest) {
            $ip = Craft::$app->getRequest()->getUserIP();
        }

        // Set user ID
        $userId = '';
        $user = Craft::$app->getUser()->getIdentity();

        if ($user !== null) {
            $userId = $user->id;
        }

        // Trim message to remove whitespace and empty lines
        $message = trim($message);

        $log = date('Y-m-d H:i:s') . ' [' . $ip . '][' . $userId . '][' . $level . '] ' . $message . "\n";

        if (self::$enableRotation && @filesize($file) > self::$maxFileSize * 1024) {
            self::rotateFiles($file);
        }

        try {
            FileHelper::writeToFile($file, $log, ['append' => true]);
        } catch (ErrorException $e) {
            Craft::warning('Failed to write log to file `' . $file . '`.');
        }
        // Catch DB exceptions in case the DB cannot be queried for a mutex lock
        catch (Exception $e) {
            Craft::warning('Failed to write log to file `' . $file . '`.');
        }

        // Only log to Craft if debug toolbar is not enabled, otherwise this will break it
        // https://github.com/putyourlightson/craft-blitz/issues/233

        $user = Craft::$app->getUser()->getIdentity();
        //Not actually an error
        $debugToolbarEnabled = $user ? $user->getPreference('enableDebugToolbarForSite') : false;

        if (self::$logToCraft && !$debugToolbarEnabled) {
            // Convert level to a message level that the Yii logger might understand
            $level = self::MESSAGE_LEVELS[$level] ?? $level;

            Craft::getLogger()->log($message, $level, 'e-conomic');
        }
    }

    /**
     * Rotates the file
     *
     * @param string $file filepath of the file
     * @return void
     */
    private static function rotateFiles(string $file): void
    {
        for ($i = self::$maxLogFiles; $i >= 0; --$i) {
            // $i == 0 is the original log file
            $rotateFile = $file . ($i === 0 ? '' : '.' . $i);

            if (is_file($rotateFile)) {
                // Suppress errors because it's possible multiple processes enter into this section.
                if ($i === self::$maxLogFiles) {
                    @unlink($rotateFile);
                    continue;
                }

                $newFile = $file . '.' . ($i + 1);
                self::$rotateByCopy ? self::rotateByCopy($rotateFile, $newFile) : self::rotateByRename($rotateFile, $newFile);

                if ($i === 0) {
                    self::clearLogFile($rotateFile);
                }
            }
        }
    }

    /**
     * Clears the log file 
     *
     * @param string $rotateFile filename of the file to clear
     * @return void
     */
    private static function clearLogFile(string $rotateFile): void
    {
        if ($filePointer = @fopen($rotateFile, 'a')) {
            @ftruncate($filePointer, 0);
            @fclose($filePointer);
        }
    }

    /**
     * Copies content of rotateFile into the new file
     *
     * @param string $rotateFile file to copy from
     * @param string $newFile file to copy to
     * @return void
     */
    private static function rotateByCopy(string $rotateFile, string $newFile): void
    {
        @copy($rotateFile, $newFile);
    }

    /**
     * Renames the rotateFile to newFile
     *
     * @param string $rotateFile File to rename
     * @param string $newFile Name to give to the file
     * @return void
     */
    private static function rotateByRename(string $rotateFile, string $newFile): void
    {
        @rename($rotateFile, $newFile);
    }
}
