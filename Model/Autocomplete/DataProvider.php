<?php
/**
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Elastic Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile\ElasticsuiteRetailer
 * @author    Fanny DECLERCK <fadec@smile.fr>
 * @copyright 2018 Smile
 * @license   Open Software License ("OSL") v. 3.0
 */
namespace Smile\ElasticsuiteRetailer\Model\Autocomplete;

use Magento\Search\Model\Autocomplete\DataProviderInterface;
use Magento\Search\Model\QueryFactory;
use Magento\Search\Model\Autocomplete\ItemFactory;
use Magento\Store\Model\StoreManagerInterface;
use Smile\ElasticsuiteCore\Helper\Autocomplete as ConfigurationHelper;
use Smile\ElasticsuiteCore\Model\Autocomplete\Terms\DataProvider as TermDataProvider;
use Smile\ElasticsuiteRetailer\Model\ResourceModel\Fulltext\CollectionFactory as RetailerCollectionFactory;

/**
 * Retailer autocomplete data provider.
 *
 * @category Smile
 * @package  Smile\ElasticsuiteRetailer
 * @author   Fanny DECLERCK <fadec@smile.fr>
 */
class DataProvider implements DataProviderInterface
{
    /**
     * Autocomplete type
     */
    const AUTOCOMPLETE_TYPE = "retailer";

    /**
     * Autocomplete result item factory
     *
     * @var ItemFactory
     */
    protected $itemFactory;

    /**
     * Query factory
     *
     * @var QueryFactory
     */
    protected $queryFactory;

    /**
     * @var TermDataProvider
     */
    protected $termDataProvider;

    /**
     * @var RetailerCollectionFactory
     */
    protected $retailerCollectionFactory;

    /**
     * @var ConfigurationHelper
     */
    protected $configurationHelper;

    /**
     * Store manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var string Autocomplete result type
     */
    private $type;

    /**
     * @var \Smile\StoreLocator\Helper\Data
     */
    protected $storeLocatorHelper;

    /**
     * Constructor.
     *
     * @param ItemFactory                     $itemFactory               Suggest item factory.
     * @param QueryFactory                    $queryFactory              Search query factory.
     * @param TermDataProvider                $termDataProvider          Search terms suggester.
     * @param RetailerCollectionFactory       $retailerCollectionFactory Retailer collection factory.
     * @param ConfigurationHelper             $configurationHelper       Autocomplete configuration helper.
     * @param StoreManagerInterface           $storeManager              Store manager.
     * @param \Smile\StoreLocator\Helper\Data $storeLocatorHelper        StoreLocator Helper.
     * @param string                          $type                      Autocomplete provider type.
     */
    public function __construct(
        ItemFactory $itemFactory,
        QueryFactory $queryFactory,
        TermDataProvider $termDataProvider,
        RetailerCollectionFactory $retailerCollectionFactory,
        ConfigurationHelper $configurationHelper,
        StoreManagerInterface $storeManager,
        \Smile\StoreLocator\Helper\Data $storeLocatorHelper,
        $type = self::AUTOCOMPLETE_TYPE
    ) {
        $this->itemFactory          = $itemFactory;
        $this->queryFactory         = $queryFactory;
        $this->termDataProvider     = $termDataProvider;
        $this->retailerCollectionFactory = $retailerCollectionFactory;
        $this->configurationHelper  = $configurationHelper;
        $this->type                 = $type;
        $this->storeManager         = $storeManager;
        $this->storeLocatorHelper   = $storeLocatorHelper;
    }
    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * {@inheritDoc}
     */
    public function getItems()
    {
        $result = [];
        $retailerCollection = $this->getRetailerCollection();
        if ($retailerCollection) {
            foreach ($retailerCollection as $retailer) {
                $result[] = $this->itemFactory->create(
                    [
                        'title' => $retailer->getName(),
                        'url'   => $this->storeLocatorHelper->getRetailerUrl($retailer),
                        'type'  => $this->getType(),
                    ]
                );
            }
        }

        return $result;
    }

    /**
     * List of search terms suggested by the search terms data daprovider.
     *
     * @return array
     */
    private function getSuggestedTerms()
    {
        $terms = array_map(
            function (\Magento\Search\Model\Autocomplete\Item $termItem) {
                return $termItem->getTitle();
            },
            $this->termDataProvider->getItems()
        );

        return $terms;
    }

    /**
     * Suggested retailer collection.
     * Returns null if no suggested search terms.
     *
     * @return \Smile\ElasticsuiteRetailer\Model\ResourceModel\Fulltext\Collection|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getRetailerCollection()
    {
        $retailerCollection = null;
        $suggestedTerms = $this->getSuggestedTerms();
        $terms          = [$this->queryFactory->get()->getQueryText()];

        if (!empty($suggestedTerms)) {
            $terms = array_merge($terms, $suggestedTerms);
        }

        $retailerCollection = $this->retailerCollectionFactory->create();
        $retailerCollection->addAttributeToSelect(['name', 'url_key']);
        $retailerCollection->addSearchFilter($terms);
        $retailerCollection->setPageSize($this->getResultsPageSize());

        return $retailerCollection;
    }

    /**
     * Retrieve number of retailers to display in autocomplete results
     *
     * @return int
     */
    private function getResultsPageSize()
    {
        return $this->configurationHelper->getMaxSize($this->getType());
    }
}
