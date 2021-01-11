<?php


namespace Tests;


class SyncUtilsTest extends SyncTestCase
{
    public function test_get_translated_content()
    {
        // Input not expected Ex. String

        $mlCollection = '';

        $out = getTranslatedContent($mlCollection);

        $this->assertEquals('', $out);

        // Input not expected Array with key equals to language code  Ex. En

        $cl = cl();

        $mlCollection = [
            $cl => 'car'
        ];

        $out = getTranslatedContent($mlCollection);

        $this->assertEquals('car', $out);
    }

    public function test_cl()
    {

        // Input expected to be 'en' from $cl

        $cl = 'en';

        $out = cl($cl);

        $this->assertEquals('en', $out);
    }

    public function test_ml()
    {

        // Input expected to be [ "en" => "1" ] from $out

        $destination = null;
        $input = '1';
        $out = ml($destination, $input);
        $this->assertEquals([
            "en" => "1"
        ], $out);

    }


}
