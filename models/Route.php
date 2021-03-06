<?php

namespace cyneek\yii2\routes\models;

use yii\db\ActiveRecord;

/**
 * Class Route.
 *
 * @property int $id
 * @property string $type
 * @property string $uri
 * @property string $route
 * @property string $config
 * @property string $app
 */
class Route extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'site_routes';
    }
}
