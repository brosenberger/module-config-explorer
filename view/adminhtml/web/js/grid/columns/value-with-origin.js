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
    'Magento_Ui/js/grid/columns/column'
], function (Column) {
    'use strict';

    var TOOLTIP_VALUE_MAX_LENGTH = 120;

    return Column.extend({
        /**
         * Hover text for the origin icon: which file shadowed this row and, when
         * there is one, the DB value it shadowed - truncated, since config values
         * are frequently long serialized/JSON strings that native tooltips don't
         * wrap or scroll.
         *
         * @param {Object} row
         * @returns {String}
         */
        getOriginTooltip: function (row) {
            if (!row || !row.origin_source) {
                return '';
            }

            if (row.db_value === undefined || row.db_value === null) {
                return 'Overridden by ' + row.origin_source;
            }

            var dbValue = String(row.db_value);

            if (dbValue.length > TOOLTIP_VALUE_MAX_LENGTH) {
                dbValue = dbValue.substring(0, TOOLTIP_VALUE_MAX_LENGTH) + '…';
            }

            return 'Overridden by ' + row.origin_source + ': ' + dbValue;
        }
    });
});
