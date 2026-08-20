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

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;

/**
 * Flat, indented Default / Website / Store View options for the grid's scope
 * switcher filter.
 *
 * Magento\Store\Model\System\Store's own tree builders (getStoresStructure(),
 * getStoreOptionsTree()) target classic HTML <select> optgroup rendering for the
 * Stores > Configuration switcher; a ui_component filterSelect needs a flat options
 * list instead, so this reuses the website/store lookups but not that tree shape.
 * Each option's value encodes the scope pair the filter needs back
 * ("default", "websites:1", "stores:2").
 */
class ScopeOptions implements OptionSourceInterface
{
    private const SCOPE_DEFAULT = 'default';
    private const SCOPE_WEBSITE = 'websites';
    private const SCOPE_STORE = 'stores';

    /**
     * Plain spaces collapse in rendered <option> text; a real non-breaking space
     * does not. Same fix Magento\Store\Model\System\Store::retrieveOptionValues()
     * uses for its own tree-select indentation.
     */
    private const INDENT = "\u{00A0}\u{00A0}\u{00A0}\u{00A0}";

    /**
     * @var WebsiteRepositoryInterface
     */
    private $websiteRepository;

    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    public function __construct(WebsiteRepositoryInterface $websiteRepository, StoreRepositoryInterface $storeRepository)
    {
        $this->websiteRepository = $websiteRepository;
        $this->storeRepository = $storeRepository;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            ['value' => self::SCOPE_DEFAULT, 'label' => __('Default Config')],
        ];

        $storesByWebsite = [];
        foreach ($this->storeRepository->getList() as $store) {
            $storesByWebsite[(int)$store->getWebsiteId()][] = $store;
        }

        foreach ($this->websiteRepository->getList() as $website) {
            $websiteId = (int)$website->getId();

            // Website/store id 0 is the internal "Admin" pseudo-scope, not a real
            // storefront - Magento's own Stores > Configuration switcher excludes it
            // by default too (Store\Model\System\Store::setIsAdminScopeAllowed()).
            if ($websiteId === 0) {
                continue;
            }

            $options[] = [
                'value' => self::SCOPE_WEBSITE . ':' . $websiteId,
                'label' => __('Website: %1', $website->getName()),
            ];

            foreach ($storesByWebsite[$websiteId] ?? [] as $store) {
                $options[] = [
                    'value' => self::SCOPE_STORE . ':' . $store->getId(),
                    'label' => __(self::INDENT . 'Store View: %1', $store->getName()),
                ];
            }
        }

        return $options;
    }
}
