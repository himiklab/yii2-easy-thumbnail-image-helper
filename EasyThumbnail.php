<?php
namespace milano\thumbnail;

use yii\base\Object;

/**
 * EasyThumbnailImage global configuration component.
 *
 * @author HimikLab
 * @package himiklab\thumbnail
 */
class EasyThumbnail extends Object
{
    /** @var string $cacheAlias path alias relative with @web where the cache files are kept */
    public $cacheAlias = 'assets/thumbnails';

    /** @var int $cacheExpire seconds */
    public $cacheExpire = 0;

    public function init()
    {
        EasyThumbnailImage::$cacheAlias = $this->cacheAlias;
        EasyThumbnailImage::$cacheExpire = $this->cacheExpire;
    }
}
