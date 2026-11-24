<?php

namespace OfflineAgency\MongoAutoSync\Traits;

use Exception;
use Illuminate\Support\Arr;

trait Helper
{
    /**
     * @return bool|mixed
     *
     * @throws Exception
     */
    public function isArray($options)
    {
        $this->validateOptions($options);

        return $this->getFieldTypeOptionsValue($options, 'is-array', 'boolean');
    }

    /**
     * @return bool|mixed
     *
     * @throws Exception
     */
    public function isCarbonDate($options)
    {
        $this->validateOptions($options);

        return $this->getFieldTypeOptionsValue($options, 'is-carbon-date', 'boolean');
    }

    /**
     * @throws Exception
     */
    private function validateOptions($options)
    {
        if (gettype($options) !== 'array') {
            throw new Exception($options.' is not a valid array!');
        }
    }

    /**
     * @throws Exception
     */
    private function validateOptionValue($value, string $expected)
    {
        if (gettype($value) !== $expected) {
            throw new Exception($value.' is not a valid '.$expected.' found '.gettype($value).'! Check on your model configurations.');
        }
    }

    /**
     * @return bool|mixed
     *
     * @throws Exception
     */
    private function getFieldTypeOptionsValue(array $options, string $key, string $expected)
    {
        if (Arr::has($options, $key)) {
            $value = Arr::get($options, $key);
            $this->validateOptionValue($value, $expected);

            return $value;
        } else {
            return false;
        }
    }
}
