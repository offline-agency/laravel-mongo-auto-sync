<?php

namespace OfflineAgency\MongoAutoSync\Traits;

use Illuminate\Support\Arr;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidConfigurationException;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;

trait Helper
{
    /**
     * @param  mixed  $options
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
     * @param  mixed  $options
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
     * @phpstan-assert array<mixed> $options
     *
     * @throws InvalidConfigurationException
     */
    private function validateOptions($options)
    {
        if (gettype($options) !== 'array') {
            throw new InvalidConfigurationException(SyncHelper::toString($options).' is not a valid array!');
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
            throw new InvalidConfigurationException(SyncHelper::toString($value).' is not a valid '.$expected.' found '.gettype($value).'! Check on your model configurations.');
        }
    }

    /**
     * @param  array<mixed>  $options
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
