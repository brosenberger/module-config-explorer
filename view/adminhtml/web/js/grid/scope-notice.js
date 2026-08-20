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
define([
    'uiRegistry',
    'Magento_Ui/js/form/components/html'
], function (registry, Html) {
    'use strict';

    var DEFAULT_SCOPE = 'default';

    return Html.extend({
        defaults: {
            // Full dotted registry name of the "Effective Scope" filterSelect -
            // supplied via XML, not hardcoded here, so this stays reusable.
            scopeFilterName: '',
            visible: false
        },

        /**
         * declarative imports/listens map a source PROPERTY straight onto a target
         * property via component.set() - no room for the "non-empty and not
         * literally 'default'" transform this needs, hence subscribing directly
         * instead once the filter component exists in the registry.
         *
         * @inheritdoc
         */
        initialize: function () {
            this._super();

            registry.get(this.scopeFilterName, function (scopeFilter) {
                this.applyVisibility(scopeFilter.value());
                scopeFilter.value.subscribe(this.applyVisibility, this);
            }.bind(this));

            return this;
        },

        /**
         * @param {String} value
         */
        applyVisibility: function (value) {
            this.visible(!!value && value !== DEFAULT_SCOPE);
        }
    });
});
