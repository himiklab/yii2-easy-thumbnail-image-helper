<?php
/**
 * @link https://github.com/himiklab/yii2-easy-thumbnail-image-helper
 * @copyright Copyright (c) 2014 HimikLab
 * @license http://opensource.org/licenses/MIT
 */

namespace himiklab\thumbnail;

use Yii;
use yii\helpers\Html;
use yii\helpers\FileHelper;
use yii\imagine\Image;
use Imagine\Image\ManipulatorInterface;

/**
 * Yii2 helper for creating and caching thumbnails on real time
 * @author HimikLab
 * @package himiklab\thumbnail
 */
class EasyThumbnailImage extends Image
{
    const THUMBNAIL_OUTBOUND = ManipulatorInterface::THUMBNAIL_OUTBOUND;
    const THUMBNAIL_INSET = ManipulatorInterface::THUMBNAIL_INSET;

    /** @var string path alias relative with @web where the cache files are kept */
    public static $cacheAlias = 'assets/thumbnails';

    /**
     * Creates a thumbnail image. The function differs from `\Imagine\Image\ImageInterface::thumbnail()` function that
     * it keeps the aspect ratio of the image. And it differs from `\yii\imagine\Image::thumbnail()` function
     * that it use the cache.
     * @param string $filename the image file path or path alias
     * @param integer $width the width in pixels to create the thumbnail
     * @param integer $height the height in pixels to create the thumbnail
     * @param string $mode self::THUMBNAIL_INSET, the original image
     * is scaled down so it is fully contained within the thumbnail dimensions.
     * The specified $width and $height (supplied via $size) will be considered
     * maximum limits. Unless the given dimensions are equal to the original image’s
     * aspect ratio, one dimension in the resulting thumbnail will be smaller than
     * the given limit. If self::THUMBNAIL_OUTBOUND mode is chosen, then
     * the thumbnail is scaled so that its smallest side equals the length of the
     * corresponding side in the original image. Any excess outside of the scaled
     * thumbnail’s area will be cropped, and the returned thumbnail will have
     * the exact $width and $height specified
     * @return \Imagine\Image\ImageInterface
     */
    public static function thumbnail($filename, $width, $height, $mode = self::THUMBNAIL_OUTBOUND)
    {
        return static::getImagine()->open(self::thumbnailFile($filename, $width, $height, $mode));
    }

    /**
     * Return path from thumbnail file.
     * @param string $filename
     * @param integer $width
     * @param integer $height
     * @param string $mode
     * @return string
     * @throws FileNotFoundException
     */
    public static function thumbnailFile($filename, $width, $height, $mode = self::THUMBNAIL_OUTBOUND)
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
            return $thumbnailFile;
        }
        if (!is_dir($thumbnailFilePath)) {
            mkdir($thumbnailFilePath, 0755, true);
        }

        $image = parent::thumbnail($filename, $width, $height, $mode);
        $image->save($thumbnailFile);
        return $thumbnailFile;
    }

    /**
     * Return URL from thumbnail file.
     * @param string $filename
     * @param integer $width
     * @param integer $height
     * @param string $mode
     * @return string
     */
    public static function thumbnailFileUrl($filename, $width, $height, $mode = self::THUMBNAIL_OUTBOUND)
    {
        $filename = FileHelper::normalizePath(Yii::getAlias($filename));
        $cacheUrl = Yii::getAlias('@web/' . self::$cacheAlias);
        $thumbnailFilePath = self::thumbnailFile($filename, $width, $height, $mode);

        preg_match('#[^\\' . DIRECTORY_SEPARATOR . ']+$#', $thumbnailFilePath, $matches);
        $fileName = $matches[0];

        return $cacheUrl . '/' . substr($fileName, 0, 2) . '/' . $fileName;
    }

    /**
     * Creates a thumbnail image <img> tag.
     * @param string $filename
     * @param integer $width
     * @param integer $height
     * @param string $mode
     * @param array $options options similarly with \yii\helpers\Html::img()
     * @return string
     */
    public static function thumbnailImg($filename, $width, $height, $mode = self::THUMBNAIL_OUTBOUND, $options = [])
    {
        $filename = FileHelper::normalizePath(Yii::getAlias($filename));
        try {
            $thumbnailFileUrl = self::thumbnailFileUrl($filename, $width, $height, $mode);
        } catch (FileNotFoundException $e) {
            return 'File doesn\'t exist';
        } catch (\Exception $e) {
            Yii::warning("{$e->getCode()}\n{$e->getMessage()}\n{$e->getFile()}");
            return 'Error ' . $e->getCode();
        }

        $options['width'] = $width;
        $options['height'] = $height;

        return Html::img(
            $thumbnailFileUrl,
            $options
        );
    }
}
