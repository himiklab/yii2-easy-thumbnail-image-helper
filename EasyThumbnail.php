<?php
/**
 * @link https://github.com/himiklab/yii2-easy-thumbnail-image-helper
 * @copyright Copyright (c) 2014-2017 HimikLab
 * @license http://opensource.org/licenses/MIT MIT
 */

namespace himiklab\thumbnail;

use yii\base\BaseObject;

/**
 * EasyThumbnailImage global configuration component.
 *
 * @author HimikLab
 * @package himiklab\thumbnail
 */
class EasyThumbnail extends BaseObject
{
    const GRABBER_PHP = 1;
    const GRABBER_CURL = 2;

    /** @var string $cacheAlias path alias relative with @web where the cache files are kept */
    public $cacheAlias = 'assets/thumbnails';

    /** @var integer $cacheExpire seconds */
    public $cacheExpire = 0;

    /** @var integer */
    public $grabberType = self::GRABBER_PHP;

    public function init()
    {
        EasyThumbnailImage::$cacheAlias = $this->cacheAlias;
        EasyThumbnailImage::$cacheExpire = $this->cacheExpire;
        EasyThumbnailImage::$grabberType = $this->grabberType;
    }
}
