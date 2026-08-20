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

namespace BroCode\ConfigExplorer\Test\Unit\Model\Config;

use BroCode\ConfigExplorer\Model\Config\StoreScopeResolver;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use PHPUnit\Framework\TestCase;

class StoreScopeResolverTest extends TestCase
{
    public function testGetStoreIdsForWebsiteFiltersByWebsiteId(): void
    {
        $storeA = $this->createMock(StoreInterface::class);
        $storeA->method('getWebsiteId')->willReturn(1);
        $storeA->method('getId')->willReturn(10);

        $storeB = $this->createMock(StoreInterface::class);
        $storeB->method('getWebsiteId')->willReturn(2);
        $storeB->method('getId')->willReturn(20);

        $storeRepository = $this->createMock(StoreRepositoryInterface::class);
        $storeRepository->method('getList')->willReturn([$storeA, $storeB]);

        $resolver = new StoreScopeResolver($storeRepository, $this->createMock(WebsiteRepositoryInterface::class));

        self::assertSame([10], $resolver->getStoreIdsForWebsite(1));
    }

    public function testGetWebsiteIdForStore(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(5);

        $storeRepository = $this->createMock(StoreRepositoryInterface::class);
        $storeRepository->method('getById')->with(3)->willReturn($store);

        $resolver = new StoreScopeResolver($storeRepository, $this->createMock(WebsiteRepositoryInterface::class));

        self::assertSame(5, $resolver->getWebsiteIdForStore(3));
    }

    public function testGetWebsiteCodeReturnsCode(): void
    {
        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getCode')->willReturn('base');

        $websiteRepository = $this->createMock(WebsiteRepositoryInterface::class);
        $websiteRepository->method('getById')->with(1)->willReturn($website);

        $resolver = new StoreScopeResolver($this->createMock(StoreRepositoryInterface::class), $websiteRepository);

        self::assertSame('base', $resolver->getWebsiteCode(1));
    }

    public function testGetWebsiteCodeReturnsNullForStaleId(): void
    {
        $websiteRepository = $this->createMock(WebsiteRepositoryInterface::class);
        $websiteRepository->method('getById')->willThrowException(new NoSuchEntityException(new Phrase('gone')));

        $resolver = new StoreScopeResolver($this->createMock(StoreRepositoryInterface::class), $websiteRepository);

        self::assertNull($resolver->getWebsiteCode(999));
    }

    public function testGetStoreCodeReturnsCode(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getCode')->willReturn('zurich_view');

        $storeRepository = $this->createMock(StoreRepositoryInterface::class);
        $storeRepository->method('getById')->with(2)->willReturn($store);

        $resolver = new StoreScopeResolver($storeRepository, $this->createMock(WebsiteRepositoryInterface::class));

        self::assertSame('zurich_view', $resolver->getStoreCode(2));
    }

    public function testGetStoreCodeReturnsNullForStaleId(): void
    {
        $storeRepository = $this->createMock(StoreRepositoryInterface::class);
        $storeRepository->method('getById')->willThrowException(new NoSuchEntityException(new Phrase('gone')));

        $resolver = new StoreScopeResolver($storeRepository, $this->createMock(WebsiteRepositoryInterface::class));

        self::assertNull($resolver->getStoreCode(999));
    }
}
