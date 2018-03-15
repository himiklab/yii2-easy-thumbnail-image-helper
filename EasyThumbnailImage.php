<?php
/**
 * @link https://github.com/himiklab/yii2-easy-thumbnail-image-helper
 * @copyright Copyright (c) 2014-2017 HimikLab
 * @license http://opensource.org/licenses/MIT MIT
 */

namespace himiklab\thumbnail;

use Imagine\Image\Box;
use Imagine\Image\ManipulatorInterface;
use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;
use yii\helpers\Html;
use yii\imagine\Image;

/**
 * Yii2 helper for creating and caching thumbnails on real time
 * @author HimikLab
 * @package himiklab\thumbnail
 */
class EasyThumbnailImage
{
    const THUMBNAIL_OUTBOUND = ManipulatorInterface::THUMBNAIL_OUTBOUND;
    const THUMBNAIL_INSET = ManipulatorInterface::THUMBNAIL_INSET;
    const QUALITY = 50;
    const MKDIR_MODE = 0755;

    const CHECK_REM_MODE_NONE = 1;
    const CHECK_REM_MODE_CRC = 2;
    const CHECK_REM_MODE_HEADER = 3;

    /** @var string $cacheAlias path alias relative with @web where the cache files are kept */
    public static $cacheAlias = 'assets/thumbnails';

    /** @var int $cacheExpire */
    public static $cacheExpire = 0;

    public static $grabberType = EasyThumbnail::GRABBER_PHP;

    /**
     * Creates and caches the image thumbnail and returns ImageInterface.
     *
     * @param string $filename the image file path or path alias or URL
     * @param integer $width the width in pixels to create the thumbnail
     * @param integer $height the height in pixels to create the thumbnail
     * @param string $mode self::THUMBNAIL_INSET, the original image
     * @param integer $quality
     * @param integer $checkRemFileMode
     * is scaled down so it is fully contained within the thumbnail dimensions.
     * The specified $width and $height (supplied via $size) will be considered
     * maximum limits. Unless the given dimensions are equal to the original image’s
     * aspect ratio, one dimension in the resulting thumbnail will be smaller than
     * the given limit. If self::THUMBNAIL_OUTBOUND mode is chosen, then
     * the thumbnail is scaled so that its smallest side equals the length of the
     * corresponding side in the original image. Any excess outside of the scaled
     * thumbnail’s area will be cropped, and the returned thumbnail will have
     * the exact $width and $height specified
     * @throws \Imagine\Exception\RuntimeException
     * @throws \Imagine\Exception\InvalidArgumentException
     * @throws FileNotFoundException
     * @return \Imagine\Image\ImageInterface
     * @throws \yii\base\InvalidParamException
     * @throws \yii\base\InvalidConfigException
     */
    public static function thumbnail($filename, $width, $height, $mode = self::THUMBNAIL_OUTBOUND, $quality = null,
                                     $checkRemFileMode = self::CHECK_REM_MODE_NONE)
    {
        return Image::getImagine()
            ->open(static::thumbnailFile($filename, $width, $height, $mode, $quality, $checkRemFileMode));
    }

    /**
     * Creates and caches the image thumbnail and returns full path from thumbnail file.
     *
     * @param string $filename the image file path or path alias or URL
     * @param integer $width the width in pixels to create the thumbnail
     * @param integer $height the height in pixels to create the thumbnail
     * @param string $mode self::THUMBNAIL_INSET, the original image
     * @param integer $quality
     * @param integer $checkRemFileMode
     * @return string
     * @throws FileNotFoundException
     * @throws \Imagine\Exception\InvalidArgumentException
     * @throws \Imagine\Exception\RuntimeException
     * @throws \yii\base\InvalidParamException
     * @throws \yii\base\InvalidConfigException
     */
    public static function thumbnailFile($filename, $width, $height, $mode = self::THUMBNAIL_OUTBOUND, $quality = null,
                                         $checkRemFileMode = self::CHECK_REM_MODE_NONE)
    {
        $fileContent = null;
        $fileNameIsUrl = false;
        if (preg_match('/^https?:\/\//', $filename)) {
            $fileNameIsUrl = true;
            if ($checkRemFileMode === self::CHECK_REM_MODE_NONE) {
                $thumbnailFileName = md5($filename . $width . $height . $mode);
            } elseif ($checkRemFileMode === self::CHECK_REM_MODE_CRC) {
                $fileContent = static::fileFromUrlContent($filename);
                $thumbnailFileName = md5($filename . $width . $height . $mode . crc32($fileContent));
            } elseif ($checkRemFileMode === self::CHECK_REM_MODE_HEADER) {
                $thumbnailFileName = md5($filename . $width . $height . $mode . static::fileFromUrlDate($filename));
            } else {
                throw new InvalidConfigException();
            }
        } else {
            $filename = FileHelper::normalizePath(Yii::getAlias($filename));
            if (!is_file($filename)) {
                throw new FileNotFoundException("File {$filename} doesn't exist");
            }
            $thumbnailFileName = md5($filename . $width . $height . $mode . filemtime($filename));
        }
        $cachePath = Yii::getAlias('@webroot/' . static::$cacheAlias);

        $thumbnailFileExt = strrchr($filename, '.');
        $thumbnailFilePath = $cachePath . DIRECTORY_SEPARATOR . substr($thumbnailFileName, 0, 2);
        $thumbnailFile = $thumbnailFilePath . DIRECTORY_SEPARATOR . $thumbnailFileName . $thumbnailFileExt;

        if (file_exists($thumbnailFile)) {
            if (static::$cacheExpire !== 0 && (time() - filemtime($thumbnailFile)) > static::$cacheExpire) {
                unlink($thumbnailFile);
            } else {
                return $thumbnailFile;
            }
        }
        if (!is_dir($thumbnailFilePath)) {
            mkdir($thumbnailFilePath, self::MKDIR_MODE, true);
        }

        $box = new Box($width, $height);
        if ($fileNameIsUrl) {
            $image = Image::getImagine()->load($fileContent ?: static::fileFromUrlContent($filename));
        } else {
            $image = Image::getImagine()->open($filename);
        }
        $image = $image->thumbnail($box, $mode);

        $options = [
            'quality' => $quality === null ? self::QUALITY : $quality
        ];

        $image->save($thumbnailFile, $options);
        return $thumbnailFile;
    }

    /**
     * Creates and caches the image thumbnail and returns URL from thumbnail file.
     *
     * @param string $filename the image file path or path alias or URL
     * @param integer $width the width in pixels to create the thumbnail
     * @param integer $height the height in pixels to create the thumbnail
     * @param string $mode self::THUMBNAIL_INSET, the original image
     * @param integer $quality
     * @param integer $checkRemFileMode
     * @return string
     * @throws FileNotFoundException
     * @throws \Imagine\Exception\InvalidArgumentException
     * @throws \Imagine\Exception\RuntimeException
     * @throws \yii\base\InvalidParamException
     * @throws \yii\base\InvalidConfigException
     */
    public static function thumbnailFileUrl($filename, $width, $height, $mode = self::THUMBNAIL_OUTBOUND, $quality = null,
                                            $checkRemFileMode = self::CHECK_REM_MODE_NONE)
    {
        $cacheUrl = Yii::getAlias('@web/' . static::$cacheAlias);
        $thumbnailFilePath = static::thumbnailFile($filename, $width, $height, $mode, $quality, $checkRemFileMode);

        preg_match('#[^\\' . DIRECTORY_SEPARATOR . ']+$#', $thumbnailFilePath, $matches);
        $fileName = $matches[0];

        return $cacheUrl . '/' . substr($fileName, 0, 2) . '/' . $fileName;
    }

    /**
     * Creates and caches the image thumbnail and returns <img> tag.
     *
     * @param string $filename the image file path or path alias or URL
     * @param integer $width the width in pixels to create the thumbnail
     * @param integer $height the height in pixels to create the thumbnail
     * @param string $mode self::THUMBNAIL_INSET, the original image
     * @param array $options options similarly with \yii\helpers\Html::img()
     * @param integer $quality
     * @param integer $checkRemFileMode
     * @return string
     * @throws FileNotFoundException
     * @throws \Imagine\Exception\InvalidArgumentException
     * @throws \Imagine\Exception\RuntimeException
     * @throws \yii\base\InvalidParamException
     * @throws \yii\base\InvalidConfigException
     */
    public static function thumbnailImg($filename, $width, $height, $mode = self::THUMBNAIL_OUTBOUND, $options = [], $quality = null,
                                        $checkRemFileMode = self::CHECK_REM_MODE_NONE)
    {
        try {
            $thumbnailFileUrl = static::thumbnailFileUrl($filename, $width, $height, $mode, $quality, $checkRemFileMode);
        } catch (\Exception $e) {
            return static::errorHandler($e, $filename);
        }

        return Html::img(
            $thumbnailFileUrl,
            $options
        );
    }

    /**
     * Clear cache directory.
     *
     * @throws \yii\base\InvalidParamException
     * @return bool
     */
    public static function clearCache()
    {
        $cacheDir = Yii::getAlias('@webroot/' . static::$cacheAlias);
        static::removeDir($cacheDir);
        return @mkdir($cacheDir, self::MKDIR_MODE, true);
    }

    protected static function removeDir($path)
    {
        if (is_file($path)) {
            @unlink($path);
        } else {
            array_map('self::removeDir', glob($path . DIRECTORY_SEPARATOR . '*'));
            @rmdir($path);
        }
    }

    /**
     * @param \Exception $error
     * @param string $filename
     * @return string
     */
    protected static function errorHandler($error, $filename)
    {
        if ($error instanceof FileNotFoundException) {
            return $error->getMessage();
        }

        Yii::warning("{$error->getCode()}\n{$error->getMessage()}\n{$error->getFile()}");
        return 'Error ' . $error->getCode();
    }

    /**
     * @param string $url
     * @return string
     * @throws FileNotFoundException
     */
    protected static function fileFromUrlDate($url)
    {
        if (static::$grabberType === EasyThumbnail::GRABBER_PHP) {
            $streamContextDefaults = stream_context_get_options(stream_context_get_default());
            stream_context_set_default(['http' => ['method' => 'HEAD']]);
            if (($headers = @get_headers($url, 1)) === false || strpos($headers[0], '200') === false) {
                stream_context_set_default($streamContextDefaults);
                throw new FileNotFoundException("URL {$url} doesn't exist");
            }
            stream_context_set_default($streamContextDefaults);

            return isset($headers['Last-Modified']) ? $headers['Last-Modified'] : '';
        }

        // curl
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_FILETIME => true,
            CURLOPT_NOBODY => true,
        ]);
        if (curl_exec($curl) === false) {
            throw new FileNotFoundException("URL {$url} doesn't exist");
        }

        return curl_getinfo($curl, CURLINFO_FILETIME);
    }

    /**
     * @param string $url
     * @return string
     * @throws FileNotFoundException
     */
    protected static function fileFromUrlContent($url)
    {
        if (static::$grabberType === EasyThumbnail::GRABBER_PHP) {
            if (($result = @file_get_contents($url)) === false) {
                throw new FileNotFoundException("URL {$url} doesn't exist");
            }

            return $result;
        }

        // curl
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
        ]);
        if (!($result = curl_exec($curl))) {
            throw new FileNotFoundException("URL {$url} doesn't exist");
        }

        return $result;
    }
}
