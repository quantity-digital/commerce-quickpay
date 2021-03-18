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
    public static $logToCraft = true;

    /**
     * @var bool
     */
    public static $enableRotation = true;

    /**
     * @var int
     */
    public static $maxFileSize = 10240; // in KB

    /**
     * @var int
     */
    public static $maxLogFiles = 5;

    /**
     * @var bool
     */
    public static $rotateByCopy = true;

    /**
     * Logs an info message to a file with the provided handle.
     *
     * @param string $message
     */
    public static function info(string $message)
    {
        self::log($message, 'info');
    }

    /**
     * Logs an error message to a file with the provided handle.
     *
     * @param string $message
     */
    public static function error(string $message)
    {
        self::log($message, 'error');
    }

    /**
     * Logs the message to a file with the provided handle and level.
     *
     * @param string $message
     * @param string $level
     */
    public static function log(string $message, string $level = 'info')
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
        $debugToolbarEnabled = $user ? $user->getPreference('enableDebugToolbarForSite') : false;

        if (self::$logToCraft && !$debugToolbarEnabled) {
            // Convert level to a message level that the Yii logger might understand
            $level = self::MESSAGE_LEVELS[$level] ?? $level;

            Craft::getLogger()->log($message, $level, 'e-conomic');
        }
    }

    private static function rotateFiles($file)
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

    private static function clearLogFile($rotateFile)
    {
        if ($filePointer = @fopen($rotateFile, 'a')) {
            @ftruncate($filePointer, 0);
            @fclose($filePointer);
        }
    }

    private static function rotateByCopy($rotateFile, $newFile)
    {
        @copy($rotateFile, $newFile);
    }

    private static function rotateByRename($rotateFile, $newFile)
    {
        @rename($rotateFile, $newFile);
    }
}
