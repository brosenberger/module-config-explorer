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

/**
 * Stand-ins for classes that only exist once `bin/magento setup:di:compile`
 * has run: the two generated Factory classes, and a throwaway subclass of
 * core's Encrypted backend model for the "subclass is detected too" test
 * case. Unit tests run with no Magento install at all, so nothing ever
 * generates the real ones - PHPUnit's mock builder still needs a real,
 * loadable class to reflect on. What these bodies do is irrelevant: every
 * test that needs one overrides create()/behavior via createMock().
 */

namespace BroCode\ConfigExplorer\Api\Data {

    use BroCode\ConfigExplorer\Model\Data\ConfigEntry;

    if (!class_exists(ConfigEntryInterfaceFactory::class, false)) {
        class ConfigEntryInterfaceFactory
        {
            /**
             * @param array $data
             * @return ConfigEntryInterface
             */
            public function create(array $data = []): ConfigEntryInterface
            {
                return new ConfigEntry($data);
            }
        }
    }
}

namespace BroCode\ConfigExplorer\Model\ResourceModel\ConfigData {

    if (!class_exists(CollectionFactory::class, false)) {
        class CollectionFactory
        {
            /**
             * @param array $data
             * @return Collection
             */
            public function create(array $data = []): Collection
            {
                throw new \LogicException(self::class . ' is a test stub; mock create() per test.');
            }
        }
    }
}

namespace BroCode\ConfigExplorer\Test\Unit\Fixture {

    if (!class_exists(FakeEncryptedSubclass::class, false)) {
        /**
         * A field whose backend_model is a subclass of core's Encrypted must be
         * detected too - EncryptedPathResolver::isEncryptedBackendModel() uses
         * is_a($class, Encrypted::class, true), which needs a real, loadable
         * subclass to check against. Never instantiated, so Encrypted's own
         * constructor dependencies don't matter here.
         */
        class FakeEncryptedSubclass extends \Magento\Config\Model\Config\Backend\Encrypted
        {
        }
    }
}
