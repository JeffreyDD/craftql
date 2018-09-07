<?php

namespace markhuot\CraftQL\Listeners;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\UnionType;
use markhuot\CraftQL\Builders\Field;
use markhuot\CraftQL\Helpers\StringHelper;
use markhuot\CraftQL\Types\Entry;

class GetMatrixFieldSchema
{
    /**
     * Handle the request for the schema
     *
     * @param \markhuot\CraftQL\Events\GetFieldSchema $event
     * @return void
     */
    function handle($event) {
        $event->handled = true;

        $field = $event->sender;
        $schema = $event->schema;

        $union = $schema->addUnionField($field)
            ->lists()
            ->arguments(function ($field) {
                $field->addStringArgument('type');
                $field->addIntArgument('limit');
            })
            ->resolveType(function ($root, $args) use ($field) {
                $block = $root->getType();
                return ucfirst($field->handle).ucfirst($block->handle);
            })
            ->resolve(function ($root, $args, $context, $info) use ($field) {
                if (!empty($args['type'])) {
                    $query = $root->{$field->handle}->type($args['type']);
                }
                else {
                    $query = $root->{$field->handle};
                }

                if (!empty($args['limit'])) {
                    $query = $query->limit($args['limit']);
                }

                return $query->all();
            });

        $blockTypes = $field->getBlockTypes();

        foreach ($blockTypes as $blockType) {
            $typeNames = array_map(function (Entry $type) {
                return StringHelper::graphQLNameForEntryType($type->getContext());
            }, $schema->getRequest()->entryTypes()->all());

            $matrixBlockName = ucfirst($field->handle).ucfirst($blockType->handle);

            if (in_array($matrixBlockName, $typeNames)) {
                $matrixBlockName .= 'Matrix';
            }

            $type = $union->addType($matrixBlockName, $blockType);
            $type->addStringField('id'); // ideally this would be an `int`, but draft matrix blocks have an id of `new1`
            $type->addFieldsByLayoutId($blockType->fieldLayoutId);

            if (empty($type->getFields())) {
                $warning = 'The block type, `'.$blockType->handle.'` on `'.$field->handle.'`, has no fields. This would violate the GraphQL spec so we filled it in with this placeholder.';

                $type->addStringField('empty')
                    ->description($warning)
                    ->resolve($warning);
            }
        }

        if (empty($blockTypes)) {
            $warning = 'The matrix field, `'.$field->name.'`, has no block types. This would violate the GraphQL spec so we filled it in with this placeholder.';

            $type = $union->addType(ucfirst($field->handle).'Empty');
            $type->addStringField('empty')
                ->description($warning)
                ->resolve($warning);
        }

        if (!empty($blockTypes)) {
            $inputType = $event->mutation->createInputObjectType(ucfirst($event->sender->handle) . 'Input');
            $inputType->addStringArgument('id');

            foreach ($blockTypes as $blockType) {
                $blockInputType = $event->mutation->createInputObjectType(ucfirst($event->sender->handle) . ucfirst($blockType->handle) . 'Input');
                $blockInputType->addArgumentsByLayoutId($blockType->fieldLayoutId);

                if (count($blockInputType->getArguments()) == 0) {
                    $blockInputType->addStringArgument('emptyEntrytType')->description('The entry type, '.$event->sender->handle.', has no fields. This would violate the GraphQL spec so we filled it in with this placeholder.');
                }

                $inputType->addArgument($blockType->handle)
                    ->type($blockInputType);
            }

            $event->mutation->addArgument($event->sender)
                ->lists()
                ->type($inputType)
                ->onSave(function ($values) {
                    $newValues = [];

                    foreach ($values as $index => $value) {
                        $id = @$value['id'] ? $value['id'] : "new{$index}";
                        unset($value['id']);
                        if (isset($value['type'])) {
                            $type = $value['type'];
                            $fields = $value['fields'];
                        }
                        else {
                            $type = array_keys($value)[0];
                            $fields = $value[$type];
                        }

                        $newValues[$id] = [
                            'type' => $type,
                            'enabled' => 1,
                            'fields' => $fields,
                        ];
                    }

                    return $newValues;
                });
        }

    }
}
