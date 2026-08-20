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

use Magento\Config\App\Config\Type\System;
use Magento\Framework\App\Config\ConfigPathResolver;
use Magento\Framework\App\DeploymentConfig\Reader;
use Magento\Framework\Config\File\ConfigFilePool;
use Magento\Framework\Stdlib\ArrayManager;

/**
 * Resolves which deployment-config file, if any, shadows a core_config_data row.
 *
 * Magento's real precedence for a system-config value is core_config_data, then
 * every file ConfigFilePool knows about, folded in registration order (last file
 * wins - see Magento\Framework\App\DeploymentConfig\Reader::load() and
 * Magento\Framework\App\Config\InitialConfigSource, sortOrder 1000 in
 * module-config's systemConfigSourceAggregated). This walks ConfigFilePool::getPaths()
 * generically rather than hardcoding "config.php"/"env.php" by name, so a module that
 * registers an additional file via the ConfigFilePool DI extension point is picked up
 * without a code change here.
 *
 * The lookup path is built with ConfigPathResolver and read with ArrayManager - the
 * same two classes Magento\Config\Console\Command\ConfigSet\LockProcessor uses to
 * *write* these files. That is deliberate, not just convenient: default scope has no
 * scope-id segment in the stored array, and websites/stores scope is keyed by scope
 * *code*, not the numeric scope_id a core_config_data row carries. Re-deriving that
 * translation by hand would silently mismatch every website/store-scoped row; reusing
 * the writer's own path-resolution guarantees read and write agree by construction.
 *
 * Known limitation: this only ever answers "is this DB row shadowed", because it is
 * driven by a path already read from core_config_data. LockProcessor never calls
 * save() - a path locked via --lock-env/--lock-config that was never otherwise set
 * has no DB row, and is invisible to this resolver (and to the grid) entirely.
 */
class ValueOriginResolver
{
    /**
     * @var ConfigFilePool
     */
    private $configFilePool;

    /**
     * @var Reader
     */
    private $reader;

    /**
     * @var ConfigPathResolver
     */
    private $configPathResolver;

    /**
     * @var ArrayManager
     */
    private $arrayManager;

    /**
     * @var array<string, array>
     */
    private $fileDataCache = [];

    public function __construct(
        ConfigFilePool $configFilePool,
        Reader $reader,
        ConfigPathResolver $configPathResolver,
        ArrayManager $arrayManager
    ) {
        $this->configFilePool = $configFilePool;
        $this->reader = $reader;
        $this->configPathResolver = $configPathResolver;
        $this->arrayManager = $arrayManager;
    }

    /**
     * The winning deployment-config file for this path/scope/scopeId, or null if no
     * registered file defines it and the core_config_data row is authoritative.
     */
    public function resolve(string $path, string $scope, int $scopeId): ?ValueOrigin
    {
        $fullPath = $this->configPathResolver->resolve($path, $scope, $scopeId, System::CONFIG_TYPE);

        $winner = null;

        foreach ($this->configFilePool->getPaths() as $fileKey => $fileName) {
            $fileData = $this->getFileData($fileKey);

            if (!$this->arrayManager->exists($fullPath, $fileData)) {
                continue;
            }

            $value = $this->arrayManager->get($fullPath, $fileData);

            // A malformed lock file could hold a whole subtree at what should be a
            // leaf path; that is not a displayable override, so it is skipped
            // rather than handed to the grid as a value.
            if (is_array($value)) {
                continue;
            }

            $winner = new ValueOrigin($fileKey, $fileName, $value);
        }

        return $winner;
    }

    /**
     * @return array
     */
    private function getFileData(string $fileKey): array
    {
        if (!isset($this->fileDataCache[$fileKey])) {
            $this->fileDataCache[$fileKey] = $this->reader->load($fileKey);
        }

        return $this->fileDataCache[$fileKey];
    }
}
