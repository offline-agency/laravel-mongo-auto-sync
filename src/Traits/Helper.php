<?php

namespace OfflineAgency\MongoAutoSync\Traits;

use Illuminate\Support\Arr;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidConfigurationException;

trait Helper
{
    /**
     * @param  array<string, mixed>  $options
     * @return bool|mixed
     *
     * @throws InvalidConfigurationException
     */
    public function isArray($options)
    {
        $this->validateOptions($options);

        return $this->getFieldTypeOptionsValue($options, 'is-array', 'boolean');
    }

    /**
     * @param  array<string, mixed>  $options
     * @return bool|mixed
     *
     * @throws InvalidConfigurationException
     */
    public function isCarbonDate($options)
    {
        $this->validateOptions($options);

        return $this->getFieldTypeOptionsValue($options, 'is-carbon-date', 'boolean');
    }

    /**
     * @param  mixed  $options
     * @return void
     *
     * @throws InvalidConfigurationException
     */
    private function validateOptions($options)
    {
        if (gettype($options) !== 'array') {
            throw new InvalidConfigurationException($options.' is not a valid array!');
        }
    }

    /**
     * @param  mixed  $value
     * @return void
     *
     * @throws InvalidConfigurationException
     */
    private function validateOptionValue($value, string $expected)
    {
        if (gettype($value) !== $expected) {
            throw new InvalidConfigurationException($value.' is not a valid '.$expected.' found '.gettype($value).'! Check on your model configurations.');
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @return bool|mixed
     *
     * @throws InvalidConfigurationException
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
