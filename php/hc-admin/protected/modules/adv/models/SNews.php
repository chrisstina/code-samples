<?php
/**
 * Класс для работы с новостями
 * 
 * @author chriss
 */
class SNews extends SCoreAdv
{
    const MODULE_TITLE = 'news';
    
    const TARGET_GAME       = 'Game';
    const TARGET_EXTERNAL   = 'External';
    
    const DISPLAY_LANG      = 'en';
    const DISPLAY_LANG_2    = 'ru';
    const DEFAULT_BANNER_SIZE = '270_80';
    
    /**
     * Локаль для баннеров внешних новостей (папка для сохранения)
     */
    const DEFAULT_BANNER_LANG = 'en';
    
    /**
     * Допустимая величина отклонения от заданных размеров ресурсов в пикселях
     */
    const ALLOWED_MARGIN = 3;
    
    /**
     * URL браузера новостей, для загрузки в iframe на страницу управления новостями
     */
    const BROWSER_URL = 'https://yc.herocraft.com/client/?';
    
    /**
     * Id игры, по умолчанию передаваемый в браузер новостей
     */
    const DEFAULT_BROWSER_GAME_ID = 1;
    
    /**
     * Версия игры, по умолчанию передаваемая в браузер новостей
     */
    const DEFAULT_BROWSER_VERSION = '1.0.1';
    
    /**
     * Разрешение браузера новостей по умолчанию
     */
    const DEFAULT_RESOLUTION = '800_480';
    
    /**
     * Минимальное количество новостей для провайдера. Если меньше указанного, выводится предупреждение
     */
    const MIN_COUNT = 2;
    
    /**
     * Массив доступных разрешений для предпросмотра новостей
     * @var array
     */
    public static $resolutions = array(
        '1200_800' => array(
            'key' => '1200_800',
            'title' => '1200x800',
            'width' => 1200,
            'height' => 800
        ),
        '1200_720' => array(
            'key' => '1200_720',
            'title' => '1200x720',
            'width' => 1200,
            'height' => 720
        ),
        '1136_640' => array(
            'key' => '1136_640',
            'title' => '1136x640',
            'width' => 1136,
            'height' => 640
        ),
        '960_640' => array(
            'key' => '960_640',
            'title' => '960х640',
            'width' => 960,
            'height' => 640
        ),
        '960_540' => array(
            'key' => '960_540',
            'title' => '960х540',
            'width' => 960,
            'height' => 540
        ),
        '800_480' => array(
            'key' => '800_480',
            'title' => '800х480',
            'width' => 800,
            'height' => 480
        ),
        '480_320' => array(
            'key' => '480_320',
            'title' => '480х320',
            'width' => 480,
            'height' => 320
        ),
        '320_240' => array(
            'key' => '320_240',
            'title' => '320х240',
            'width' => 320,
            'height' => 240
        ),
    );

    /**
     * Реструктурированный массив переводов для вывода
     * @var array 
     */
    public $translationsAssoc = array();
    
    /**
     * Массивы переводов полей title и text для валидации
     * @var array 
     */
    public $title = array();
    public $text = array();
    
    /**
     * Код игры
     * @var string 
     */
    public $gameCode;
    
    /**
     * Id текущего выбранного провайдера
     * (для валидации)
     * @var int
     */
    public $currentProvider;
    
	/**
     * Поле для валидации иконок
     * @var array 
     */
    public $iconFields;
    
    /**
     * Приоритет новости для текущего провайдера
     * @var int
     */
    public $priority;

	/**
     * Приоритет по умолчанию
     * @var int
     */
    public $defaultPriority = 1;

    /**
     * Регулярное выражение, используемое при валидации текста новости.
     * По умолчанию два и более пробела / кавычки / запятые подряд, пробелы перед знаками препинания, 
     * знаки кроме букв любого алфавита, цифр, пробела и знаков препинания, а также
     * фигурные скобки, не попадающие под шаблон {число}.
     * @var string
     */
    public $validationPattern = '/(\ {2,}|\'{2,}|\"{2,})|(\ [\.\,\!\?\;\:])|([^A-Za-z\p{Cyrillic}\}\{\d\s\r\n\!\#\$\%\'\(\)\*\+\-\.\,\/\:\;\<\=\>\?\@\\\|\"\_\-\`\&\€\“\_\”\—\’ÁČĎÉĚÍŇÓŘŠŤÚŮÝŽÀÂÆÇÈÊËÎÏÔÙÛÜŸŒßÄÖĲΆΈΉΊΑΒΓΔΕΖΗΘΙΚΛΜΝΞΟΠΡΣΤΥΦΧΨΩΌΏΎŐŰÌÒĄĆĘŁŃŚŹŻÃÕ¡¿ÑĞŞİҐЄІЇ)])|(\{(?!\d+))|((?<!\d)\})/isu';
    
    public $is_hidden;
    
    protected $_params = array(
        'id'            => null, 
        'gameId'        => null,
        'createTime'    => null,
        'available'     => false,
        'enabled'       => true,
        'providers'     => array(),
        'providerPriorities' => array(),
        'translations'  => array(),
        'filters' => '',
        'type'  => null
    );
    
     /**
     * Синглтон
     * @var RMViewer
     */
    private $_resourceViewer;
    
    /**
     * Размеры иконок
     */
    private $_sizes = array(
        '48' => 'icon_mdpi.png',
        '72' => 'icon_hdpi.png',
        '98' => 'icon_xhdpi.png',
        '80' => 'p0.gif',
        '90' => 'icon_90x90.png',
        '240' => 'icon_240x240.png',
    );
    private $_requiredSizes = array('48' => 'icon_mdpi.png', '72' => 'icon_hdpi.png', '98' => 'icon_xhdpi.png');
    private $_defaultSize = '48';
    
    public function __construct($id = null)
    {
        parent::__construct();
        $this->soapUrl = Yii::app()->getModule('adv')->wsdlUrlNews;
        $this->id = $id;
    }
    
    public function attributeLabels() 
    {
        return array(
            'available' => Yii::t('AdvModule.news', 'enabled'),
            'gameId' => Yii::t('AdvModule.news', 'gameId'),
            'language' => Yii::t('AdvModule.news', 'language'),
            'label' => Yii::t('AdvModule.news', 'title'),
            'descr' => Yii::t('AdvModule.news', 'descr'),
            'title' => Yii::t('AdvModule.news', 'title'),
            'text' => Yii::t('AdvModule.news', 'descr'),
            'game' => Yii::t('AdvModule.news', 'game'),
            'link' => Yii::t('AdvModule.news', 'link'),
            'type' => Yii::t('AdvModule.news', 'banner_type'),
            'preview_text' => Yii::t('AdvModule.news', 'preview_text'),
            'translations' => Yii::t('AdvModule.news', 'translations'),
        );
    }
    
    public function rules() 
    {
        return array(
            array('title', 'application.modules.adv.components.validators.MultilangContentStringValidator', 'max'=>50, 'isCritical' => false, 'except' => 'addProvider'),
            array('text', 'application.modules.adv.components.validators.MultilangContentStringValidator', 'max'=>600, 'isCritical' => false, 'except' => 'addProvider'),
            array('text', 'application.modules.adv.components.validators.MultilangContentStringValidator', 'max'=>5000, 'except' => 'addProvider'),
            array('translations', 'application.modules.adv.components.validators.MultilangContentDependentRequiredValidator', 
                'attributeName' => 'text',
                'dependsOn'=>'title',
                'allowEmpty' => false, 
                'except' => 'addProvider'),
            array('gameId', 'application.modules.adv.components.validators.GameValidator', 'on' => 'list, addProvider'),
            array('gameId, type', 'required', 'on' => 'add'),
            array('priority', 'numerical', 'integerOnly'=>true, 'min'=>1, 'on' => 'update'),
            array('title, text', 
                'application.modules.adv.components.validators.MultilangContentRegexValidator', 
                'pattern'=>$this->validationPattern,
                'not' => true,
                'isCritical' => false,
                'except' => 'addProvider'),
            array('gameId', 'application.modules.adv.components.validators.ImageRequiredValidator', 'isCritical' => false),
        );
    }
    
     /**
     * Возвращает отсортированный массив всех новостей, с добавлением полей translationAssoc и objectType
     * 
     * @param int $offset
     * @param int $count 
     * @param int $pid id провайдера, если указан, возвращается список только опубликованных для провайдера новостей
      * @param bool $disbaledOnly - если true, выведет только отключенные, false - только включенные
     * 
     * @return array ассоциативный массив новостей вида [id : ['data' : [...]]]
     */
    public function getList($offset = 0, $count = 100, $pid = null, $displayMode = SNews::DISPLAY_STATUS_ENABLED)
    {
        $allNews = array();
        $plainGameNews = $plainExtNews = array();
        
        if ( ! $pid)
            $pid = $this->getCurrentProviderId();

        $res = $this->_findGameNews($offset, $count, $displayMode == SNews::DISPLAY_STATUS_ENABLED, $pid);
        
        if ($res->totalCount <= 0)
            return array();
        
        if ($res)
        {
            Yii::beginProfile('news_getall_plain_arrays');
            // make plain arrays
            if (isset($res->gameNews))
            {
                $arGameNews = $this->toArray($res->gameNews);
                foreach ($arGameNews as $news)
                    $plainGameNews[$news->id] = $news;
            }
            
            if (isset($res->externalNews))
            {
                $arExtNews = $this->toArray($res->externalNews);
                foreach ($arExtNews as $news)
                    $plainExtNews[$news->id] = $news;
            }

            $arIdList = $this->toArray($res->sortedIdList);
            
            $hiddenIds = AdvHidden::model()->getHidden($arIdList);
            foreach ($arIdList as $id)
            {
                if (isset($plainGameNews[$id]))
                {
                    $newsObj = new SNews();
                    $type   = self::TARGET_GAME;
                    $data   = $plainGameNews[$id];
                    $code   = Game::model()->getCode($data->gameId);
                }
                else if (isset($plainExtNews[$id]))
                {
                    $newsObj = new SNewsExternal();
                    $type   = self::TARGET_EXTERNAL;
                    $data   = $plainExtNews[$id];
                    $code   = $data->code;
                }
                
                // не добавлять в конечный список новость, у которой флаг enabled = true
                // используется при выводе неактивных новостей для провайдера
                if ($displayMode == SNews::DISPLAY_STATUS_DISABLED && $data->enabled) 
                {
                    continue;
                }
                
                $newsObj->setScenario('list');
                $newsObj->setParams($data);
                $newsObj->objectType = $type;
                
                $finalNewsObj = new stdClass();
                $finalNewsObj = $newsObj;
                $finalNewsObj->translationsAssoc = array();
                
                if (isset($newsObj->translations))
                {
                    $translations = $this->toArray($newsObj->translations);
                    foreach ($translations as $langData)
                        $finalNewsObj->translationsAssoc[$langData->lang] = $langData;
                }
                
                $finalNewsObj->gameCode = $code;
                $finalNewsObj->is_hidden = in_array($id, $hiddenIds);

                $allNews[] = $finalNewsObj;
            }
            Yii::endProfile('news_getall_plain_arrays');
        }
        
        return $allNews;
    }
    
    /**
     * Возвращает список всех провайдеров c названиями, к которым привязана новость
     * @return array массив вида {1:'title1', 2:'title2', ...}
     */
    public function getAllProviders()
    {
        $result = array();
        $providers = $this->toArray($this->providers);

        if ( ! empty($providers))
        {
            $criteria = new CDbCriteria();
            $criteria->addInCondition('provider_id', $providers);
            if ($providersData = GameProvider::model()->findAll($criteria))
            {
                foreach ($providersData as $provider)
                    $result[] = array('id' => $provider->provider_id, 'title' => $provider->name);
            }
        }

        return $result;
    }
    
    /**
     * Возвращает количество новостей провайдера.
     * Использует кэш.
     * @param int $pid id провайдера
     * @return int количество новостей для указанного провайдера
     */
    public function getTotal($pid)
    {
        Yii::beginProfile('SNews.getTotal');
        if (($total = Yii::app()->cache->get('total_news_'.$pid)) === false)
        {
            $response = $this->_findGameNews(0, 0, true, $pid);
            $total = $response->totalCount;
            Yii::app()->cache->set('total_news_'.$pid, $total, 600);
        }
        Yii::endProfile('SNews.getTotal');
        return $total;
    }
        
    public function getLowestPriority($pid)
    {
        return $this->getTotal($pid) + 1;
    }
    
    /**
     * Возвращает общее количество новостей
     * @return int 
     */
    public function getTotalAvailable()
    {
        $response = $this->_findGameNews(0, 0);
        return $response->totalCount;
    }
    
    /**
     * Запрашивает данные определенной новости по ее id и типу.
     * Устанавливает полученные значения в поля объекта.
     * 
     * @param int $id
     * @param string $type - game / external
     */
    public function getById($id, $type)
    {
        $type = strtolower($type);
        if ($type == strtolower(self::TARGET_EXTERNAL) || $type == strtolower(self::TARGET_GAME))
        {
            $res = $this->call('get'.$type.'News', array('id' => $id));
            $object = $type.'News';
            if (isset($res->$object))
                $this->setParams((array)$res->$object);
            
            $this->objectType = $type;
        }
        
        return false;
    }
    
    /**
     * Возвращает заголовок новости для вывода в списке.
     * Если присутствует заголовок для первого языка по умолчанию (английский), возвращает его,
     * в противном случае ищет сначала заголовок для второго языка по умолчанию, затем любой 
     * существуюший заголовок.
     * 
     * @return string 
     */
    public static function getTranslatedTitle($news) 
    {
        if (! empty($news->translationsAssoc[SNews::DISPLAY_LANG]->title))
            return $news->translationsAssoc[SNews::DISPLAY_LANG]->title;
        else if (! empty($news->translationsAssoc[SNews::DISPLAY_LANG_2]->title))
            return $news->translationsAssoc[SNews::DISPLAY_LANG_2]->title;
        else 
        {
            $nothingFound = true;
            foreach ($news->translationsAssoc as $lang => $title)
            {
                if ( ! empty($title->title))
                {
                    $nothingFound = false;
                    return $title->title;
                }
            }

            if ($nothingFound)
                return Yii::t('errors', 'no_value');
        }
    }
    
    /**
     * Сохраняет запись (обновляет либо создает)
     * @return bool успешность сохранения новости
     */
    public function save($withResources = false)
    {
        $this->updateProviderPriorities();
        $news = $this->call('saveGameNews', array('gameNews' => $this->_params));
        if (isset($news->gameNews->id))
        {
            $this->id = $news->gameNews->id;
            return true;
        }
        
        return false;
    }
    
    /**
     * Генерирует ссылку новости
     * @param int $id
     * @param int $providerId
     * @return string 
     */
    public function getUrl($id, $providerId)
    {
        try
        {
            $res = $this->call('buildNewsUrl', array('newsId' => $id, 'providerId' => $providerId));
            if (isset($res->url))
                return $res->url;
        }
        catch(CException $e)
        {
            return false;
        }
        
        return false;
    }
    
    /**
     * Возвращает код новости 
     * (в случае игровой - код игры)
     * 
     * @return string 
     */
    public function getCode()
    {
        if ( ! isset($this->gameCode) || $this->gameCode == null)
            $this->gameCode = Game::model()->getCode($this->gameId);
        return $this->gameCode;
    }
    
    /**
     * Возвращает приоритет текущей новости для текущего провайдера
     * @return int приоритет, если не найден соответствующий, возвращается по умолчанию
     */
    public function getPriority($currentProviderId = null)
    {
        if ( $this->currentProvider != null)
            return $this->getProviderPriority($this->currentProvider);
        else if ( $currentProviderId != null)
            return $this->getProviderPriority($currentProviderId);
        return $this->defaultPriority;
    }
    
    /**
     * Возвращает текст, отформатированный таким образом,
     * что места в тексте, не прошедшие валидацию (@see rules()), подсвечиваются.
     * 
     * @param string $text текст новости
     * @return string отформатированный html текста новости
     */
    public function highlightValidation($text)
    {
        return preg_replace($this->validationPattern, '<span class="invalid">$1$2$3$4$5$6</span>', $text);
    }
    
    /**
     * Дополнительно формирует массивы для полей title и text для валидации,
     * приводит массив translations к требуемой структуре.
     * @param array $params 
     */
    public function setParams($params = array()) 
    {
        parent::setParams($params);
        if ( ! empty($this->translationsAssoc))
        {
            $newTranslationStructure = array();
            foreach($this->translationsAssoc as $langName => $langData)
            {
                if (!is_array($langData))
                    $langData = (array)$langData;
                
                $this->title[$langName] = $langData['title'];
                $this->text[$langName]  = $langData['text'];
                
                $newTranslationStructure[] = array(
                    'lang' => $langName,
                    'title' => $langData['title'],
                    'text'  => $langData['text']
                );
            }
            $this->translations = $newTranslationStructure;
        }
        
        $this->priority = $this->getPriority();
        $this->gameCode = $this->getCode();
    }
    
    /**
     * Устанавливает значение массива translationsAssос,
     * основываясь на данных translation. Используется для
     * удобства вывода форм редактирования.
     */
    public function setTranslationsAssoc()
    {
        if ( !empty ($this->translations))
        {
            $translations = $this->toArray($this->translations);
                foreach ($translations as $langData)
                    $this->translationsAssoc[$langData->lang] = $langData;
        }
    }
    
    /** ======= Работа с ресурсами =======    */
    
    public function getResourceViewer()
    {
        if ( ! $this->_resourceViewer instanceof RMViewer)
        {
            $this->_resourceViewer = new RMViewer('games');
        }
        
        return $this->_resourceViewer;
    }
    
    public function getSizes()
    {
        return $this->_sizes;
    }
    
    public function getRequiredSizes()
    {
        return $this->_requiredSizes;
    }
    
    /**
     * Возвращает название иконки, ассоциированной с указанном размером.
     * 
     * @param string $sizeTitle
     * @return mixed название иконки (например, icon_mdpi.png), false если размер не задан
     */
    public function getSize($sizeTitle)
    {
        return isset($this->_sizes[$sizeTitle]) ? $this->_sizes[$sizeTitle] : false;
    }
    
    /**
     * Возвращает html изображения иконки новости для указанного размера (по-умолчанию - icon_mdpi.png).
     * Например - /images/upload/res/games/majesty_northern_expansion/icon_mdpi.png.
     * Если файл не найден, возвращает картинку noicon.png.
     * 
     * @param string $sizeTitle название размера (ключ в массиве $sizes)
     * @return string html картинки
     */
    public function getIcon($sizeTitle = null)
    {
        $path = $this->getIconPath($sizeTitle);
        return $this->iconExists($path) ?
            $this->getResourceViewer()->getImage($path, $this->gameCode) : 
            $this->getResourceViewer()->getNoiconImage();
    }
    
    /**
     * Возвращает объект привязанной игры
     * @return type
     */
    public function getGameObject()
    {
        return Game::model()->findByPk($this->gameId);
    }
    
    /**
     * Возвращает относительный путь к иконке 
     * (например, /majesty_northern_expansion/icon_mdpi.png или 13-11-08_ext_news_golodnye_igry_aktsiya_63308/icon_mdpi.png)
     * @param string $sizeTitle название размера (ключ в массиве $sizes)
     * @return string
     */
    public function getIconPath($sizeTitle = null)
    {
        return $this->getCode() . '/' . ($sizeTitle == null ? $this->_sizes[$this->_defaultSize] : $this->_sizes[$sizeTitle]);
    }
    
    public function iconExists($path)
    {
        return $this->getResourceViewer()->imageExists($path);
    }
    
    /**
     * Возвращает полный относительный путь к иконке 
     * (например, /images/upload/res/13-11-08_ext_news_golodnye_igry_aktsiya_63308/icon_mdpi.png)
     * @param string $sizeTitle название размера (ключ в массиве $sizes)
     * @return string
     */
    public function getFullIconPath($sizeTitle = null)
    {
        return $this->getResourceViewer()->getImagePath($this->getIconPath($sizeTitle));
    }

    /**
     * Возвращает html изображения баннера указанного типа для указанной игры
     * @param int $gid id игры
     * @param string $type тип баннера @see SBanner::getTypesAssoc()
     * @param string $lang
     * @return html картинки
     */
    public function getBannerImage($gid, $type, $lang = null)
    {
        $banner = new SBanner();
        $banner->gameId = $gid;
        $banner->imageLang = $lang;
        
        return $banner->getImage(self::DEFAULT_BANNER_SIZE, $type, false);
    }
    
    /** ======= In-model validators ========= */
    
    public function getMissingImages()
    {
        $errors = array();
        foreach ($this->getRequiredSizes() as $sizeTitle => $size)
        {
            if ( ! $this->getResourceViewer()->imageExists($this->getIconPath($sizeTitle)))
                $errors[] = $sizeTitle;
        }
        
        return $errors;
    }
    
    /**
     * Осуществляет валидацию на уровне стора.
     * Например, проверяет количество активных элементов для стора 
     * 
     * @param AppStore $store
     * @return array массив текстов предупреждений
     */
    public function getStoreWarnings($store) {
        
        $warnings = array();
        
        if ($this->getTotal($store->provider_id) <= SNews::MIN_COUNT)
        {
            $warnings[] = Yii::t('AdvModule.news', 'error_less_than_{min}', array('{min}'=>SNews::MIN_COUNT));
        }
        
        return $warnings;
    }
    
    /** ======= Campaign support functions ========= */
    
    public function getImage()
    {
        return $this->getIcon();
    }
    
    public function getTitle()
    {
        if ( empty($this->translationsAssoc))
            $this->setTranslationsAssoc();
        
        return SNews::getTranslatedTitle($this);
    }
    
    public function getListForCampaign($providerId)
    {
        return $this->getList(0, 100, $providerId, SCoreAdv::DISPLAY_STATUS_BOTH);
    }
    
    public function enable() {
        $this->enabled = true;
        return $this->save(false);
    }
    
    public function disable() {
        $this->enabled = false;
        return $this->save(false);
    }
    
    /** ======= Private functions ========= */
    
    /**
     * Запрашивает список новостей
     * 
     * @param int $offset
     * @param int $count если 0, вернет только общее количество (без самих записей)
     * @param bool $enabledOnly флаг - возвращать ли только активные новости, или все. По умолчанию true.
     * @return array [gameNews : [], externalNews : [], allSortedIds : []]
     */
    private function _findGameNews($offset = 0, $count = 10, $enabledOnly = true, $providerId = null)
    {
        $res = new stdClass();
        $res->totalCount = 0;
        
        try 
        {
            $res = $this->call('GetNewsList', array(
                'offset' => $offset,
                'count' => $count,
                'enabledOnly' => $enabledOnly,
                'providerId' => $providerId
            ));
        }
        catch(CException $e)
        {
            Yii::log($e->__toString(), 'error');
        }

        return $res;
    }
}