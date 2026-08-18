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

namespace BroCode\ConfigExplorer\Model;

use BroCode\ConfigExplorer\Model\Config\EncryptedPathResolver;
use BroCode\ConfigExplorer\Model\ResourceModel\ConfigData\CollectionFactory;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * Grid data source.
 *
 * Unlike the REST endpoint this has no reveal parameter at all: the grid always
 * redacts, whatever the current user's ACL says. Plaintext stays behind an explicit
 * API call that leaves a trace, rather than a checkbox somebody leaves on in a shared
 * admin session.
 */
class DataProvider extends AbstractDataProvider
{
    /**
     * @var EncryptedPathResolver
     */
    private $encryptedPathResolver;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param EncryptedPathResolver $encryptedPathResolver
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        EncryptedPathResolver $encryptedPathResolver,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
        $this->encryptedPathResolver = $encryptedPathResolver;
    }

    /**
     * @return array
     */
    public function getData()
    {
        $data = parent::getData();

        if (!isset($data['items']) || !is_array($data['items'])) {
            return $data;
        }

        foreach ($data['items'] as $key => $item) {
            $isEncrypted = $this->encryptedPathResolver->isEncrypted((string)($item['path'] ?? ''));
            $data['items'][$key]['is_encrypted'] = $isEncrypted ? 1 : 0;

            if ($isEncrypted) {
                $data['items'][$key]['value'] = ConfigEntryRepository::REDACTED_PLACEHOLDER;
            }
        }

        return $data;
    }
}
