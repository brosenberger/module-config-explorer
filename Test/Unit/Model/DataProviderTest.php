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

namespace BroCode\ConfigExplorer\Test\Unit\Model;

use BroCode\ConfigExplorer\Model\Config\EncryptedPathResolver;
use BroCode\ConfigExplorer\Model\Config\ValueOrigin;
use BroCode\ConfigExplorer\Model\Config\ValueOriginResolver;
use BroCode\ConfigExplorer\Model\ConfigEntryRepository;
use BroCode\ConfigExplorer\Model\DataProvider;
use BroCode\ConfigExplorer\Model\ResourceModel\ConfigData\Collection;
use BroCode\ConfigExplorer\Model\ResourceModel\ConfigData\CollectionFactory;
use Magento\Framework\Api\Filter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Covers the two things DataProvider adds on top of the raw collection data: the
 * per-row origin enrichment in getData() (and its interaction with the existing
 * encrypted-value redaction), and the addFilter() override that routes the scope
 * switcher's virtual "effective_scope" field to Collection::filterByEffectiveScope()
 * instead of the default addFieldToFilter() path (which has no such column).
 */
class DataProviderTest extends TestCase
{
    public function testRowWithoutOriginIsUntouched(): void
    {
        $collection = $this->collectionReturning([
            ['config_id' => 1, 'path' => 'general/locale/code', 'scope' => 'default', 'scope_id' => 0, 'value' => 'en_US'],
        ]);

        $data = $this->provider($collection, false, null)->getData();

        self::assertSame('en_US', $data['items'][0]['value']);
        self::assertArrayNotHasKey('db_value', $data['items'][0]);
        self::assertNull($data['items'][0]['origin_source']);
        self::assertSame('Database', $data['items'][0]['origin_label']);
        self::assertSame(0, $data['items'][0]['is_encrypted']);
    }

    public function testOriginNotEncryptedShowsEffectiveValueAndShadowedDbValue(): void
    {
        $collection = $this->collectionReturning([
            [
                'config_id' => 1,
                'path' => 'web/unsecure/base_url',
                'scope' => 'default',
                'scope_id' => 0,
                'value' => 'https://db-value.test/',
            ],
        ]);

        $origin = new ValueOrigin('app_env', 'env.php', 'https://env-value.test/');
        $data = $this->provider($collection, false, $origin)->getData();

        self::assertSame('https://env-value.test/', $data['items'][0]['value']);
        self::assertSame('https://db-value.test/', $data['items'][0]['db_value']);
        self::assertSame('env.php', $data['items'][0]['origin_source']);
        self::assertSame('env.php', $data['items'][0]['origin_label']);
    }

    public function testOriginEncryptedRedactsBothValueAndShadowedDbValue(): void
    {
        $collection = $this->collectionReturning([
            [
                'config_id' => 1,
                'path' => 'carriers/usps/password',
                'scope' => 'default',
                'scope_id' => 0,
                'value' => 'ciphertext-db',
            ],
        ]);

        $origin = new ValueOrigin('app_env', 'env.php', 'ciphertext-env');
        $data = $this->provider($collection, true, $origin)->getData();

        self::assertSame(ConfigEntryRepository::REDACTED_PLACEHOLDER, $data['items'][0]['value']);
        self::assertSame(ConfigEntryRepository::REDACTED_PLACEHOLDER, $data['items'][0]['db_value']);
        self::assertSame('env.php', $data['items'][0]['origin_source']);
    }

    public function testAddFilterWithEffectiveScopeFieldDecodesWebsiteValue(): void
    {
        $collection = $this->collectionReturning([]);
        $collection->expects(self::once())->method('filterByEffectiveScope')->with('websites', 3);
        $collection->expects(self::never())->method('addFieldToFilter');

        $this->provider($collection, false, null)->addFilter(
            (new Filter())->setField('effective_scope')->setValue('websites:3')->setConditionType('eq')
        );
    }

    public function testAddFilterWithEffectiveScopeFieldDecodesDefaultValue(): void
    {
        $collection = $this->collectionReturning([]);
        $collection->expects(self::once())->method('filterByEffectiveScope')->with('default', null);

        $this->provider($collection, false, null)->addFilter(
            (new Filter())->setField('effective_scope')->setValue('default')->setConditionType('eq')
        );
    }

    public function testAddFilterWithEmptyEffectiveScopeValueIsNoOp(): void
    {
        $collection = $this->collectionReturning([]);
        $collection->expects(self::never())->method('filterByEffectiveScope');
        $collection->expects(self::never())->method('addFieldToFilter');

        $this->provider($collection, false, null)->addFilter(
            (new Filter())->setField('effective_scope')->setValue('')->setConditionType('eq')
        );
    }

    public function testAddFilterWithOtherFieldsDelegatesToParent(): void
    {
        $collection = $this->collectionReturning([]);
        $collection->expects(self::once())->method('addFieldToFilter')->with('path', ['like' => '%foo%']);
        $collection->expects(self::never())->method('filterByEffectiveScope');

        $this->provider($collection, false, null)->addFilter(
            (new Filter())->setField('path')->setValue('%foo%')->setConditionType('like')
        );
    }

    /**
     * @return Collection&MockObject
     */
    private function collectionReturning(array $items): Collection
    {
        $collection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['toArray', 'addFieldToFilter', 'filterByEffectiveScope'])
            ->getMock();

        $collection->method('toArray')->willReturn([
            'totalRecords' => count($items),
            'items' => $items,
        ]);

        return $collection;
    }

    private function provider(Collection $collection, bool $isEncrypted, ?ValueOrigin $origin): DataProvider
    {
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $encryptedPathResolver = $this->createMock(EncryptedPathResolver::class);
        $encryptedPathResolver->method('isEncrypted')->willReturn($isEncrypted);

        $valueOriginResolver = $this->createMock(ValueOriginResolver::class);
        $valueOriginResolver->method('resolve')->willReturn($origin);

        return new DataProvider(
            'brocode_configexplorer_listing_data_source',
            'config_id',
            'id',
            $collectionFactory,
            $encryptedPathResolver,
            $valueOriginResolver
        );
    }
}
