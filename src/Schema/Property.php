<?php
/**
 * @package   business-central-sdk
 * @author    Morten Harders 🐢
 * @copyright 2020
 */

namespace BusinessCentral\Schema;

use BusinessCentral\Schema;
use BusinessCentral\Traits\HasSchema;
use Carbon\Carbon;

/**
 * Class Property
 *
 * @property string $name
 * @property string $type
 * @property bool   $read_only
 * @property bool   $fillable
 *
 * @author  Morten K. Harders 🐢 <mh@coolrunner.dk>
 * @package BusinessCentral\Schema
 */
class Property
{
    use HasSchema;

    protected $name;
    protected $type;

    protected $validation = [];

    protected $read_only = false;
    protected $guarded   = false;
    protected $fillable  = false;

    public function __construct($property, Schema $schema, EntityType $entity_type)
    {
        $this->schema = $schema;
        $this->name   = $property['@attributes']['Name'];
        $this->type   = $property['@attributes']['Type'];

        $this->read_only = $this->schema->propertyIsReadOnly($entity_type->name, $this->name);
        $this->fillable  = $this->schema->propertyIsFillable($entity_type->name, $this->name);


        $this->validation = [
            'nullable'   => filter_var($property['@attributes']['Nullable'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'max_length' => $property['@attributes']['MaxLength'] ?? null,
        ];
    }

    public function convert($value)
    {
        switch ($this->type) {
            case 'Edm.DateTimeOffset':
            case 'Edm.String':
            case 'Edm.Stream':
                return (string)$value;
            case 'Edm.Guid':
                if ($value === '00000000-0000-0000-0000-000000000000') {
                    return null;
                }

                return (string)$value;
            case 'Edm.Boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'Edm.Decimal':
                return doubleval($value);
            case 'Edm.Int32':
                return intval($value);
            default:
                if (strpos($this->type, 'Microsoft.NAV.') !== false) {
                    if ($complex_type = $this->schema->getComplexType(Schema::getType($this->type))) {
                        $data = [
                            '$complex_type' => $complex_type->name,
                        ];

                        foreach ($value ?? [] as $key => $attribute) {
                            if ($property = $complex_type->getProperty($key)) {
                                $data[$key] = $property->convert($attribute);
                            }
                        }

                        return $data;
                    }
                }

                return $value;
        }
    }

    public function getValidationType()
    {
        switch ($this->type) {
            case 'Edm.String':
            case 'Edm.Stream':
                return 'string';
            case 'Edm.Guid':
                return 'guid';
            case 'Edm.Boolean':
                return 'bool';
            case 'Edm.Decimal':
                return 'float';
            case 'Edm.Int32':
            case 'Edm.Int64':
                return 'int';
            case 'Edm.DateTimeOffset':
                return 'date';
            default:
                if (strpos($this->type, 'Microsoft.NAV.') !== false) {
                    return $this->schema->getComplexType(Schema::getType($this->type));
                }
        }
    }

    public function getDocType()
    {
        switch ($this->type) {
            case 'Edm.String':
            case 'Edm.Guid':
            case 'Edm.DateTimeOffset':
            case 'Edm.Stream':
                return 'string';
            case 'Edm.Boolean':
                return 'bool';
            case 'Edm.Decimal':
                return 'float';
            case 'Edm.Int32':
            case 'Edm.Int64':
                return 'int';
            default:
                if (strpos($this->type, 'Microsoft.NAV.') !== false) {
                    return $this->schema->getComplexType(Schema::getType($this->type));
                }

                return 'string';
        }
    }

    public function getValidation()
    {
        $rules = [];
        if ($this->name === 'id') {
            return $rules;
        }

        if ($this->validation['nullable']) {
            $rules[$this->name][] = 'nullable';
        } else {
            $rules[$this->name][] = 'required';
        }

        $type = $this->getValidationType();

        if ($type instanceof ComplexType) {
            foreach ($type->getValidationRules() as $key => $value) {
                $rules["$this->name.$key"] = $value;
            }
        } else {
            if ($max = $this->validation['max_length']) {
                $rules[$this->name][] = "$type:$max";
            } else {
                $rules[$this->name][] = $type;
            }
        }


        return $rules;
    }

    public function __get($name)
    {
        switch ($name) {
            case 'name':
            case 'type':
            case 'read_only':
            case 'guarded':
            case 'fillable':
                return $this->{$name};
        }
    }

    public function getEntityType()
    {
        return $this->schema->getEntityType($this->type);
    }
}