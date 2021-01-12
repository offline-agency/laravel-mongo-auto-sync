<?php


namespace Tests;

use Illuminate\Http\Request;
use Tests\Models\SubItem;
use Illuminate\Support\Str;



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

    public function test_isMl()
    {
        $value = [
            'is-ml'=>true
        ];

        $out = isML($value);

        $this->assertEquals(true, $out);

        //

        $value = [
            'is-ml'=>false
        ];

        $out = isML($value);

        $this->assertEquals(false, $out);

        //

        $value = [
            'is-ml'=>''
        ];

        $out = isML($value);

        $this->assertEquals(false, $out);
    }

    public function test_isMD()
    {
        //Input expected '' from $value

        $value = [
            'is-md' => true
        ];

        $out = isMD($value);
        $this->assertEquals(true, $out);

        //
        $value = [
            'is-md' => false
        ];

        $out = isMD($value);
        $this->assertEquals(false, $out);

        //

        $value = [
            'is-md' => ''
        ];

        $out = isMD($value);
        $this->assertEquals(false, $out);
    }

    public function test_isEM()
    {
        //Input expected 'EmbedsMany' from $value

        $value = 'EmbedsMany';

        $out = is_EM($value);

        $this->assertEquals(true, $out);

        //Input expected '' from $value

        $value = '';


        $out = is_EM($value);

        $this->assertEquals(false, $out);

    }

    public function test_isEO()
    {
        //Input expected 'EmbedsOne' from $value

        $value = 'EmbedsOne';

        $out = is_EO($value);

        $this->assertEquals(true, $out);

        //Input expected '' from $value
        $value = '';

        $out = is_EO($value);

        $this->assertEquals(false, $out);
    }

    public function test_isHM()
    {
        //Input expected 'Has Many' from $value

        $value = 'HasMany';

        $out = is_HM($value);

        $this->assertEquals(true, $out);

        //Input expected '' from $value

        $value = '';

        $out = is_HM($value);

        $this->assertEquals(false, $out);
    }

    public function test_isHO()
    {
        //Input expected 'HasOne' from $value

        $value = 'HasOne';

        $out = is_HO($value);

        $this->assertEquals(true, $out);

        //Input expected '' from $value

        $value = '';

        $out = is_HO($value);

        $this->assertEquals(false, $out);
    }

    public function test_isEditable()
    {
        $value = [
            'is-editable' => false
        ];

        $out = isEditable($value);

        $this->assertEquals(false, $out);

        //

        $value = [
            'is-editable' => true
        ];

        $out = isEditable($value);

        $this->assertEquals(true, $out);

        //

        $value = [
            'is-editable' => ''
        ];

        $out = isEditable($value);

        $this->assertEquals('', $out);
    }

    public function test_hasTarget()
    {
        $value = [
            'has-target' => false
        ];

        $out = hasTarget($value);

        $this->assertEquals(false, $out);

        //

        $value = [
            'has-target' => true
        ];

        $out = hasTarget($value);

        $this->assertEquals(true, $out);

        //

        $value = [
            'has-target' => ''
        ];

        $out = hasTarget($value);

        $this->assertEquals('', $out);
    }

    public function test_isFillable()
    {
        //
        $value = [];
        $event = '';

        $out = isFillable($value, $event);

        $this->assertEquals(isEditable($value), $out);

        //

        $value = '';
        $event = 'add';

        $out = isFillable($value, $event);

        $this->assertEquals(true, $out);
    }
}
