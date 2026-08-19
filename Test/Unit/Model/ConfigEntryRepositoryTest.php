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

use BroCode\ConfigExplorer\Api\Data\ConfigEntryInterface;
use BroCode\ConfigExplorer\Api\Data\ConfigEntryInterfaceFactory;
use BroCode\ConfigExplorer\Model\Config\EncryptedPathResolver;
use BroCode\ConfigExplorer\Model\ConfigEntryRepository;
use BroCode\ConfigExplorer\Model\Data\ConfigEntry;
use BroCode\ConfigExplorer\Model\ResourceModel\ConfigData\Collection;
use BroCode\ConfigExplorer\Model\ResourceModel\ConfigData\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\AuthorizationException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for two bugs a live-instance verification pass found:
 *
 * 1. getList() returned the raw stored ciphertext (never called the encryptor)
 *    even when reveal was fully authorized - see
 *    testEncryptedValueIsDecryptedWhenRevealIsGranted().
 * 2. Separately, EncryptedPathResolver detected nothing at all outside the
 *    adminhtml area because of a di.xml scoping bug - covered in
 *    \BroCode\ConfigExplorer\Test\Unit\Etc\DiConfigTest, not here, since that
 *    one is a DI-wiring defect rather than something this repository's own
 *    logic could catch in isolation.
 */
class ConfigEntryRepositoryTest extends TestCase
{
    private const PATH = 'carriers/usps/password';
    private const CIPHERTEXT = '0:3:m2jyiB6xxlxSv0K7VWnK8s0CoNcfmHy0aU6o1CEn4BW/ZPiig9bA2q0zzsaFLL/ffsE=';

    private CollectionFactory&MockObject $collectionFactory;
    private ConfigEntryInterfaceFactory&MockObject $entryFactory;
    private EncryptedPathResolver&MockObject $resolver;
    private AuthorizationInterface&MockObject $authorization;
    private ScopeConfigInterface&MockObject $scopeConfig;
    private EncryptorInterface&MockObject $encryptor;

    protected function setUp(): void
    {
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->entryFactory = $this->createMock(ConfigEntryInterfaceFactory::class);
        $this->entryFactory->method('create')->willReturnCallback(
            static fn (): ConfigEntryInterface => new ConfigEntry()
        );
        $this->resolver = $this->createMock(EncryptedPathResolver::class);
        $this->authorization = $this->createMock(AuthorizationInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
    }

    private function repository(): ConfigEntryRepository
    {
        return new ConfigEntryRepository(
            $this->collectionFactory,
            $this->entryFactory,
            $this->resolver,
            $this->authorization,
            $this->scopeConfig,
            $this->encryptor
        );
    }

    /**
     * @param string $path
     * @param string $storedValue
     */
    private function givenOneRow(string $path, string $storedValue): void
    {
        $item = new DataObject([
            'config_id' => 14,
            'path' => $path,
            'scope' => 'default',
            'scope_id' => 0,
            'value' => $storedValue,
        ]);

        $collection = $this->createMock(Collection::class);
        $collection->method('getItems')->willReturn([$item]);
        $this->collectionFactory->method('create')->willReturn($collection);
    }

    public function testEncryptedValueIsRedactedByDefault(): void
    {
        $this->givenOneRow(self::PATH, self::CIPHERTEXT);
        $this->resolver->method('isEncrypted')->willReturn(true);
        $this->encryptor->expects(self::never())->method('decrypt');

        $entries = $this->repository()->getList(self::PATH);

        self::assertSame('***', $entries[0]->getValue());
        self::assertTrue($entries[0]->getIsEncrypted());
    }

    /**
     * The bug: revealEncrypted=true, fully authorized, still returned
     * self::CIPHERTEXT verbatim instead of the plaintext the API promises.
     */
    public function testEncryptedValueIsDecryptedWhenRevealIsGranted(): void
    {
        $this->givenOneRow(self::PATH, self::CIPHERTEXT);
        $this->resolver->method('isEncrypted')->willReturn(true);
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->authorization->method('isAllowed')->willReturn(true);
        $this->encryptor->expects(self::once())
            ->method('decrypt')
            ->with(self::CIPHERTEXT)
            ->willReturn('sandbox-test-secret-42');

        $entries = $this->repository()->getList(self::PATH, null, null, true);

        self::assertSame('sandbox-test-secret-42', $entries[0]->getValue());
    }

    public function testNonEncryptedValuePassesThroughUnchanged(): void
    {
        $this->givenOneRow('general/store_information/name', 'My Store');
        $this->resolver->method('isEncrypted')->willReturn(false);
        $this->encryptor->expects(self::never())->method('decrypt');

        $entries = $this->repository()->getList('general/store_information/name');

        self::assertSame('My Store', $entries[0]->getValue());
        self::assertFalse($entries[0]->getIsEncrypted());
    }

    /**
     * The toggle is checked before the ACL resource: a caller who holds the
     * resource on an installation with the toggle off is still refused.
     */
    public function testRevealThrowsWhenKillSwitchIsOff(): void
    {
        $this->givenOneRow(self::PATH, self::CIPHERTEXT);
        $this->scopeConfig->method('isSetFlag')->willReturn(false);
        $this->authorization->expects(self::never())->method('isAllowed');

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('disabled for this installation');

        $this->repository()->getList(self::PATH, null, null, true);
    }

    public function testRevealThrowsWhenAclResourceIsMissing(): void
    {
        $this->givenOneRow(self::PATH, self::CIPHERTEXT);
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->authorization->method('isAllowed')->willReturn(false);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('not allowed to reveal');

        $this->repository()->getList(self::PATH, null, null, true);
    }
}
