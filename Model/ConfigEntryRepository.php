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
use BroCode\ConfigExplorer\Model\ResourceModel\ConfigData\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\AuthorizationException;

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
        AuthorizationInterface $authorization,
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->configEntryFactory = $configEntryFactory;
        $this->encryptedPathResolver = $encryptedPathResolver;
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
            $itemPath = (string)$item->getData('path');
            $isEncrypted = $this->encryptedPathResolver->isEncrypted($itemPath);
            $rawValue = $item->getData('value');

            if ($isEncrypted && !$revealEncrypted) {
                $value = self::REDACTED_PLACEHOLDER;
            } elseif ($isEncrypted && $revealEncrypted) {
                // Encryptor::decrypt() returns '' rather than throwing when the key
                // version the ciphertext asks for is missing (e.g. after a key
                // rotation) - that empty string is indistinguishable here from a
                // genuinely empty value.
                $value = $rawValue === null ? null : $this->encryptor->decrypt((string)$rawValue);
            } else {
                $value = $rawValue === null ? null : (string)$rawValue;
            }

            /** @var ConfigEntryInterface $entry */
            $entry = $this->configEntryFactory->create();
            $entry->setConfigId((int)$item->getData('config_id'))
                ->setPath($itemPath)
                ->setScope((string)$item->getData('scope'))
                ->setScopeId((int)$item->getData('scope_id'))
                ->setValue($value)
                ->setIsEncrypted($isEncrypted);

            $entries[] = $entry;
        }

        return $entries;
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
