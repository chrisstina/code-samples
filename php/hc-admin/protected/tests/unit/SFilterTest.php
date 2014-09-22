<?php
Yii::import('application.modules.adv.components.widgets.filter.models.*');

/**
 * Юнит-тест для XML фильтров
 *
 * @author chriss
 */
class SFilterTest extends CTestCase 
{
    const EXCLUDED_GAME_ID = 180;
    
    public $stringFilterBasic       = '<and><eq variable="country" value="ad" /><eq variable="lang" value="de" /></and>';
    public $stringFilterBasicNot    = '<and><not><eq variable="country" value="ad" /></not><not><eq variable="lang" value="de" /></not></and>';
    public $blockFilterCommonAnd    = '<or desc="COMMON"><and><eq variable="country" value="ad" /><eq variable="lang" value="de" /></and></or>';
    public $blockFilterCommonOr     = '<or desc="COMMON"><or><eq variable="country" value="ad" /><eq variable="lang" value="de" /></or></or>';
    public $blockFilterExc          = '<or desc="EXCLUDE_GAME"><not><eq variable="game" value="180" /></not></or>';
    public $blockFilterComposite    = '<or desc="COMMON"><or><eq variable="country" value="ad" /><eq variable="lang" value="de" /></or></or><or desc="EXCLUDE_GAME"><not><eq variable="game" value="180" /></not></or>';
    public $blockFilterCompositeNot = '<or desc="COMMON"><or><eq variable="country" value="ad" /><not><eq variable="lang" value="de" /></not></or></or><or desc="EXCLUDE_GAME"><not><eq variable="game" value="180" /></not></or>';
    public $arrayFilter             = array('<or desc="COMMON"><and><eq variable="country" value="ad" /><eq variable="lang" value="de" /></and></or>');
    public $arrayFilterComposite    = array(
        '<or desc="COMMON"><and><eq variable="country" value="ad" /><eq variable="lang" value="de" /></and></or>',
        '<or desc="EXCLUDE_GAME"><not><eq variable="game" value="180" /></not></or>');
    
    public function test__construct()
    {
        $filter = new SFilter();
        $filter = new SFilter($this->stringFilterBasic);
        $filter = new SFilter($this->arrayFilter);
    }
    
    public function testGetCommonBlock()
    {
        $this->_testFilterCommon('stringFilterBasic');      // без разделения на блоки
        $this->_testFilterCommon('blockFilterCommonAnd');   // разделение на блоки одной строкой, один блок
        $this->_testFilterCommon('blockFilterComposite');   // разделение на блоки одной строкой, несколько блоков
        $this->_testFilterCommon('arrayFilter');            // разделение на блоки массивом, один блок
        $this->_testFilterCommon('arrayFilterComposite');   // разделение на блоки массивом, несколько блоков
    }
    
    public function testGetExcludedGame()
    {
        $filter = new SFilter($this->stringFilterBasic);    // без разделения на блоки, игра не указана
        $this->assertEquals(null, $filter->getExcludedGame());
        
        $filter = new SFilter($this->blockFilterCommonOr); // разделение на блоки одной строкой, один блок, игра не указана
        $this->assertEquals(null, $filter->getExcludedGame());
        
        $filter = new SFilter($this->blockFilterComposite); // разделение на блоки одной строкой, несколько блоков, игра указана
        $this->assertEquals(self::EXCLUDED_GAME_ID, $filter->getExcludedGame());
        
        $filter = new SFilter($this->blockFilterExc);       // разделение на блоки одной строкой, один блок, игра указана
        $this->assertEquals(self::EXCLUDED_GAME_ID, $filter->getExcludedGame());
        
        $filter = new SFilter($this->arrayFilter);          // разделение на блоки массивом, один блок, игра не указана
        $this->assertEquals(null, $filter->getExcludedGame());
        
        $filter = new SFilter($this->arrayFilterComposite); // разделение на блоки массивом, несколько блоков, игра указана
        $this->assertEquals(self::EXCLUDED_GAME_ID, $filter->getExcludedGame());
    }
    
    public function testGetLifetime()
    {
        $this->markTestSkipped('Not implemented');
    }
    
    /**
     * @todo test invalid
     */
    public function testGenerateXML()
    {
        $testCases = require Yii::getPathOfAlias('application.tests.filterTestCases').'.php';
        foreach ($testCases as $testCase)
        {
            $filter = new SFilter();
            if (isset($testCase['postValues']['show_in_game']))
                $this->assertEquals($testCase['xml'], $filter->generateXML(
                        $testCase['postValues']['expressions'], 
                        $testCase['postValues']['containers'], 
                        $testCase['postValues']['show_in_game']));
            else
                $this->assertEquals($testCase['xml'], $filter->generateXML(
                        $testCase['postValues']['expressions'], 
                        $testCase['postValues']['containers']));
        }
    }
    
    private function _testFilterCommon($filterType)
    {
        if (isset($this->{$filterType}))
        {
            $filter = new SFilter($this->{$filterType});
            $commonBlock = $filter->getCommonBlock();
            $this->assertTrue( ! empty($commonBlock));
            $this->assertTrue($commonBlock instanceof SFilterBlockCommon);
        }
    }
}

?>
