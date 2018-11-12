<?php
/**
 * @link https://github.com/himiklab/yii2-easy-thumbnail-image-helper
 * @copyright Copyright (c) 2014-2018 HimikLab
 * @license http://opensource.org/licenses/MIT MIT
 */

namespace himiklab\thumbnail;

use yii\base\BaseObject;
use yii\imagine\Image;

/**
 * EasyThumbnailImage global configuration component.
 *
 * @author HimikLab
 * @package himiklab\thumbnail
 */
class EasyThumbnail extends BaseObject
{
    /** @var string $cacheAlias path alias relative with @web where the cache files are kept */
    public $cacheAlias = 'assets/thumbnails';

    /** @var integer $cacheExpire seconds */
    public $cacheExpire = 0;

    /** @var \yii\httpclient\Client */
    public $httpClient;

    /**
     * @var string
     * @see \yii\imagine\Image::$thumbnailBackgroundColor
     */
    public $thumbnailBackgroundColor;

    /**
     * @var string
     * @see \yii\imagine\Image::$thumbnailBackgroundAlpha
     */
    public $thumbnailBackgroundAlpha;

    public function init()
    {
        EasyThumbnailImage::$cacheAlias = $this->cacheAlias;
        EasyThumbnailImage::$cacheExpire = $this->cacheExpire;
        EasyThumbnailImage::$httpClient = $this->httpClient;

        if ($this->thumbnailBackgroundColor) {
            Image::$thumbnailBackgroundColor = $this->thumbnailBackgroundColor;
        }
        if ($this->thumbnailBackgroundAlpha) {
            Image::$thumbnailBackgroundAlpha = $this->thumbnailBackgroundAlpha;
        }
    }
}
