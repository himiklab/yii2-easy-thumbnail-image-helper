<?php
/**
 * @link https://github.com/himiklab/yii2-easy-thumbnail-image-helper
 * @copyright Copyright (c) 2014-2018 HimikLab
 * @license http://opensource.org/licenses/MIT MIT
 */

namespace himiklab\thumbnail;

use Imagine\Image\Box;
use Imagine\Image\ManipulatorInterface;
use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;
use yii\helpers\Html;
use yii\httpclient\Client as HttpClient;
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
    const THUMBNAIL_INSET_BOX = 'inset_box';
    const QUALITY = 50;
    const MKDIR_MODE = 0755;

    const CHECK_REM_MODE_NONE = 1;
    const CHECK_REM_MODE_CRC = 2;
    const CHECK_REM_MODE_HEADER = 3;

    /** @var string $cacheAlias path alias relative with @web where the cache files are kept */
    public static $cacheAlias = 'assets/thumbnails';

    /** @var int $cacheExpire */
    public static $cacheExpire = 0;

    /** @var yii\httpclient\Client */
    public static $httpClient;

    /**
     * Creates and caches the image thumbnail and returns `ImageInterface`.
     *
     * If one of thumbnail dimensions is set to `null`, another one is calculated automatically based on aspect ratio of
     * original image. Note that calculated thumbnail dimension may vary depending on the source image in this case.
     *
     * If both dimensions are specified, resulting thumbnail would be exactly the width and height specified. How it's
     * achieved depends on the mode.
     *
     * If `self::THUMBNAIL_OUTBOUND` mode is used, which is default, then the thumbnail is scaled so that
     * its smallest side equals the length of the corresponding side in the original image. Any excess outside of
     * the scaled thumbnail’s area will be cropped, and the returned thumbnail will have the exact width and height
     * specified.
     *
     * If thumbnail mode is `self::THUMBNAIL_INSET`, the original image is scaled down so it is fully
     * contained within the thumbnail dimensions. The rest is filled with background that could be configured via
     * [[Image::$thumbnailBackgroundColor]] or [[EasyThumbnail::$thumbnailBackgroundColor]],
     * and [[Image::$thumbnailBackgroundAlpha]] or [[EasyThumbnail::$thumbnailBackgroundAlpha]].
     *
     * If thumbnail mode is `self::THUMBNAIL_INSET_BOX`, the original image is scaled down so it is fully contained
     * within the thumbnail dimensions. The specified $width and $height (supplied via $size) will be considered
     * maximum limits. Unless the given dimensions are equal to the original image’s aspect ratio, one dimension in the
     * resulting thumbnail will be smaller than the given limit.
     *
     * @param string $filename the image file path or path alias or URL
     * @param integer $width the width in pixels to create the thumbnail
     * @param integer $height the height in pixels to create the thumbnail
     * @param string $mode mode of resizing original image to use in case both width and height specified
     * @param integer $quality
     * @param integer $checkRemFileMode check file version on remote server
     * @return \Imagine\Image\ImageInterface
     * @throws FileNotFoundException
     * @throws InvalidConfigException
     * @throws \yii\httpclient\Exception
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
     * If one of thumbnail dimensions is set to `null`, another one is calculated automatically based on aspect ratio of
     * original image. Note that calculated thumbnail dimension may vary depending on the source image in this case.
     *
     * If both dimensions are specified, resulting thumbnail would be exactly the width and height specified. How it's
     * achieved depends on the mode.
     *
     * If `self::THUMBNAIL_OUTBOUND` mode is used, which is default, then the thumbnail is scaled so that
     * its smallest side equals the length of the corresponding side in the original image. Any excess outside of
     * the scaled thumbnail’s area will be cropped, and the returned thumbnail will have the exact width and height
     * specified.
     *
     * If thumbnail mode is `self::THUMBNAIL_INSET`, the original image is scaled down so it is fully
     * contained within the thumbnail dimensions. The rest is filled with background that could be configured via
     * [[Image::$thumbnailBackgroundColor]] or [[EasyThumbnail::$thumbnailBackgroundColor]],
     * and [[Image::$thumbnailBackgroundAlpha]] or [[EasyThumbnail::$thumbnailBackgroundAlpha]].
     *
     * If thumbnail mode is `self::THUMBNAIL_INSET_BOX`, the original image is scaled down so it is fully contained
     * within the thumbnail dimensions. The specified $width and $height (supplied via $size) will be considered
     * maximum limits. Unless the given dimensions are equal to the original image’s aspect ratio, one dimension in the
     * resulting thumbnail will be smaller than the given limit.
     *
     * @param string $filename the image file path or path alias or URL
     * @param integer $width the width in pixels to create the thumbnail
     * @param integer $height the height in pixels to create the thumbnail
     * @param string $mode mode of resizing original image to use in case both width and height specified
     * @param integer $quality
     * @param integer $checkRemFileMode check file version on remote server
     * @return string
     * @throws FileNotFoundException
     * @throws InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public static function thumbnailFile($filename, $width, $height, $mode = self::THUMBNAIL_OUTBOUND, $quality = null,
                                         $checkRemFileMode = self::CHECK_REM_MODE_NONE)
    {
        $fileContent = null;
        $fileNameIsUrl = false;
        if (\preg_match('/^https?:\/\//', $filename)) {
            $fileNameIsUrl = true;
            switch ($checkRemFileMode) {
                case self::CHECK_REM_MODE_NONE:
                    $thumbnailFileName = \md5($filename . $width . $height . $mode);
                    break;
                case self::CHECK_REM_MODE_CRC:
                    $fileContent = static::fileFromUrlContent($filename);
                    $thumbnailFileName = \md5($filename . $width . $height . $mode . \crc32($fileContent));
                    break;
                case self::CHECK_REM_MODE_HEADER:
                    $fileContent = static::fileFromUrlContent($filename);
                    $thumbnailFileName = \md5(
                        $filename . $width . $height . $mode . static::fileFromUrlDate($filename)
                    );
                    break;
                default:
                    throw new InvalidConfigException('Unknown `checkRemFileMode` param value.');
            }
        } else {
            $filename = FileHelper::normalizePath(Yii::getAlias($filename));
            if (!\is_file($filename)) {
                throw new FileNotFoundException("File {$filename} doesn't exist");
            }
            $thumbnailFileName = \md5($filename . $width . $height . $mode . \filemtime($filename));
        }
        $cachePath = Yii::getAlias('@webroot/' . static::$cacheAlias);

        $thumbnailFileExt = \strrchr($filename, '.');
        $thumbnailFilePath = $cachePath . DIRECTORY_SEPARATOR . \substr($thumbnailFileName, 0, 2);
        $thumbnailFile = $thumbnailFilePath . DIRECTORY_SEPARATOR . $thumbnailFileName . $thumbnailFileExt;

        if (\file_exists($thumbnailFile)) {
            if (static::$cacheExpire !== 0 && (\time() - \filemtime($thumbnailFile)) > static::$cacheExpire) {
                \unlink($thumbnailFile);
            } else {
                return $thumbnailFile;
            }
        }
        if (!\is_dir($thumbnailFilePath)) {
            \mkdir($thumbnailFilePath, self::MKDIR_MODE, true);
        }

        if ($fileNameIsUrl) {
            $image = Image::getImagine()->load($fileContent ?: static::fileFromUrlContent($filename));
        } else {
            $image = Image::getImagine()->open($filename);
        }
        if ($mode === self::THUMBNAIL_INSET_BOX) {
            $image = $image->thumbnail(new Box($width, $height), ManipulatorInterface::THUMBNAIL_INSET);
        } else {
            $image = Image::thumbnail($image, $width, $height, $mode);
        }

        $options = [
            'quality' => $quality === null ? self::QUALITY : $quality
        ];

        $image->save($thumbnailFile, $options);
        return $thumbnailFile;
    }

    /**
     * Creates and caches the image thumbnail and returns URL from thumbnail file.
     *
     * If one of thumbnail dimensions is set to `null`, another one is calculated automatically based on aspect ratio of
     * original image. Note that calculated thumbnail dimension may vary depending on the source image in this case.
     *
     * If both dimensions are specified, resulting thumbnail would be exactly the width and height specified. How it's
     * achieved depends on the mode.
     *
     * If `self::THUMBNAIL_OUTBOUND` mode is used, which is default, then the thumbnail is scaled so that
     * its smallest side equals the length of the corresponding side in the original image. Any excess outside of
     * the scaled thumbnail’s area will be cropped, and the returned thumbnail will have the exact width and height
     * specified.
     *
     * If thumbnail mode is `self::THUMBNAIL_INSET`, the original image is scaled down so it is fully
     * contained within the thumbnail dimensions. The rest is filled with background that could be configured via
     * [[Image::$thumbnailBackgroundColor]] or [[EasyThumbnail::$thumbnailBackgroundColor]],
     * and [[Image::$thumbnailBackgroundAlpha]] or [[EasyThumbnail::$thumbnailBackgroundAlpha]].
     *
     * If thumbnail mode is `self::THUMBNAIL_INSET_BOX`, the original image is scaled down so it is fully contained
     * within the thumbnail dimensions. The specified $width and $height (supplied via $size) will be considered
     * maximum limits. Unless the given dimensions are equal to the original image’s aspect ratio, one dimension in the
     * resulting thumbnail will be smaller than the given limit.
     *
     * @param string $filename the image file path or path alias or URL
     * @param integer $width the width in pixels to create the thumbnail
     * @param integer $height the height in pixels to create the thumbnail
     * @param string $mode mode of resizing original image to use in case both width and height specified
     * @param integer $quality
     * @param integer $checkRemFileMode check file version on remote server
     * @return string
     * @throws FileNotFoundException
     * @throws InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public static function thumbnailFileUrl($filename, $width, $height, $mode = self::THUMBNAIL_OUTBOUND,
                                            $quality = null, $checkRemFileMode = self::CHECK_REM_MODE_NONE)
    {
        $cacheUrl = Yii::getAlias('@web/' . static::$cacheAlias);
        $thumbnailFilePath = static::thumbnailFile($filename, $width, $height, $mode, $quality, $checkRemFileMode);

        \preg_match('#[^\\' . DIRECTORY_SEPARATOR . ']+$#', $thumbnailFilePath, $matches);
        $fileName = $matches[0];

        return $cacheUrl . '/' . \substr($fileName, 0, 2) . '/' . $fileName;
    }

    /**
     * Creates and caches the image thumbnail and returns <img> tag.
     *
     * If one of thumbnail dimensions is set to `null`, another one is calculated automatically based on aspect ratio of
     * original image. Note that calculated thumbnail dimension may vary depending on the source image in this case.
     *
     * If both dimensions are specified, resulting thumbnail would be exactly the width and height specified. How it's
     * achieved depends on the mode.
     *
     * If `self::THUMBNAIL_OUTBOUND` mode is used, which is default, then the thumbnail is scaled so that
     * its smallest side equals the length of the corresponding side in the original image. Any excess outside of
     * the scaled thumbnail’s area will be cropped, and the returned thumbnail will have the exact width and height
     * specified.
     *
     * If thumbnail mode is `self::THUMBNAIL_INSET`, the original image is scaled down so it is fully
     * contained within the thumbnail dimensions. The rest is filled with background that could be configured via
     * [[Image::$thumbnailBackgroundColor]] or [[EasyThumbnail::$thumbnailBackgroundColor]],
     * and [[Image::$thumbnailBackgroundAlpha]] or [[EasyThumbnail::$thumbnailBackgroundAlpha]].
     *
     * @param string $filename the image file path or path alias or URL
     * @param integer $width the width in pixels to create the thumbnail
     * @param integer $height the height in pixels to create the thumbnail
     * @param string $mode mode of resizing original image to use in case both width and height specified
     * @param integer $quality
     * @param integer $checkRemFileMode check file version on remote server
     * @return string
     * @throws \Imagine\Exception\InvalidArgumentException
     * @throws \Imagine\Exception\RuntimeException
     * @throws \yii\base\InvalidParamException
     */
    public static function thumbnailImg($filename, $width, $height, $mode = self::THUMBNAIL_OUTBOUND, $options = [],
                                        $quality = null, $checkRemFileMode = self::CHECK_REM_MODE_NONE)
    {
        try {
            $thumbnailFileUrl = static::thumbnailFileUrl(
                $filename,
                $width,
                $height,
                $mode,
                $quality,
                $checkRemFileMode
            );
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
     * @return bool
     * @throws \yii\base\ErrorException
     */
    public static function clearCache()
    {
        $cacheDir = Yii::getAlias('@webroot/' . static::$cacheAlias);
        FileHelper::removeDirectory($cacheDir);
        return @\mkdir($cacheDir, self::MKDIR_MODE, true);
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
     * @throws \yii\httpclient\Exception
     */
    protected static function fileFromUrlDate($url)
    {
        $response = self::getHttpClient()
            ->head($url)
            ->send();
        if (!$response->isOk) {
            throw new FileNotFoundException("URL {$url} doesn't exist");
        }

        return $response->headers['Last-Modified'];
    }

    /**
     * @param string $url
     * @return string
     * @throws FileNotFoundException
     * @throws InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    protected static function fileFromUrlContent($url)
    {
        $response = self::getHttpClient()
            ->createRequest()
            ->setMethod('GET')
            ->setUrl($url)
            ->send();
        if (!$response->isOk) {
            throw new FileNotFoundException("URL {$url} doesn't exist");
        }

        return $response->content;
    }

    /**
     * @return HttpClient
     */
    protected static function getHttpClient()
    {
        if (self::$httpClient === null || !(self::$httpClient instanceof HttpClient)) {
            self::$httpClient = new HttpClient();
        }

        return self::$httpClient;
    }
}
