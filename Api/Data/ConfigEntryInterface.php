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

namespace BroCode\ConfigExplorer\Api\Data;

/**
 * Read-only representation of a single core_config_data row.
 *
 * When a row is encrypted and redaction applies, getValue() returns the redaction
 * placeholder — never null and never the ciphertext — while getIsEncrypted() returns
 * true, so a consumer can tell "redacted" apart from "genuinely empty".
 */
interface ConfigEntryInterface
{
    public const CONFIG_ID = 'config_id';
    public const PATH = 'path';
    public const SCOPE = 'scope';
    public const SCOPE_ID = 'scope_id';
    public const VALUE = 'value';
    public const IS_ENCRYPTED = 'is_encrypted';

    /**
     * Primary key from core_config_data.
     *
     * @return int
     */
    public function getConfigId(): int;

    /**
     * @param int $configId
     * @return \BroCode\ConfigExplorer\Api\Data\ConfigEntryInterface
     */
    public function setConfigId(int $configId): ConfigEntryInterface;

    /**
     * Stored config path, e.g. "payment/braintree/merchant_id".
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * @param string $path
     * @return \BroCode\ConfigExplorer\Api\Data\ConfigEntryInterface
     */
    public function setPath(string $path): ConfigEntryInterface;

    /**
     * Scope type: "default", "websites", or "stores".
     *
     * @return string
     */
    public function getScope(): string;

    /**
     * @param string $scope
     * @return \BroCode\ConfigExplorer\Api\Data\ConfigEntryInterface
     */
    public function setScope(string $scope): ConfigEntryInterface;

    /**
     * Scope id. Always 0 for the default scope.
     *
     * @return int
     */
    public function getScopeId(): int;

    /**
     * @param int $scopeId
     * @return \BroCode\ConfigExplorer\Api\Data\ConfigEntryInterface
     */
    public function setScopeId(int $scopeId): ConfigEntryInterface;

    /**
     * Config value, or the redaction placeholder when getIsEncrypted() is true and
     * reveal was not granted for this request.
     *
     * @return string|null
     */
    public function getValue(): ?string;

    /**
     * @param string|null $value
     * @return \BroCode\ConfigExplorer\Api\Data\ConfigEntryInterface
     */
    public function setValue(?string $value): ConfigEntryInterface;

    /**
     * Whether the field behind this path declares an encrypted backend model.
     *
     * @return bool
     */
    public function getIsEncrypted(): bool;

    /**
     * @param bool $isEncrypted
     * @return \BroCode\ConfigExplorer\Api\Data\ConfigEntryInterface
     */
    public function setIsEncrypted(bool $isEncrypted): ConfigEntryInterface;
}
