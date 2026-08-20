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

use BroCode\ConfigExplorer\Api\ConfigEntryRepositoryInterface;
use BroCode\ConfigExplorer\Api\Data\ConfigEntryInterface;
use BroCode\ConfigExplorer\Api\Data\ConfigEntryInterfaceFactory;
use BroCode\ConfigExplorer\Model\Config\EncryptedPathResolver;
use BroCode\ConfigExplorer\Model\Config\ValueOrigin;
use BroCode\ConfigExplorer\Model\Config\ValueOriginResolver;
use BroCode\ConfigExplorer\Model\ResourceModel\ConfigData\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\AuthorizationException;

/**
 * getList() mirrors the effective value Magento actually serves, the same way the
 * admin grid does (see DataProvider::getData()) - a core_config_data row shadowed by
 * app/etc/config.php or app/etc/env.php reports the file's value, not the DB row's,
 * with the DB row surfaced separately via getDbValue()/getOriginSource() so a caller
 * can tell the two apart instead of silently getting a value nobody actually uses.
 */
class ConfigEntryRepository implements ConfigEntryRepositoryInterface
{
    /**
     * Site-wide kill switch. Off means nobody reveals anything, whatever their role.
     */
    public const XML_PATH_ALLOW_ENCRYPTED_REVEAL = 'brocode_config_explorer/general/allow_encrypted_reveal';

    /**
     * ACL resource granted to no role by default.
     */
    public const ACL_RESOURCE_REVEAL = 'BroCode_ConfigExplorer::config_view_encrypted';

    public const REDACTED_PLACEHOLDER = '***';

    private const SCOPE_DEFAULT = 'default';

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var ConfigEntryInterfaceFactory
     */
    private $configEntryFactory;

    /**
     * @var EncryptedPathResolver
     */
    private $encryptedPathResolver;

    /**
     * @var ValueOriginResolver
     */
    private $valueOriginResolver;

    /**
     * @var AuthorizationInterface
     */
    private $authorization;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    public function __construct(
        CollectionFactory $collectionFactory,
        ConfigEntryInterfaceFactory $configEntryFactory,
        EncryptedPathResolver $encryptedPathResolver,
        ValueOriginResolver $valueOriginResolver,
        AuthorizationInterface $authorization,
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->configEntryFactory = $configEntryFactory;
        $this->encryptedPathResolver = $encryptedPathResolver;
        $this->valueOriginResolver = $valueOriginResolver;
        $this->authorization = $authorization;
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    /**
     * @inheritDoc
     */
    public function getList(
        ?string $path = null,
        ?string $scope = null,
        ?int $scopeId = null,
        bool $revealEncrypted = false
    ): array {
        if ($revealEncrypted) {
            $this->assertRevealAllowed();
        }

        $collection = $this->collectionFactory->create();

        if ($path !== null && $path !== '') {
            $collection->addFieldToFilter('path', ['like' => '%' . $path . '%']);
        }

        if ($scope !== null && $scope !== '') {
            $collection->addFieldToFilter('scope', $scope);
        }

        if ($scopeId !== null && $scope !== null && $scope !== self::SCOPE_DEFAULT) {
            $collection->addFieldToFilter('scope_id', $scopeId);
        }

        $entries = [];

        foreach ($collection->getItems() as $item) {
            $entries[] = $this->buildEntry($item, $revealEncrypted);
        }

        return $entries;
    }

    /**
     * @param \BroCode\ConfigExplorer\Model\ConfigData $item
     * @return ConfigEntryInterface
     */
    private function buildEntry($item, bool $revealEncrypted): ConfigEntryInterface
    {
        $path = (string)$item->getData('path');
        $scope = (string)$item->getData('scope');
        $scopeId = (int)$item->getData('scope_id');
        $isEncrypted = $this->encryptedPathResolver->isEncrypted($path);
        $rawDbValue = $item->getData('value');
        $rawDbValue = $rawDbValue === null ? null : (string)$rawDbValue;

        $origin = $this->valueOriginResolver->resolve($path, $scope, $scopeId);
        [$value, $dbValue] = $this->resolveValues($isEncrypted, $revealEncrypted, $rawDbValue, $origin);

        /** @var ConfigEntryInterface $entry */
        $entry = $this->configEntryFactory->create();
        $entry->setConfigId((int)$item->getData('config_id'))
            ->setPath($path)
            ->setScope($scope)
            ->setScopeId($scopeId)
            ->setValue($value)
            ->setIsEncrypted($isEncrypted)
            ->setOriginSource($origin !== null ? $origin->getFileName() : null)
            ->setDbValue($dbValue);

        return $entry;
    }

    /**
     * The effective value (DB row, unless a deployment-config file shadows it) and,
     * only when something does shadow it, the DB row's own value alongside it.
     * Encrypted paths redact both regardless of reveal, unless reveal was granted -
     * in which case whichever ciphertext is actually in play (the shadowing file's,
     * if there is one, otherwise the DB row's) gets decrypted, same as the DB row
     * would have been before this method existed.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveValues(
        bool $isEncrypted,
        bool $revealEncrypted,
        ?string $rawDbValue,
        ?ValueOrigin $origin
    ): array {
        $effectiveRaw = $origin !== null ? (string)$origin->getValue() : $rawDbValue;

        if (!$isEncrypted) {
            return [$effectiveRaw, $origin !== null ? $rawDbValue : null];
        }

        if (!$revealEncrypted) {
            return [self::REDACTED_PLACEHOLDER, $origin !== null ? self::REDACTED_PLACEHOLDER : null];
        }

        // Encryptor::decrypt() returns '' rather than throwing when the key version
        // the ciphertext asks for is missing (e.g. after a key rotation) - that empty
        // string is indistinguishable here from a genuinely empty value.
        $value = $effectiveRaw === null ? null : $this->encryptor->decrypt($effectiveRaw);
        $dbValue = $origin === null || $rawDbValue === null ? null : $this->encryptor->decrypt($rawDbValue);

        return [$value, $origin !== null ? $dbValue : null];
    }

    /**
     * Both gates are checked, and the toggle is checked first: a caller who holds the
     * ACL resource on a store where the toggle is off still gets refused.
     *
     * @return void
     * @throws AuthorizationException
     */
    private function assertRevealAllowed(): void
    {
        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_ALLOW_ENCRYPTED_REVEAL)) {
            throw new AuthorizationException(
                __('Revealing encrypted configuration values is disabled for this installation.')
            );
        }

        if (!$this->authorization->isAllowed(self::ACL_RESOURCE_REVEAL)) {
            throw new AuthorizationException(
                __('You are not allowed to reveal encrypted configuration values.')
            );
        }
    }
}
