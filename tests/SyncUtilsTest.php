<?php

namespace Tests;

use Tests\Models\Article;

class SyncUtilsTest extends TestCase
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
            $cl => 'car',
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
            'en' => '1',
        ], $out);
    }

    public function test_is_ml()
    {
        $value = [
            'is-ml' => true,
        ];

        $out = isML($value);

        $this->assertEquals(true, $out);

        //

        $value = [
            'is-ml' => false,
        ];

        $out = isML($value);

        $this->assertEquals(false, $out);

        //

        $value = [
            'is-ml' => '',
        ];

        $out = isML($value);

        $this->assertEquals(false, $out);
    }

    public function test_is_md()
    {
        // Input expected '' from $value

        $value = [
            'is-md' => true,
        ];

        $out = isMD($value);
        $this->assertEquals(true, $out);

        //
        $value = [
            'is-md' => false,
        ];

        $out = isMD($value);
        $this->assertEquals(false, $out);

        //

        $value = [
            'is-md' => '',
        ];

        $out = isMD($value);
        $this->assertEquals(false, $out);
    }

    public function test_is_em()
    {
        // Input expected 'EmbedsMany' from $value

        $value = 'EmbedsMany';

        $out = is_EM($value);

        $this->assertEquals(true, $out);

        // Input expected '' from $value

        $value = '';

        $out = is_EM($value);

        $this->assertEquals(false, $out);
    }

    public function test_is_eo()
    {
        // Input expected 'EmbedsOne' from $value

        $value = 'EmbedsOne';

        $out = is_EO($value);

        $this->assertEquals(true, $out);

        // Input expected '' from $value
        $value = '';

        $out = is_EO($value);

        $this->assertEquals(false, $out);
    }

    public function test_is_hm()
    {
        // Input expected 'Has Many' from $value

        $value = 'HasMany';

        $out = is_HM($value);

        $this->assertEquals(true, $out);

        // Input expected '' from $value

        $value = '';

        $out = is_HM($value);

        $this->assertEquals(false, $out);
    }

    public function test_is_ho()
    {
        // Input expected 'HasOne' from $value

        $value = 'HasOne';

        $out = is_HO($value);

        $this->assertEquals(true, $out);

        // Input expected '' from $value

        $value = '';

        $out = is_HO($value);

        $this->assertEquals(false, $out);
    }

    public function test_is_editable()
    {
        $value = [
            'is-editable' => false,
        ];

        $out = isEditable($value);

        $this->assertEquals(false, $out);

        //

        $value = [
            'is-editable' => true,
        ];

        $out = isEditable($value);

        $this->assertEquals(true, $out);

        //

        $value = [
            'is-editable' => '',
        ];

        $out = isEditable($value);

        $this->assertEquals('', $out);
    }

    public function test_has_target()
    {
        $value = [
            'has-target' => false,
        ];

        $out = hasTarget($value);

        $this->assertEquals(false, $out);

        //

        $value = [
            'has-target' => true,
        ];

        $out = hasTarget($value);

        $this->assertEquals(true, $out);

        //

        $value = [
            'has-target' => '',
        ];

        $out = hasTarget($value);

        $this->assertEquals('', $out);
    }

    public function test_is_fillable()
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

    public function test_get_aid()
    {
        // Check if there's no data inside the database, set id to 1
        Article::truncate();
        $article = new Article;

        $out = getAID($article);

        $this->assertEquals(1, $out);

        // If there's already data inside the database, increments new data by 1

        $articleModel = $this->prepareArticleData([]);

        $out = getAID($article);

        $this->assertEquals(2, $out);

        Article::truncate();
    }

    public function test_get_array_with_empty_obj()
    {
        $article = new Article;
        $is_EO = true;
        $is_EM = [];
        $expectedArray = [
            (object) [
                'autoincrement_id' => null,
                'title' => null,
                'content' => null,
                'slug' => null,
                'visibility' => null,
                'status' => null,
                'is_deleted' => null,
                'is_active' => null,
            ],
        ];
        $out = getArrayWithEmptyObj($article, $is_EO, $is_EM);

        $this->assertEquals($expectedArray, $out);
    }

    public function test_get_counter_for_relationships()
    {
        $is_EO = true;
        $is_EM = false;
        $i = null;
        $method = '';

        $out = getCounterForRelationships($method, $is_EO, $is_EM, $i);

        $this->assertEquals('', $out);

        //

        $is_EO = false;
        $is_EM = true;
        $i = null;
        $method = '';

        $out = getCounterForRelationships($method, $is_EO, $is_EM, $i);

        $this->assertEquals('', $out);

        //

        $is_EO = true;
        $is_EM = false;
        $i = null;
        $method = null;

        $out = getCounterForRelationships($method, $is_EO, $is_EM, $i);

        $this->assertEquals('', $out);

        //

        $is_EO = false;
        $is_EM = true;
        $i = null;
        $method = null;

        $out = getCounterForRelationships($method, $is_EO, $is_EM, $i);

        $this->assertEquals('-'.$i, $out);
    }

    public function test_get_type_on_target()
    {
        $EM = [
            'typeOnTarget' => 'EmbedsMany',
        ];
        $EO = [
            'typeOnTarget' => 'EmbedsOne',
        ];
        $defaultValue = [];

        $outEM = getTypeOnTarget($EM);
        $outEO = getTypeOnTarget($EO);
        $outDefaultValue = getTypeOnTarget($defaultValue);

        $this->assertEquals('EmbedsMany', $outEM);
        $this->assertEquals('EmbedsOne', $outEO);
        $this->assertEquals('EmbedsMany', $outDefaultValue);
    }
}
