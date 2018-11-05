<?php

namespace markhuot\CraftQL\Builders;

use DateTimeZone;
use markhuot\CraftQL\Types\Timestamp;

class Date extends Field {

    function getType() {
        return Timestamp::class;
    }

    function getResolve() {
        return function ($root, $args, $context, $info) {
            $format = 'U';
            $timezone = 'GMT';

            if (isset($info->fieldNodes[0]->directives[0])) {
                $directive = $info->fieldNodes[0]->directives[0];
                if ($directive->arguments) {
                    foreach ($directive->arguments as $arg) {
                        switch ($arg->name->value) {
                            case 'as':
                                $format = $arg->value->value;
                                break;

                            case 'timezone':
                                $timezone = $arg->value->value;
                                break;

                            case 'format':
                                $format = constant('DateTime::' . strtoupper($arg->value->value));
                                break;
                        }
                    }
                }
            }

            $date = $root->{$info->fieldName};

            if ($this->isNonNull() && !$date) {
                throw new Error("`{$info->fieldName}` is a required field but has no value");
            }

            if (!$date) {
                return null;
            }

            $date->setTimezone(new DateTimeZone($timezone));

            $date = $date->format($format);
            $cast = ($format === 'U') ? 'intval' : 'strval';
            return $cast($date);
        };
    }

}