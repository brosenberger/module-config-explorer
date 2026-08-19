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

namespace BroCode\ConfigExplorer\Test\Unit\Etc;

use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for a bug a live-instance verification pass found:
 * EncryptedPathResolver's injected Structure\Data resolves its configScope to
 * the *current request area*. That is fine in adminhtml (the grid), but the
 * REST controller runs in webapi_rest, which has no system.xml of its own -
 * so the resolver silently returned zero encrypted paths there, and the REST
 * endpoint leaked raw ciphertext by default for every field.
 *
 * The fix lives entirely in a di.xml constructor argument, which no ordinary
 * unit test touches by exercising application code - so this test asserts
 * the wiring itself, directly against the XML, to catch a regression where
 * someone "simplifies" the argument away.
 */
class DiConfigTest extends TestCase
{
    public function testEncryptedPathResolverIsWiredToTheAdminhtmlScopedStructureData(): void
    {
        $path = __DIR__ . '/../../../etc/di.xml';
        self::assertFileExists($path);

        $xml = new \SimpleXMLElement((string)file_get_contents($path));
        $nodes = $xml->xpath(
            '//type[@name="BroCode\ConfigExplorer\Model\Config\EncryptedPathResolver"]'
            . '/arguments/argument[@name="structureData"]'
        );

        self::assertNotEmpty(
            $nodes,
            'EncryptedPathResolver needs an explicit structureData argument in etc/di.xml - '
            . 'the default binding of Structure\Data resolves to the current request area, '
            . 'which is empty outside adminhtml (e.g. the REST controller runs in webapi_rest).'
        );
        self::assertSame(
            'adminhtmlConfigStructureData',
            trim((string)$nodes[0]),
            'structureData must stay pinned to the adminhtml-scoped virtualType, not the '
            . 'area-dependent default - see Magento\Config\Model\Config\Structure\Data\'s '
            . 'default di.xml binding and magento2-base/app/etc/di.xml\'s adminhtmlConfigScope.'
        );
    }
}
