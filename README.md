Easy Thumbnail Image Helper for Yii2
========================

Yii2 helper for creating and caching thumbnails on real time.

Installation
------------
The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require "himiklab/yii2-easy-thumbnail-image-helper" "*"
```
or add

```json
"himiklab/yii2-easy-thumbnail-image-helper" : "*"
```

to the require section of your application's `composer.json` file.

Usage
-----
For example:

```php
use himiklab\thumbnail\EasyThumbnailImage;

EasyThumbnailImage::$cacheAlias = 'assets/thumbnails';

echo EasyThumbnailImage::thumbnailImg(
    $model->pictureFile,
    50,
    50,
    EasyThumbnailImage::THUMBNAIL_OUTBOUND,
    ['alt' => $model->pictureName]
);
```

For other functions please see the source code.
