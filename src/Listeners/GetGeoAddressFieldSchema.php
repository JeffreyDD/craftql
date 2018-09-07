<?php

namespace markhuot\CraftQL\Listeners;

use Craft;
use craft\helpers\ElementHelper;
use markhuot\CraftQL\Events\GetFieldSchema;

class GetGeoAddressFieldSchema
{
    /**
     * Handle the request for the schema
     *
     * @param \markhuot\CraftQL\Events\GetFieldSchema $event
     * @return void
     */
    function handle(GetFieldSchema $event) {
        $event->handled = true;

        $field = $event->sender;
        $schema = $event->schema;

        $object = $schema->createObjectType('GeoAddress');
        $object->addStringField('street');
        $object->addStringField('zip');
        $object->addStringField('city');
        $object->addStringField('country');
        $object->addStringField('lat');
        $object->addStringField('lng');

        $schema->addStringField($field)
            ->type($object)
            ->resolve(function ($root, $args) use ($field) {
                return $root->{$field->handle};
            });
    }
}
