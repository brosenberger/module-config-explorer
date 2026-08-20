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

namespace BroCode\ConfigExplorer\Model;

use BroCode\ConfigExplorer\Model\Config\EncryptedPathResolver;
use BroCode\ConfigExplorer\Model\Config\StoreScopeResolver;
use BroCode\ConfigExplorer\Model\Config\ValueOriginResolver;
use BroCode\ConfigExplorer\Model\ResourceModel\ConfigData\CollectionFactory;
use Magento\Framework\Api\Filter;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * Grid data source.
 *
 * Unlike the REST endpoint this has no reveal parameter at all: the grid always
 * redacts, whatever the current user's ACL says. Plaintext stays behind an explicit
 * API call that leaves a trace, rather than a checkbox somebody leaves on in a shared
 * admin session.
 *
 * Two independent, coexisting ways to scope the grid: the AJAX "Effective Scope"
 * toolbar filter (addFilter() below), and the classic Magento\Backend\Block\Store\
 * Switcher in the page header - the same block the admin Dashboard uses - which
 * communicates by a full page reload setting a "store" request param rather than an
 * AJAX filter request. The constructor reads that param directly, once, since the
 * switcher block itself has no way to reach into this class's own filter cycle.
 */
class DataProvider extends AbstractDataProvider
{
    /**
     * Filter field for the scope switcher. Not a real core_config_data column, so it
     * cannot go through the default addFieldToFilter() path - see addFilter().
     */
    private const EFFECTIVE_SCOPE_FIELD = 'effective_scope';

    /**
     * Request param Magento\Backend\Block\Store\Switcher writes on reload, using its
     * own default store_var_name - see the block's getStoreVarName().
     */
    private const STORE_SWITCHER_PARAM = 'store';

    private const SCOPE_DEFAULT = 'default';
    private const SCOPE_WEBSITE = 'websites';
    private const SCOPE_STORE = 'stores';

    private const ORIGIN_DATABASE_LABEL = 'Database';

    /**
     * @var EncryptedPathResolver
     */
    private $encryptedPathResolver;

    /**
     * @var ValueOriginResolver
     */
    private $valueOriginResolver;

    /**
     * @var StoreScopeResolver
     */
    private $storeScopeResolver;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param EncryptedPathResolver $encryptedPathResolver
     * @param ValueOriginResolver $valueOriginResolver
     * @param StoreScopeResolver $storeScopeResolver
     * @param RequestInterface $request
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        EncryptedPathResolver $encryptedPathResolver,
        ValueOriginResolver $valueOriginResolver,
        StoreScopeResolver $storeScopeResolver,
        RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
        $this->encryptedPathResolver = $encryptedPathResolver;
        $this->valueOriginResolver = $valueOriginResolver;
        $this->storeScopeResolver = $storeScopeResolver;

        $storeId = (int)$request->getParam(self::STORE_SWITCHER_PARAM);
        if ($storeId > 0) {
            $this->collection->filterByEffectiveScope(self::SCOPE_STORE, $storeId);
        }
    }

    /**
     * @return array
     */
    public function getData()
    {
        $data = parent::getData();

        if (!isset($data['items']) || !is_array($data['items'])) {
            return $data;
        }

        foreach ($data['items'] as $key => $item) {
            $path = (string)($item['path'] ?? '');
            $isEncrypted = $this->encryptedPathResolver->isEncrypted($path);
            $data['items'][$key]['is_encrypted'] = $isEncrypted ? 1 : 0;

            $rawDbValue = $item['value'] ?? null;
            $origin = $this->valueOriginResolver->resolve(
                $path,
                (string)($item['scope'] ?? ''),
                (int)($item['scope_id'] ?? 0)
            );

            if ($isEncrypted) {
                $data['items'][$key]['value'] = ConfigEntryRepository::REDACTED_PLACEHOLDER;
                if ($origin !== null) {
                    $data['items'][$key]['db_value'] = ConfigEntryRepository::REDACTED_PLACEHOLDER;
                }
            } elseif ($origin !== null) {
                $data['items'][$key]['value'] = $origin->getValue();
                $data['items'][$key]['db_value'] = $rawDbValue;
            }

            $data['items'][$key]['origin_source'] = $origin !== null ? $origin->getFileName() : null;
            // Separate from origin_source: the icon's tooltip logic keys off
            // origin_source being null ("nothing to show"), but the grid column
            // needs a non-empty label for every row, "Database" included.
            $data['items'][$key]['origin_label'] = $origin !== null ? $origin->getFileName() : self::ORIGIN_DATABASE_LABEL;
            $data['items'][$key]['scope_code'] = $this->resolveScopeCode(
                (string)($item['scope'] ?? ''),
                (int)($item['scope_id'] ?? 0)
            );
        }

        return $data;
    }

    /**
     * The website/store code behind a row's scope_id, or null for default scope
     * (which has no code of its own) or a scope_id that no longer resolves (a stale
     * row left behind by a deleted website/store).
     */
    private function resolveScopeCode(string $scope, int $scopeId): ?string
    {
        if ($scope === self::SCOPE_WEBSITE) {
            return $this->storeScopeResolver->getWebsiteCode($scopeId);
        }

        if ($scope === self::SCOPE_STORE) {
            return $this->storeScopeResolver->getStoreCode($scopeId);
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function addFilter(Filter $filter)
    {
        if ($filter->getField() === self::EFFECTIVE_SCOPE_FIELD) {
            $this->applyEffectiveScopeFilter((string)$filter->getValue());

            return;
        }

        parent::addFilter($filter);
    }

    /**
     * Decodes the scope switcher's encoded option value ("default", "websites:1",
     * "stores:2") and forwards it to the collection's inheritance-aware filter.
     */
    private function applyEffectiveScopeFilter(string $value): void
    {
        if ($value === '') {
            return;
        }

        if ($value === self::SCOPE_DEFAULT) {
            $this->getCollection()->filterByEffectiveScope(self::SCOPE_DEFAULT, null);

            return;
        }

        [$scope, $scopeId] = array_pad(explode(':', $value, 2), 2, null);

        if ($scope === null || $scopeId === null || !is_numeric($scopeId)) {
            return;
        }

        $this->getCollection()->filterByEffectiveScope($scope, (int)$scopeId);
    }
}
