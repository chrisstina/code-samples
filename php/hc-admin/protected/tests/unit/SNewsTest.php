<?php
Yii::import('application.modules.adv.models.*');
Yii::import('ext.soapclient.*');

/**
 * SNews model test
 *
 * @author chriss
 */
class SNewsTest extends CDbTestCase 
{
    const GAME_ID = 1;
    const GOOGLE_PLAY_PID = 629;
    const APPLE_STORE_PID = 623;
    const NOKIA_OVI_PID = 123;
    const NEWS_ID_1 = 1;
    const NEWS_ID_2 = 2;
    
    public $fixtures = array(
        'core_providers'    => 'GameProvider',
        'adv_hidden'        => 'AdvHidden',
        'adv_comments'      => 'AdvComment',
    );
        
    public function test__construct()
    {
        $news = new SNews(self::NEWS_ID_1);
        $this->assertEquals(self::NEWS_ID_1, $news->id);
        $this->assertNotEmpty($news->soapUrl);
    }
    
    public function testRules()
    {
        $news = new SNews();
        $news->translationsAssoc = array(
            'en' => array('title' => 'invalid title longer than 50 symbols invalid title longer than 50 symbols', 'text' => 'valid'),
            'ru' => array('title' => 'valid', 'text' => 'invalid  has two spaces'),
        );
        
        $news->setParams();
        $this->assertTrue($news->validate());
        $warnings = $news->getWarnings();
        $this->assertFalse(empty($warnings));
        
        $news->translationsAssoc = array(
                'en' => array('title' => 'valid', 'text' => 'valid'),
                'ru' => array('title' => 'valid', 'text' => 'invalid  has two spaces'),
        );
        
        $news->setParams();
        $this->assertTrue($news->validate());
        $warnings = $news->getWarnings();
        $this->assertFalse(empty($warnings));
        
        $news->translationsAssoc = array(
                'en' => array('title' => 'valid', 'text' => 'valid'),
                'ru' => array('title' => 'valid', 'text' => 'invalid . has a space before dot'),
        );
        
        $news->setParams();
        $this->assertTrue($news->validate());
        $warnings = $news->getWarnings();
        $this->assertFalse(empty($warnings));
        
        $news->translationsAssoc = array(
                'en' => array('title' => 'valid', 'text' => 'valid'),
                'ru' => array('title' => 'valid', 'text' => 'valid'),
            );
        $news->setParams();
        $this->assertTrue($news->validate());
    }
    
    public function testGetAll()
    {
        $client = $this->getMock('SNews', array('getAllByProvider'));
        $client->expects($this->once())
                ->method('getAllByProvider')
                ->with(self::GOOGLE_PLAY_PID, 0, 10)
                ->will($this->returnValue($this->_getNews()));

        $allNews = $client->getAll(0, 10, self::GOOGLE_PLAY_PID);
        $this->assertEquals(5, count($allNews));
        $this->assertEquals(false, $allNews[0]->is_hidden);
        $this->assertEquals(true, $allNews[4]->is_hidden);
        $this->assertEquals('test comment', $allNews[0]->comments);
        
        $client = $this->getMock('SNews', array('getAvailable'));
        $client->expects($this->once())
                ->method('getAvailable')
                ->with(0, 10)
                ->will($this->returnValue($this->_getNews()));

        $this->assertEquals(5, count($client->getAll(0, 10)));
    }
    
    public function testGetAvailable()
    {
        $client = $this->getMock('SNews', array('call'));
        $client->expects($this->once())
                ->method('call')
                ->with('GetNewsList', array(
                    'offset' => 0,
                    'count' => 10,
                    'enabledOnly' => true,
                    'providerId' => null
                ))
                ->will($this->returnValue($this->_getNews()));
        $res = $client->getAvailable(0, 10);
        $this->assertEquals(4, $res->totalCount);
    }
    
    public function testGetAllByProvider()
    {
        $client = $this->getMock('SNews', array('call'));
        $client->expects($this->once())
                ->method('call')
                ->with('GetNewsList', array(
                    'offset' => 0,
                    'count' => 10,
                    'enabledOnly' => true,
                    'providerId' => self::GOOGLE_PLAY_PID
                ))
                ->will($this->returnValue($this->_getNews()));
        $res = $client->getAllByProvider(self::GOOGLE_PLAY_PID, 0, 10);
        $this->assertEquals(4, $res->totalCount);
    }
    
    public function testGetAllProviders()
    {
        $newsObject = new SNews();
        $newsObject->providers = array(120,123);
        $providers = $newsObject->getAllProviders();
        
        $this->assertEquals(
                array(array('id' => 120, 'title' => 'Nokia OVI'), array('id' => 123, 'title'=>'Google Play')),
                $providers
            );
    }
    
    public function testGetTotal()
    {
        $client = $this->getMock('SNews', array('call'));
        $client->expects($this->once())
                ->method('call')
                ->with('GetNewsList', array(
                    'offset' => 0,
                    'count' => 0,
                    'enabledOnly' => true,
                    'providerId' => self::GOOGLE_PLAY_PID
                ))
                ->will($this->returnValue($this->_getNews()));
        $res = $client->getTotal(self::GOOGLE_PLAY_PID);
        $this->assertEquals(4, $res);
    }
    
    public function testGetTotalAvailable()
    {
        $client = $this->getMock('SNews', array('call'));
        $client->expects($this->once())
                ->method('call')
                ->with('GetNewsList', array(
                    'offset' => 0,
                    'count' => 0,
                    'enabledOnly' => true,
                    'providerId' => null
                ))
                ->will($this->returnValue($this->_getNews()));
        
        $res = $client->getTotalAvailable();
        $this->assertEquals(4, $res);
    }
    
    public function testGetById()
    {
        $client = $this->getMock('SNews', array('call'));
        $client->expects($this->once())
                ->method('call')
                ->with('getGameNews', array('id' => self::NEWS_ID_1))
                ->will($this->returnValue($this->_getGameNewsItem()));
        
        $res = $client->getById(1, SNews::TARGET_GAME);
        
        $this->assertEquals(SNews::TARGET_GAME, $client->objectType);
        $this->assertEquals(self::NEWS_ID_1, $client->id);
        
        $client = $this->getMock('SNews', array('call'));
        $client->expects($this->once())
                ->method('call')
                ->with('getExternalNews', array('id' => self::NEWS_ID_1))
                ->will($this->returnValue($this->_getExtNewsItem()));
        
        $res = $client->getById(self::NEWS_ID_1, SNews::TARGET_EXTERNAL);
        
        $this->assertEquals(SNews::TARGET_EXTERNAL, $client->objectType);
        $this->assertEquals(self::NEWS_ID_1, $client->id);
        
        $client = $this->getMock('SNews', array('call'));
        $client->expects($this->never())->method('call');
        
        $res = $client->getById(self::NEWS_ID_1, 'invalid');
        $this->assertFalse($res);
    }
    
    public function testGetTranslatedTitle()
    {
        $news = new SNews();
        $news->translationsAssoc = array(
            SNews::DISPLAY_LANG => (object)array('title' => 'en title'),
            SNews::DISPLAY_LANG_2 => (object)array('title' => 'ru title'),
            'fr' => (object)array('title' => 'fr title'),
        );
        
        $this->assertEquals($news->translationsAssoc[SNews::DISPLAY_LANG]->title, $news->getTranslatedTitle($news));
        
        $news = new SNews();
        $news->translationsAssoc = array(
            SNews::DISPLAY_LANG_2 => (object)array('title' => 'ru title'),
            'fr' => (object)array('title' => 'fr title'),
        );
        
        $this->assertEquals($news->translationsAssoc[SNews::DISPLAY_LANG_2]->title, $news->getTranslatedTitle($news));
        
        $news = new SNews();
        $news->translationsAssoc = array(
            'de' => (object)array('title' => 'de title'),
            'fr' => (object)array('title' => 'fr title'),
        );
        
        $this->assertEquals($news->translationsAssoc['de']->title, $news->getTranslatedTitle($news));
    }

    public function testSave()
    {
        $client = $this->getMock('SNews', array('call'));
        $client->setParams(array(
            'id'            => self::NEWS_ID_1,
            'gameId'        => self::GAME_ID,
            'createTime'    => null,
            'available'     => false,
            'enabled'       => true,
            'providers'     => array(self::GOOGLE_PLAY_PID, self::APPLE_STORE_PID),
            'translations'  => array(),
        ));

        $client->expects($this->once())
            ->method('call')
            ->with('saveGameNews', array('gameNews' => array(
                    'id'            => self::NEWS_ID_1, 
                    'gameId'        => self::GAME_ID,
                    'createTime'    => null,
                    'available'     => false,
                    'enabled'       => true,
                    'providers'     => array(self::GOOGLE_PLAY_PID, self::APPLE_STORE_PID),
                    'providerPriorities' => array(),
                    'translations'  => array(),
                    'filters'       => '',
                    'type'          => null,
                )
        ));

        $client->save();
    }
    
    public function testSaveProvider()
    {
        $client = $this->getMock('SNews', array('call', 'getTotal'));
        $client->setParams(array(
            'id'            => self::NEWS_ID_1, 
            'gameId'        => self::GAME_ID,
            'createTime'    => null,
            'available'     => false,
            'enabled'       => true,
            'providers'     => array(self::GOOGLE_PLAY_PID, self::APPLE_STORE_PID),
            'providerPriorities' => (object)array(
                        'entry' => array(
                            (object)array('key' => self::GOOGLE_PLAY_PID, 'value' => 0),
                            (object)array('key' => self::APPLE_STORE_PID, 'value' => 1),
                        )
                    ),
            'translations'  => array(),
        ));
        
        $client->expects($this->at(2))
            ->method('call')
            ->with('saveGameNews', array('gameNews' => array(
                    'id'            => self::NEWS_ID_1, 
                    'gameId'        => self::GAME_ID,
                    'createTime'    => null,
                    'available'     => false,
                    'enabled'       => true,
                    'providers'     => array(self::GOOGLE_PLAY_PID, self::APPLE_STORE_PID, self::NOKIA_OVI_PID),
                    'providerPriorities' => array(
                        'entry' => array(
                            array('key' => self::NOKIA_OVI_PID, 'value' => 2),
                            (object)array('key' => self::GOOGLE_PLAY_PID, 'value' => 0),
                            (object)array('key' => self::APPLE_STORE_PID, 'value' => 1),
                        )
                    ),
                    'translations'  => array(),
                    'filters'       => '',
                    'type'          => null,
                )
        ));
        
        $client->expects($this->any())
                ->method('getTotal')
                ->with($this->anything())
                ->will($this->returnValue(1));

        $client->saveProvider(self::NOKIA_OVI_PID);
        
        $client = $this->getMock('SNews', array('call'));
        $client->setParams(array(
            'id'            => self::NEWS_ID_1, 
            'gameId'        => self::GAME_ID,
            'createTime'    => null,
            'available'     => false,
            'enabled'       => true,
            'providers'     => array(self::GOOGLE_PLAY_PID, self::APPLE_STORE_PID),
            'translations'  => array(),
        ));

        $client->expects($this->never())->method('call');
        $client->saveProvider(self::GOOGLE_PLAY_PID);
    }
    
    public function testRemoveProvider()
    {
        $client = $this->getMock('SNews', array('call'));
        $client->setParams(array(
            'id'            => self::NEWS_ID_1, 
            'gameId'        => self::GAME_ID,
            'createTime'    => null,
            'available'     => false,
            'enabled'       => true,
            'providers'     => array(self::GOOGLE_PLAY_PID, self::APPLE_STORE_PID, self::NOKIA_OVI_PID),
            'providerPriorities' => (object)array(
                        'entry' => array(
                            (object)array('key' => self::GOOGLE_PLAY_PID, 'value' => 0),
                            (object)array('key' => self::APPLE_STORE_PID, 'value' => 1),
                            (object)array('key' => self::NOKIA_OVI_PID, 'value' => 2),
                        )
                    ),
            'translations'  => array(),
            'type'          => SBanner::TYPE_DEFAULT
        ));

        $client->expects($this->once())
            ->method('call')
            ->with('saveGameNews', array('gameNews' => array(
                    'id'            => self::NEWS_ID_1, 
                    'gameId'        => self::GAME_ID,
                    'createTime'    => null,
                    'available'     => false,
                    'enabled'       => true,
                    'providers'     => array(0 => self::GOOGLE_PLAY_PID, 1 => self::NOKIA_OVI_PID),
                    'providerPriorities' => array(
                        'entry' => array(
                            0 => (object)array('key' => self::GOOGLE_PLAY_PID, 'value' => 0),
                            2 => (object)array('key' => self::NOKIA_OVI_PID, 'value' => 2),
                        )
                    ),
                    'translations'  => array(),
                    'filters'       => '',
                    'type'          => SBanner::TYPE_DEFAULT,
                )
        ));

        $client->removeProvider(self::APPLE_STORE_PID);
    }
    
    /**
     * Имитирует ответ веб-сервиса, метод получения новостей
     */
    private function _getNews()
    {
        $res = new stdClass();
        $translations = array(
            (object)array('lang' => 'ru', 'title' => 'ru title', 'text' => 'ru text'),
            (object)array('lang' => 'en', 'title' => 'en title', 'text' => 'en text')
        );
        $n1 = new stdClass();
        $n2 = new stdClass();
        $n3 = new stdClass();
        $n4 = new stdClass();
        $n5 = new stdClass();
        
        $n1->translations = $n2->translations = $n3->translations = $n4->translations = $n5->translations = $translations;
        $n1->id = 1;
        $n2->id = 2;
        $n3->id = 3;
        $n4->id = 4;
        $n5->id = 5;

        $n1->gameId = self::GAME_ID;
        $n2->gameId = self::GAME_ID;
        $n5->gameId = self::GAME_ID;
        $n3->code = 'code1';
        $n4->code = 'code4';
        
        $gameNews       = array($n1, $n2, $n5);
        $externalNews   = array($n3, $n4);
        $sortedIds      = array(1,2,3,4, 5);
        
        $res->gameNews      = $gameNews;
        $res->externalNews  = $externalNews;
        $res->sortedIdList  = $sortedIds;
        $res->totalCount = 4;
        
        return $res;
    }
    
    /**
     * Имитирует ответ веб-сервиса, метод получения игровой новости
     */
    private function _getGameNewsItem()
    {
        $res = new stdClass();
        $translations = array(
            array('lang' => 'ru', 'title' => 'ru title', 'text' => 'ru text'),
            array('lang' => 'en', 'title' => 'en title', 'text' => 'en text')
        );
        $n1 = new stdClass();
        $n1->translations = $translations;
        $n1->id = 1;
        
        $res = new stdClass();
        $res->gameNews = $n1;
        return $res;
    }
    
     
    /**
     * Имитирует ответ веб-сервиса, метод получения внешней новости
     */
    private function _getExtNewsItem()
    {
        $res = new stdClass();
        $translations = array(
            array('lang' => 'ru', 'title' => 'ru title', 'text' => 'ru text'),
            array('lang' => 'en', 'title' => 'en title', 'text' => 'en text')
        );
        $n1 = new stdClass();
        $n1->translations = $translations;
        $n1->id = 1;
        
        $res = new stdClass();
        $res->externalNews = $n1;
        return $res;
    }
}

?>
