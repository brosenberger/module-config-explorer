<?php
/**
 * Copyright (C) 2026 Benjamin Rosenberger <bensch.rosenberger@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @copyright 2026 Benjamin Rosenberger
 * @author bensch.rosenberger@gmail.com
 * @license MIT
 * @link https://brocode.at
 */
declare(strict_types=1);

namespace BroCode\ConfigExplorer\Model\ResourceModel\ConfigData;

use BroCode\ConfigExplorer\Model\Config\StoreScopeResolver;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Psr\Log\LoggerInterface;

/**
 * Read-only collection over core_config_data.
 */
class Collection extends AbstractCollection
{
    private const SCOPE_DEFAULT = 'default';
    private const SCOPE_WEBSITE = 'websites';
    private const SCOPE_STORE = 'stores';

    /**
     * @var StoreScopeResolver
     */
    private $storeScopeResolver;

    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        StoreScopeResolver $storeScopeResolver,
        ?AdapterInterface $connection = null,
        ?AbstractDb $resource = null
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
        $this->storeScopeResolver = $storeScopeResolver;
    }

    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            \BroCode\ConfigExplorer\Model\ConfigData::class,
            \BroCode\ConfigExplorer\Model\ResourceModel\ConfigData::class
        );
    }

    /**
     * Break ties on the sorted column with the primary key so LIMIT/OFFSET paging stays
     * deterministic. Without this, sorting by a non-unique column (path, scope, value)
     * can return the same row on two pages while another row is skipped.
     *
     * @return $this
     */
    protected function _renderOrders()
    {
        $idField = $this->getResource()->getIdFieldName();
        if (!isset($this->_orders[$idField])) {
            $this->_orders[$idField] = self::SORT_ORDER_ASC;
        }

        return parent::_renderOrders();
    }

    /**
     * Reduces the row set to what is actually "in play" at the given scope, using
     * real Magento scope inheritance rather than a flat scope/scope_id equality
     * filter: a website shows its own rows, every descendant store view's own rows,
     * and default rows not already shadowed by this website; a store view shows its
     * own rows, website rows not shadowed by it, and default rows not shadowed by
     * either level.
     *
     * Expressed as a single query with correlated NOT EXISTS subqueries - the row
     * that would otherwise be shadowed simply never enters the result set - rather
     * than two passes in PHP.
     *
     * @return $this
     */
    public function filterByEffectiveScope(string $scope, ?int $scopeId): self
    {
        if ($scope === self::SCOPE_DEFAULT) {
            $this->addFieldToFilter('scope', self::SCOPE_DEFAULT);

            return $this;
        }

        if ($scopeId === null) {
            return $this;
        }

        $conditions = null;

        if ($scope === self::SCOPE_WEBSITE) {
            $conditions = $this->websiteScopeConditions($scopeId);
        } elseif ($scope === self::SCOPE_STORE) {
            $conditions = $this->storeScopeConditions($scopeId);
        }

        if ($conditions !== null) {
            $this->getSelect()->where(implode(' OR ', $conditions));
        }

        return $this;
    }

    /**
     * @return string[]
     */
    private function websiteScopeConditions(int $websiteId): array
    {
        return [
            $this->scopeEquals(self::SCOPE_WEBSITE, $websiteId),
            $this->scopeIn(self::SCOPE_STORE, $this->storeScopeResolver->getStoreIdsForWebsite($websiteId)),
            $this->scopeEqualsAndNotShadowedAt(self::SCOPE_DEFAULT, null, self::SCOPE_WEBSITE, $websiteId),
        ];
    }

    /**
     * @return string[]
     */
    private function storeScopeConditions(int $storeId): array
    {
        $websiteId = $this->storeScopeResolver->getWebsiteIdForStore($storeId);

        return [
            $this->scopeEquals(self::SCOPE_STORE, $storeId),
            $this->scopeEqualsAndNotShadowedAt(self::SCOPE_WEBSITE, $websiteId, self::SCOPE_STORE, $storeId),
            $this->scopeEqualsAndNotShadowedAt(self::SCOPE_DEFAULT, null, self::SCOPE_STORE, $storeId)
                . ' AND ' . $this->notShadowedAt(self::SCOPE_WEBSITE, $websiteId),
        ];
    }

    /**
     * @return string
     */
    private function scopeEquals(string $scope, int $scopeId): string
    {
        $connection = $this->getConnection();

        return sprintf(
            '(scope = %s AND scope_id = %d)',
            $connection->quote($scope),
            $scopeId
        );
    }

    /**
     * @param int[] $scopeIds
     * @return string
     */
    private function scopeIn(string $scope, array $scopeIds): string
    {
        $connection = $this->getConnection();

        if ($scopeIds === []) {
            // No stores under this website: the IN() branch must contribute nothing,
            // not throw on an empty list.
            return '(1 = 0)';
        }

        return sprintf(
            '(scope = %s AND scope_id IN (%s))',
            $connection->quote($scope),
            implode(',', array_map('intval', $scopeIds))
        );
    }

    /**
     * A scope=$scope (optionally scope_id=$scopeId, when not null) condition, further
     * restricted to rows not shadowed at $shadowScope/$shadowScopeId.
     *
     * @return string
     */
    private function scopeEqualsAndNotShadowedAt(
        string $scope,
        ?int $scopeId,
        string $shadowScope,
        int $shadowScopeId
    ): string {
        $connection = $this->getConnection();

        $scopeCondition = $scopeId === null
            ? sprintf('scope = %s', $connection->quote($scope))
            : sprintf('scope = %s AND scope_id = %d', $connection->quote($scope), $scopeId);

        return sprintf(
            '(%s AND %s)',
            $scopeCondition,
            $this->notShadowedAt($shadowScope, $shadowScopeId)
        );
    }

    /**
     * A correlated NOT EXISTS fragment: true when no row at $scope/$scopeId shares
     * this row's path.
     *
     * @return string
     */
    private function notShadowedAt(string $scope, int $scopeId): string
    {
        $connection = $this->getConnection();

        return sprintf(
            'NOT EXISTS (SELECT 1 FROM %s AS ov WHERE ov.path = main_table.path AND ov.scope = %s AND ov.scope_id = %d)',
            $connection->quoteIdentifier($this->getMainTable()),
            $connection->quote($scope),
            $scopeId
        );
    }
}
