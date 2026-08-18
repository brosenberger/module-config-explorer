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
declare(strict_types=1);

namespace BroCode\ConfigExplorer\Model\Config;

use Magento\Config\Model\Config\Backend\Encrypted;
use Magento\Config\Model\Config\Structure\Data as StructureData;

/**
 * Resolves the set of config paths whose declared backend_model encrypts its value.
 *
 * Magento core answers a narrower version of this question in
 * Magento\Config\Model\Config\Structure::getFieldPathsByAttribute(), which
 * Magento\EncryptionKey\Model\ResourceModel\Key\Change uses to find rows to re-encrypt
 * on a key rotation. That method is not reused here because it misses two cases this
 * module has to get right:
 *
 * 1. It compares backend_model with `==` against one class name, so a field whose
 *    backend_model is a *subclass* of Encrypted is never returned. For a re-encrypt
 *    routine that is a missed row; for a redaction routine it is a leaked secret.
 * 2. It builds structure paths (section/group/field) and ignores <config_path>
 *    overrides, so a field that stores under a different path than it is declared at
 *    would be matched against the wrong core_config_data row.
 *
 * The merged-structure array shape walked below is the same one core traverses in
 * Structure::getFieldPathsByAttribute(): sections keyed by id, each with `children`,
 * groups nesting further groups, and every node tagged `_elementType` by
 * Magento\Config\Model\Config\Structure\Converter.
 *
 * Known limitation: a field is only recognised when its declared backend_model is
 * Encrypted or a subclass of it. A custom backend model that calls the encryptor
 * without extending Encrypted is invisible here, and its value will be shown in full.
 * Audit your own system.xml before treating this as a security boundary.
 */
class EncryptedPathResolver
{
    /**
     * @var StructureData
     */
    private $structureData;

    /**
     * @var string[]|null
     */
    private $encryptedPaths;

    public function __construct(StructureData $structureData)
    {
        $this->structureData = $structureData;
    }

    /**
     * Whether the given stored config path belongs to an encrypted field.
     */
    public function isEncrypted(string $path): bool
    {
        return isset($this->getEncryptedPaths()[$path]);
    }

    /**
     * Encrypted config paths, keyed by path for O(1) lookup.
     *
     * @return array<string, true>
     */
    public function getEncryptedPaths(): array
    {
        if ($this->encryptedPaths !== null) {
            return $this->encryptedPaths;
        }

        $paths = [];
        $sections = $this->structureData->get('sections');

        if (is_array($sections)) {
            foreach ($sections as $sectionId => $section) {
                $children = $section['children'] ?? null;
                if (is_array($children)) {
                    $this->collect($children, (string)$sectionId, $paths);
                }
            }
        }

        $this->encryptedPaths = $paths;

        return $this->encryptedPaths;
    }

    /**
     * Walks group children, recursing into nested groups and collecting encrypted fields.
     *
     * @param array $nodes
     * @param string $parentPath
     * @param array<string, true> $paths
     * @return void
     */
    private function collect(array $nodes, string $parentPath, array &$paths): void
    {
        foreach ($nodes as $nodeId => $node) {
            if (!is_array($node)) {
                continue;
            }

            $path = $parentPath . '/' . $nodeId;

            if (isset($node['children']) && is_array($node['children'])) {
                $this->collect($node['children'], $path, $paths);
                continue;
            }

            if (($node['_elementType'] ?? null) !== 'field') {
                continue;
            }

            if (!$this->isEncryptedBackendModel($node['backend_model'] ?? null)) {
                continue;
            }

            // A <config_path> node overrides where the value is actually stored.
            $storedPath = isset($node['config_path']) && is_string($node['config_path'])
                ? trim($node['config_path'])
                : $path;

            if ($storedPath !== '') {
                $paths[$storedPath] = true;
            }
        }
    }

    /**
     * @param string|null $backendModel
     * @return bool
     */
    private function isEncryptedBackendModel($backendModel): bool
    {
        if (!is_string($backendModel)) {
            return false;
        }

        $className = ltrim(trim($backendModel), '\\');

        if ($className === '') {
            return false;
        }

        // is_a() with allow_string covers the exact class and every subclass, and
        // returns false rather than throwing when the class cannot be autoloaded.
        return is_a($className, Encrypted::class, true);
    }
}
