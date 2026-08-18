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

namespace BroCode\ConfigExplorer\Api;

/**
 * Read access to core_config_data rows.
 *
 * There is no save or delete counterpart anywhere in this module, by design.
 */
interface ConfigEntryRepositoryInterface
{
    /**
     * Return core_config_data rows matching the given filters.
     *
     * Redaction is applied in this order:
     * 1. A row whose path is not encrypted always returns its plain value.
     * 2. An encrypted row with $revealEncrypted false returns the redaction
     *    placeholder. This is the default for every caller.
     * 3. An encrypted row with $revealEncrypted true requires all three of:
     *    the brocode_config_explorer/general/allow_encrypted_reveal system toggle,
     *    the BroCode_ConfigExplorer::config_view_encrypted ACL resource, and this
     *    flag. A caller who asks for plaintext and may not have it gets an
     *    exception rather than a silently redacted response.
     *
     * @param string|null $path Partial match, applied as LIKE %path%. Null returns every path.
     * @param string|null $scope One of "default", "websites", "stores". Null returns every scope.
     * @param int|null $scopeId Ignored when $scope is null or "default".
     * @param bool $revealEncrypted Defaults to false; see the redaction rules above.
     * @return \BroCode\ConfigExplorer\Api\Data\ConfigEntryInterface[]
     * @throws \Magento\Framework\Exception\AuthorizationException When plaintext is requested
     *         without the system toggle and the ACL resource.
     */
    public function getList(
        ?string $path = null,
        ?string $scope = null,
        ?int $scopeId = null,
        bool $revealEncrypted = false
    ): array;
}
