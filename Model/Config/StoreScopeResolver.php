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

namespace BroCode\ConfigExplorer\Model\Config;

use Magento\Store\Api\StoreRepositoryInterface;

/**
 * Resolves website/store-view scope relationships for the grid's scope filter.
 *
 * WebsiteInterface has no getStoreIds() of its own, so the website->stores direction
 * is derived by scanning StoreRepositoryInterface::getList() and matching
 * getWebsiteId() - the same thing the concrete \Magento\Store\Model\Website::
 * getStoreIds() does internally, kept here against the interface instead of the
 * concrete class.
 */
class StoreScopeResolver
{
    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    public function __construct(StoreRepositoryInterface $storeRepository)
    {
        $this->storeRepository = $storeRepository;
    }

    /**
     * @return int[]
     */
    public function getStoreIdsForWebsite(int $websiteId): array
    {
        $storeIds = [];

        foreach ($this->storeRepository->getList() as $store) {
            if ((int)$store->getWebsiteId() === $websiteId) {
                $storeIds[] = (int)$store->getId();
            }
        }

        return $storeIds;
    }

    public function getWebsiteIdForStore(int $storeId): int
    {
        return (int)$this->storeRepository->getById($storeId)->getWebsiteId();
    }
}
