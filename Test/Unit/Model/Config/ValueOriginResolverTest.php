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

use BroCode\ConfigExplorer\Model\Config\ValueOriginResolver;
use Magento\Config\App\Config\Type\System;
use Magento\Framework\App\Config\ConfigPathResolver;
use Magento\Framework\App\DeploymentConfig\Reader;
use Magento\Framework\Config\File\ConfigFilePool;
use Magento\Framework\Stdlib\ArrayManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * ConfigPathResolver's own scope/scope-id-to-array-key translation (default has no
 * id segment, websites/stores use scope code not numeric scope_id) is Magento's own,
 * already-trusted logic, so it is mocked here rather than re-verified - these tests
 * cover only what this class is actually responsible for: walking
 * ConfigFilePool::getPaths() and picking the last file that defines the resolved
 * path. The real ArrayManager is used (no framework dependencies, pure logic) rather
 * than mocked, so the exists()/get() lookup itself is genuinely exercised.
 */
class ValueOriginResolverTest extends TestCase
{
    public function testNoOverrideAnywhereReturnsNull(): void
    {
        $resolver = $this->resolverOver(
            ['app_config' => 'config.php', 'app_env' => 'env.php'],
            ['app_config' => [], 'app_env' => []],
            'default/general/locale/code'
        );

        self::assertNull($resolver->resolve('general/locale/code', 'default', 0));
    }

    public function testSingleFileOverrideIsReturned(): void
    {
        $resolver = $this->resolverOver(
            ['app_config' => 'config.php', 'app_env' => 'env.php'],
            [
                'app_config' => $this->nested('default/general/locale/code', 'de_DE'),
                'app_env' => [],
            ],
            'default/general/locale/code'
        );

        $origin = $resolver->resolve('general/locale/code', 'default', 0);

        self::assertNotNull($origin);
        self::assertSame('app_config', $origin->getFileKey());
        self::assertSame('config.php', $origin->getFileName());
        self::assertSame('de_DE', $origin->getValue());
    }

    public function testLastRegisteredFileWinsOnConflict(): void
    {
        $resolver = $this->resolverOver(
            ['app_config' => 'config.php', 'app_env' => 'env.php'],
            [
                'app_config' => $this->nested('default/general/locale/code', 'de_DE'),
                'app_env' => $this->nested('default/general/locale/code', 'en_US'),
            ],
            'default/general/locale/code'
        );

        $origin = $resolver->resolve('general/locale/code', 'default', 0);

        self::assertSame('app_env', $origin->getFileKey());
        self::assertSame('en_US', $origin->getValue());
    }

    public function testDifferentPathInFileDoesNotFalseMatch(): void
    {
        $resolver = $this->resolverOver(
            ['app_config' => 'config.php'],
            ['app_config' => $this->nested('default/general/locale/timezone', 'Europe/Vienna')],
            'default/general/locale/code'
        );

        self::assertNull($resolver->resolve('general/locale/code', 'default', 0));
    }

    public function testDelegatesPathBuildingToConfigPathResolver(): void
    {
        $pool = $this->createMock(ConfigFilePool::class);
        $pool->method('getPaths')->willReturn([]);

        $reader = $this->createMock(Reader::class);

        $pathResolver = $this->createMock(ConfigPathResolver::class);
        $pathResolver->expects(self::once())
            ->method('resolve')
            ->with('web/unsecure/base_url', 'websites', 1, System::CONFIG_TYPE)
            ->willReturn('websites/1/web/unsecure/base_url');

        $resolver = new ValueOriginResolver($pool, $reader, $pathResolver, new ArrayManager());

        $resolver->resolve('web/unsecure/base_url', 'websites', 1);
    }

    /**
     * @param array<string, string> $filePaths file key => file name, as ConfigFilePool::getPaths() returns
     * @param array<string, array> $fileData file key => that file's raw data, as Reader::load($key) returns
     */
    private function resolverOver(array $filePaths, array $fileData, string $resolvedPath): ValueOriginResolver
    {
        $pool = $this->createMock(ConfigFilePool::class);
        $pool->method('getPaths')->willReturn($filePaths);

        $reader = $this->createMock(Reader::class);
        $reader->method('load')->willReturnCallback(
            static fn (string $fileKey): array => $fileData[$fileKey] ?? []
        );

        $pathResolver = $this->createMock(ConfigPathResolver::class);
        $pathResolver->method('resolve')->willReturn($resolvedPath);

        return new ValueOriginResolver($pool, $reader, $pathResolver, new ArrayManager());
    }

    /**
     * @param mixed $value
     */
    private function nested(string $path, $value): array
    {
        $result = $value;

        foreach (array_reverse(explode('/', $path)) as $segment) {
            $result = [$segment => $result];
        }

        return $result;
    }
}
