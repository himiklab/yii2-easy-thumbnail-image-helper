<?php
/**
 * @link https://github.com/himiklab/yii2-easy-thumbnail-image-helper
 * @copyright Copyright (c) 2014 HimikLab
 * @license http://opensource.org/licenses/MIT MIT
 */

namespace himiklab\thumbnail;

use Imagine\Image\Box;
use Imagine\Image\ManipulatorInterface;
use Yii;
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

    /** @var string $cacheAlias path alias relative with @web where the cache files are kept */
    public static $cacheAlias = 'assets/thumbnails';

    /** @var int $cacheExpire */
    public static $cacheExpire = 0;

    /**
     * Creates and caches the image thumbnail and returns ImageInterface.
     *
     * @param string $filename the image file path or path alias
     * @param integer $width the width in pixels to create the thumbnail
     * @param integer $height the height in pixels to create the thumbnail
     * @param string $mode self::THUMBNAIL_INSET, the original image
     * @param integer $quality
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
     */
    public static function thumbnail($filename, $width, $height, $mode = self::THUMBNAIL_OUTBOUND, $quality = null)
    {
        return Image::getImagine()->open(self::thumbnailFile($filename, $width, $height, $mode, $quality));
    }

    /**
     * Creates and caches the image thumbnail and returns full path from thumbnail file.
     *
     * @param string $filename the image file path or path alias
     * @param integer $width the width in pixels to create the thumbnail
     * @param integer $height the height in pixels to create the thumbnail
     * @param string $mode self::THUMBNAIL_INSET, the original image
     * @param integer $quality
     * @return string
     * @throws FileNotFoundException
     * @throws \Imagine\Exception\InvalidArgumentException
     * @throws \Imagine\Exception\RuntimeException
     * @throws \yii\base\InvalidParamException
     */
    public static function thumbnailFile($filename, $width, $height, $mode = self::THUMBNAIL_OUTBOUND, $quality = null)
    {
        $filename = FileHelper::normalizePath(Yii::getAlias($filename));
        if (!is_file($filename)) {
            throw new FileNotFoundException("File $filename doesn't exist");
        }
        $cachePath = Yii::getAlias('@webroot/' . self::$cacheAlias);

        $thumbnailFileExt = strrchr($filename, '.');
        $thumbnailFileName = md5($filename . $width . $height . $mode . filemtime($filename));
        $thumbnailFilePath = $cachePath . DIRECTORY_SEPARATOR . substr($thumbnailFileName, 0, 2);
        $thumbnailFile = $thumbnailFilePath . DIRECTORY_SEPARATOR . $thumbnailFileName . $thumbnailFileExt;

        if (file_exists($thumbnailFile)) {
            if (self::$cacheExpire !== 0 && (time() - filemtime($thumbnailFile)) > self::$cacheExpire) {
                unlink($thumbnailFile);
            } else {
                return $thumbnailFile;
            }
        }
        if (!is_dir($thumbnailFilePath)) {
            mkdir($thumbnailFilePath, self::MKDIR_MODE, true);
        }

        $box = new Box($width, $height);
        $image = Image::getImagine()->open($filename);
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
     * @param string $filename the image file path or path alias
     * @param integer $width the width in pixels to create the thumbnail
     * @param integer $height the height in pixels to create the thumbnail
     * @param string $mode self::THUMBNAIL_INSET, the original image
     * @param integer $quality
     * @return string
     * @throws FileNotFoundException
     * @throws \Imagine\Exception\InvalidArgumentException
     * @throws \Imagine\Exception\RuntimeException
     * @throws \yii\base\InvalidParamException
     */
    public static function thumbnailFileUrl($filename, $width, $height, $mode = self::THUMBNAIL_OUTBOUND, $quality = null)
    {
        $filename = FileHelper::normalizePath(Yii::getAlias($filename));
        $cacheUrl = Yii::getAlias('@web/' . self::$cacheAlias);
        $thumbnailFilePath = self::thumbnailFile($filename, $width, $height, $mode, $quality);

        preg_match('#[^\\' . DIRECTORY_SEPARATOR . ']+$#', $thumbnailFilePath, $matches);
        $fileName = $matches[0];

        return $cacheUrl . '/' . substr($fileName, 0, 2) . '/' . $fileName;
    }

    /**
     * Creates and caches the image thumbnail and returns <img> tag.
     *
     * @param string $filename the image file path or path alias
     * @param integer $width the width in pixels to create the thumbnail
     * @param integer $height the height in pixels to create the thumbnail
     * @param string $mode self::THUMBNAIL_INSET, the original image
     * @param array $options options similarly with \yii\helpers\Html::img()
     * @param integer $quality
     * @return string
     */
    public static function thumbnailImg($filename, $width, $height, $mode = self::THUMBNAIL_OUTBOUND, $options = [], $quality = null)
    {
        $filename = FileHelper::normalizePath(Yii::getAlias($filename));
        try {
            $thumbnailFileUrl = self::thumbnailFileUrl($filename, $width, $height, $mode, $quality);
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
        $cacheDir = Yii::getAlias('@webroot/' . self::$cacheAlias);
        self::removeDir($cacheDir);
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
            return 'File doesn\'t exist';
        } else {
            Yii::warning("{$error->getCode()}\n{$error->getMessage()}\n{$error->getFile()}");
            return 'Error ' . $error->getCode();
        }
    }
}
