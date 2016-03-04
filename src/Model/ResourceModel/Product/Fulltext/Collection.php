<?php
/**
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Elastic Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile_ElasticSuiteCatalog
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2016 Smile
 * @license   Open Software License ("OSL") v. 3.0
 */
namespace Smile\ElasticSuiteCatalog\Model\ResourceModel\Product\Fulltext;

use Magento\Framework\Profiler;

/**
 * Search engine product collection.
 *
 * @category  Smile
 * @package   Smile_ElasticSuiteCatalog
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 */
class Collection extends \Magento\Catalog\Model\ResourceModel\Product\Collection
{
    /**
     * @var QueryResponse
     */
    private $queryResponse;

    /**
     * @var \Smile\ElasticSuiteCore\Search\Request\Builder
     */
    private $requestBuilder;

    /**
     * @var \Magento\Search\Model\SearchEngine
     */
    private $searchEngine;

    /**
     * @var string
     */
    private $queryText;

    /**
     * @var string
     */
    private $searchRequestName;

    /**
     * Constructor.
     *
     * @param \Magento\Framework\Data\Collection\EntityFactory             $entityFactory           Collection entity factory
     * @param \Psr\Log\LoggerInterface                                     $logger                  Logger.
     * @param \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy           Db Fetch strategy.
     * @param \Magento\Framework\Event\ManagerInterface                    $eventManager            Event manager.
     * @param \Magento\Eav\Model\Config                                    $eavConfig               EAV configuration.
     * @param \Magento\Framework\App\ResourceConnection                    $resource                DB connection.
     * @param \Magento\Eav\Model\EntityFactory                             $eavEntityFactory        Entity factory.
     * @param \Magento\Catalog\Model\ResourceModel\Helper                  $resourceHelper          Resource helper.
     * @param \Magento\Framework\Validator\UniversalFactory                $universalFactory        Standard factory.
     * @param \Magento\Store\Model\StoreManagerInterface                   $storeManager            Store manager.
     * @param \Magento\Framework\Module\Manager                            $moduleManager           Module manager.
     * @param \Magento\Catalog\Model\Indexer\Product\Flat\State            $catalogProductFlatState Flat index state.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface           $scopeConfig             Store configuration.
     * @param \Magento\Catalog\Model\Product\OptionFactory                 $productOptionFactory    Product options factory.
     * @param \Magento\Catalog\Model\ResourceModel\Url                     $catalogUrl              Catalog URL resource model.
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface         $localeDate              Timezone helper.
     * @param \Magento\Customer\Model\Session                              $customerSession         Customer session.
     * @param \Magento\Framework\Stdlib\DateTime                           $dateTime                Datetime helper.
     * @param \Magento\Customer\Api\GroupManagementInterface               $groupManagement         Customer group manager.
     * @param \Smile\ElasticSuiteCore\Search\Request\Builder               $requestBuilder          Search request builder.
     * @param \Magento\Search\Model\SearchEngine                           $searchEngine            Search engine
     * @param \Magento\Framework\DB\Adapter\AdapterInterface               $connection              Db Connection.
     * @param string                                                       $searchRequestName       Search request name.
     */
    public function __construct(
        \Magento\Framework\Data\Collection\EntityFactory $entityFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Eav\Model\EntityFactory $eavEntityFactory,
        \Magento\Catalog\Model\ResourceModel\Helper $resourceHelper,
        \Magento\Framework\Validator\UniversalFactory $universalFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Catalog\Model\Indexer\Product\Flat\State $catalogProductFlatState,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Catalog\Model\Product\OptionFactory $productOptionFactory,
        \Magento\Catalog\Model\ResourceModel\Url $catalogUrl,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Stdlib\DateTime $dateTime,
        \Magento\Customer\Api\GroupManagementInterface $groupManagement,
        \Smile\ElasticSuiteCore\Search\Request\Builder $requestBuilder,
        \Magento\Search\Model\SearchEngine $searchEngine,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection = null,
        $searchRequestName = 'catalog_view_container'
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $eavConfig,
            $resource,
            $eavEntityFactory,
            $resourceHelper,
            $universalFactory,
            $storeManager,
            $moduleManager,
            $catalogProductFlatState,
            $scopeConfig,
            $productOptionFactory,
            $catalogUrl,
            $localeDate,
            $customerSession,
            $dateTime,
            $groupManagement,
            $connection
        );

        $this->requestBuilder    = $requestBuilder;
        $this->searchEngine      = $searchEngine;
        $this->searchRequestName = $searchRequestName;
    }

    /**
     * {@inheritDoc}
     */
    public function getSize()
    {
        if ($this->_totalRecords === null) {
            // @TODO : better fix
            $this->_totalRecords = 1;
        }

        return $this->_totalRecords;
    }

    /**
     * {@inheritDoc}
     */
    public function setOrder($attribute, $dir = Select::SQL_DESC)
    {
        $this->_orders[$attribute] = $dir;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function addFieldToFilter($field, $condition = null)
    {
        if ($field == 'category_ids') {
            $field = 'category.category_id';
        }

        if ($field == 'price') {
            $field = 'price.price';
        }

        $this->requestBuilder->addFilter($field, $condition);

        return $this;
    }

    /**
     * Add search query filter
     *
     * @param string $query Search query text.
     *
     * @return \Smile\ElasticSuiteCatalog\Model\ResourceModel\Product\Fulltext\Collection
     */
    public function addSearchFilter($query)
    {
        $this->queryText = trim($this->queryText . ' ' . $query);

        return $this;
    }

    /**
     * Return field faceted data from faceted search result.
     *
     * @param string $field Facet field.
     *
     * @return array
     */
    public function getFacetedData($field)
    {
        $this->_renderFilters();
        $result = [];
        $aggregations = $this->queryResponse->getAggregations();
        $bucket = $aggregations->getBucket($field . '_bucket');

        if ($bucket) {
            foreach ($bucket->getValues() as $value) {
                $metrics = $value->getMetrics();
                $result[$metrics['value']] = $metrics;
            }
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function addCategoryFilter(\Magento\Catalog\Model\Category $category)
    {
        $this->addFieldToFilter('category_ids', $category->getId());
        $this->_productLimitationFilters['category_ids'] = $category->getId();

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setVisibility($visibility)
    {
        $this->addFieldToFilter('visibility', $visibility);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function _renderFiltersBefore()
    {
        Profiler::start("ES: Prepare request");
        $queryRequest = $this->prepareRequest();
        Profiler::stop("ES: Prepare request");

        Profiler::start("ES: Execute request");
        $this->queryResponse = $this->searchEngine->search($queryRequest);
        Profiler::stop("ES: Execute request");

        // Update the product count.
        $this->_totalRecords = $this->queryResponse->count();

        // Filter search results. The pagination has to be resetted since it is managed by the engine itself.
        $docIds = array_map(
            function ($doc) {
                return (int) $doc->getId();
            },
            $this->queryResponse->getIterator()->getArrayCopy()
        );

        if (empty($docIds)) {
            $docIds[] = 0;
        }

        $this->getSelect()->where('e.entity_id IN (?)', ['in' => $docIds]);
        $this->_pageSize = false;

        return parent::_renderFiltersBefore();
    }

    /**
     * {@inheritDoc}
     */
    protected function _renderFilters()
    {
        $this->_filters = [];

        return parent::_renderFilters();
    }

    /**
     * {@inheritDoc}
     */
    protected function _renderOrders()
    {
        if (!$this->_isOrdersRendered) {
            foreach ($this->_orders as $attribute => $direction) {
                if ($attribute == 'position') {
                    $categoryIds  = $this->_productLimitationFilters['category_ids'];
                    $nestedPath   = 'category';
                    $nestedFilter = ['category.category_id' => $categoryIds];
                    $this->requestBuilder->addSortOrder('category.position', $direction, $nestedPath, $nestedFilter);
                } elseif ($attribute == 'price') {
                    $customerGroupId = $this->_productLimitationFilters['customer_group_id'];
                    $nestedPath   = 'price';
                    $nestedFilter = ['price.customer_group_id' => $customerGroupId];
                    $this->requestBuilder->addSortOrder('price.price', $direction, $nestedPath, $nestedFilter);
                } else {
                    $this->requestBuilder->addSortOrder($attribute, $direction);
                }
            }

            $this->_isOrdersRendered = true;
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    protected function _afterLoad()
    {
        // Resort items according the search response.
        $orginalItems = $this->_items;
        $this->_items = [];

        foreach ($this->queryResponse->getIterator() as $document) {
            $documentId = $document->getId();
            if (isset($orginalItems[$documentId])) {
                $this->_items[$documentId] = $orginalItems[$documentId];
            }
        }

        return parent::_afterLoad();
    }

    /**
     * Prepare the search request before it will be executed.
     *
     * @return void
     */
    private function prepareRequest()
    {
        // Set the right search name (eg. catalog_product_view, ...).
        $this->requestBuilder->setRequestName($this->searchRequestName);

        // Bind the current store.
        $this->requestBuilder->setStoreId($this->getStoreId());

        // For fulltext search : set the query text.
        if ($this->queryText) {
            $this->requestBuilder->setQueryText($this->queryText);
        }

        // Update pagination of the request.
        $pageSize = $this->_pageSize ? $this->_pageSize : 20;
        $curPage  = max(1, $this->_curPage);
        $this->requestBuilder->setSize($pageSize);
        $this->requestBuilder->setFrom($pageSize * ($curPage - 1));

        // Setup sort orders.
        $this->_renderOrders();

        return $this->requestBuilder->create();
    }
}
