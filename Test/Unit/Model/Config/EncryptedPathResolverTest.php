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

use BroCode\ConfigExplorer\Model\Config\EncryptedPathResolver;
use BroCode\ConfigExplorer\Test\Unit\Fixture\FakeEncryptedSubclass;
use Magento\Config\Model\Config\Backend\Encrypted;
use Magento\Config\Model\Config\Structure\Data as StructureData;
use PHPUnit\Framework\TestCase;

/**
 * Covers the merged-structure tree walk in isolation from the DI wiring that
 * decides which Structure\Data instance actually reaches it - that part is
 * covered separately in \BroCode\ConfigExplorer\Test\Unit\Etc\DiConfigTest.
 */
class EncryptedPathResolverTest extends TestCase
{
    private function resolverOverSections(array $sections): EncryptedPathResolver
    {
        $structureData = $this->createMock(StructureData::class);
        $structureData->method('get')->with('sections')->willReturn($sections);

        return new EncryptedPathResolver($structureData);
    }

    private function fieldNode(string $backendModel, ?string $configPath = null): array
    {
        $node = [
            '_elementType' => 'field',
            'backend_model' => $backendModel,
        ];

        if ($configPath !== null) {
            $node['config_path'] = $configPath;
        }

        return $node;
    }

    public function testExactEncryptedClassIsDetected(): void
    {
        $resolver = $this->resolverOverSections([
            'carriers' => ['children' => [
                'usps' => ['children' => [
                    'password' => $this->fieldNode(Encrypted::class),
                ]],
            ]],
        ]);

        self::assertTrue($resolver->isEncrypted('carriers/usps/password'));
    }

    public function testSubclassOfEncryptedIsDetected(): void
    {
        $resolver = $this->resolverOverSections([
            'carriers' => ['children' => [
                'usps' => ['children' => [
                    'password' => $this->fieldNode(FakeEncryptedSubclass::class),
                ]],
            ]],
        ]);

        self::assertTrue($resolver->isEncrypted('carriers/usps/password'));
    }

    public function testLeadingBackslashOnBackendModelIsTolerated(): void
    {
        $resolver = $this->resolverOverSections([
            'carriers' => ['children' => [
                'usps' => ['children' => [
                    'password' => $this->fieldNode('\\' . Encrypted::class),
                ]],
            ]],
        ]);

        self::assertTrue($resolver->isEncrypted('carriers/usps/password'));
    }

    public function testConfigPathOverrideIsHonoured(): void
    {
        $resolver = $this->resolverOverSections([
            'payment' => ['children' => [
                'braintree' => ['children' => [
                    'merchant_id' => $this->fieldNode(Encrypted::class, 'payment/braintree_alias/merchant_id'),
                ]],
            ]],
        ]);

        self::assertTrue($resolver->isEncrypted('payment/braintree_alias/merchant_id'));
        self::assertFalse(
            $resolver->isEncrypted('payment/braintree/merchant_id'),
            'The declared structure path must not also match once config_path overrides it - '
            . 'the row lives only at the overridden path.'
        );
    }

    public function testNestedGroupsAreWalkedRecursively(): void
    {
        $resolver = $this->resolverOverSections([
            'section' => ['children' => [
                'outer' => ['children' => [
                    'inner' => ['children' => [
                        'field' => $this->fieldNode(Encrypted::class),
                    ]],
                ]],
            ]],
        ]);

        self::assertTrue($resolver->isEncrypted('section/outer/inner/field'));
    }

    public function testUnloadableBackendModelClassIsNotEncrypted(): void
    {
        $resolver = $this->resolverOverSections([
            'section' => ['children' => [
                'group' => ['children' => [
                    'field' => $this->fieldNode('Vendor\\Module\\Does\\Not\\Exist'),
                ]],
            ]],
        ]);

        self::assertFalse($resolver->isEncrypted('section/group/field'));
    }

    public function testPlainTextFieldIsNotEncrypted(): void
    {
        $resolver = $this->resolverOverSections([
            'general' => ['children' => [
                'store_information' => ['children' => [
                    'name' => [
                        '_elementType' => 'field',
                    ],
                ]],
            ]],
        ]);

        self::assertFalse($resolver->isEncrypted('general/store_information/name'));
    }

    public function testEmptyStructureYieldsNoEncryptedPaths(): void
    {
        $resolver = $this->resolverOverSections([]);

        self::assertSame([], $resolver->getEncryptedPaths());
        self::assertFalse($resolver->isEncrypted('anything/at/all'));
    }
}
