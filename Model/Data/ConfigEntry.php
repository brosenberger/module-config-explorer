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

namespace BroCode\ConfigExplorer\Model\Data;

use BroCode\ConfigExplorer\Api\Data\ConfigEntryInterface;
use Magento\Framework\DataObject;

class ConfigEntry extends DataObject implements ConfigEntryInterface
{
    public function getConfigId(): int
    {
        return (int)$this->getData(self::CONFIG_ID);
    }

    public function setConfigId(int $configId): ConfigEntryInterface
    {
        $this->setData(self::CONFIG_ID, $configId);

        return $this;
    }

    public function getPath(): string
    {
        return (string)$this->getData(self::PATH);
    }

    public function setPath(string $path): ConfigEntryInterface
    {
        $this->setData(self::PATH, $path);

        return $this;
    }

    public function getScope(): string
    {
        return (string)$this->getData(self::SCOPE);
    }

    public function setScope(string $scope): ConfigEntryInterface
    {
        $this->setData(self::SCOPE, $scope);

        return $this;
    }

    public function getScopeId(): int
    {
        return (int)$this->getData(self::SCOPE_ID);
    }

    public function setScopeId(int $scopeId): ConfigEntryInterface
    {
        $this->setData(self::SCOPE_ID, $scopeId);

        return $this;
    }

    public function getValue(): ?string
    {
        $value = $this->getData(self::VALUE);

        return $value === null ? null : (string)$value;
    }

    public function setValue(?string $value): ConfigEntryInterface
    {
        $this->setData(self::VALUE, $value);

        return $this;
    }

    public function getIsEncrypted(): bool
    {
        return (bool)$this->getData(self::IS_ENCRYPTED);
    }

    public function setIsEncrypted(bool $isEncrypted): ConfigEntryInterface
    {
        $this->setData(self::IS_ENCRYPTED, $isEncrypted);

        return $this;
    }

    public function getOriginSource(): ?string
    {
        $originSource = $this->getData(self::ORIGIN_SOURCE);

        return $originSource === null ? null : (string)$originSource;
    }

    public function setOriginSource(?string $originSource): ConfigEntryInterface
    {
        $this->setData(self::ORIGIN_SOURCE, $originSource);

        return $this;
    }

    public function getDbValue(): ?string
    {
        $dbValue = $this->getData(self::DB_VALUE);

        return $dbValue === null ? null : (string)$dbValue;
    }

    public function setDbValue(?string $dbValue): ConfigEntryInterface
    {
        $this->setData(self::DB_VALUE, $dbValue);

        return $this;
    }
}
