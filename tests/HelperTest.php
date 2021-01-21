<?php


namespace Tests;

use Tests\Models\Article;

class HelperTest extends SyncTestCase
{
    public function test_isArray()
    {
        $stringValue = '';
        $boolValue = [
            'is-array' => true
        ];
        $emptyArray = [];
        $article = new Article;

        //Check return true
        $outBoolValue = $article->isArray($boolValue);
        $this->assertTrue($outBoolValue);

        //Check return false
        $outNotBoolValue = $article->isArray($emptyArray);
        $this->assertFalse($outNotBoolValue);

        //
        $this->expectExceptionMessage($stringValue . ' is not a valid array!');
        $article->isArray($stringValue);
    }

    public function test_validateOptionValueException()
    {
        $notBoolValue = [
            'is-array' => 'value'
        ];
        $article = new Article;
        $expected = 'boolean';
        $this->expectExceptionMessage($notBoolValue['is-array'] . ' is not a valid ' . $expected . ' found ' . gettype($notBoolValue['is-array']) . '! Check on your model configurations.');
        $article->isArray($notBoolValue);
    }

    public function test_isCarbonDate()
    {
        $stringValue = '';
        $boolValue = [
            'is-carbon-date' => true
        ];
        $emptyArray = [];
        $article = new Article;

        //Check return true
        $outBoolValue = $article->isCarbonDate($boolValue);
        $this->assertTrue($outBoolValue);

        //Check return false
        $outNotBoolValue = $article->isCarbonDate($emptyArray);
        $this->assertFalse($outNotBoolValue);

        //
        $this->expectExceptionMessage($stringValue . ' is not a valid array!');
        $article->isCarbonDate($stringValue);
    }
}
