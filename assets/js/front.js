(function ($) {
    function getStrings() {
        return window.whData || {};
    }

    function createModal(html) {
        var overlay = $('<div class="wh-overlay"></div>');
        var modal = $('<div class="wh-modal" role="dialog" aria-modal="true"></div>').html(html);
        var close = $('<button type="button" class="wh-close" aria-label="Close">×</button>');

        close.on('click', function () {
            overlay.remove();
            modal.remove();
        });

        modal.prepend(close);
        $('body').append(overlay).append(modal);

        overlay.on('click', function () {
            overlay.remove();
            modal.remove();
        });

        return { overlay: overlay, modal: modal };
    }

    function escHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function setButtonState($button, loading) {
        var strings = getStrings();

        if (!$button || !$button.length) {
            return;
        }

        if (loading) {
            $button.data('wh-original-text', $button.text());
            $button.prop('disabled', true).addClass('is-busy').text(strings.loading_text || 'در حال تولید...');
        } else {
            var original = $button.data('wh-original-text');
            $button.prop('disabled', false).removeClass('is-busy');
            if (original) {
                $button.text(original);
            }
        }
    }

    function formatCostText(cost, usage, costToman) {
        if (cost === null || cost === undefined) {
            return '';
        }

        var strings = getStrings();
        var dollarCost = Number(cost);
        var text = (strings.cost_label || 'هزینه تقریبی') + ': $' + dollarCost.toFixed(6);

        var tomanValue = costToman;
        if (tomanValue === null || tomanValue === undefined || tomanValue === '') {
            var rate = parseFloat(strings.usd_to_toman_rate || 0);
            if (rate > 0) {
                tomanValue = Math.round(dollarCost * rate);
            }
        }

        if (tomanValue !== null && tomanValue !== undefined && tomanValue !== '' && Number(tomanValue) > 0) {
            var formattedToman = Number(tomanValue).toLocaleString('fa-IR');
            text += ' | معادل: ' + formattedToman + ' تومان';
        }

        if (usage && typeof usage.prompt_tokens !== 'undefined') {
            text += ' (' + usage.prompt_tokens + ' input / ' + (usage.completion_tokens || 0) + ' output tokens)';
        }

        return text;
    }

    function buildAttributesMarkup(attrs, promptText, costInfo) {
        var html = '<h2>ویژگی‌های پیشنهادی</h2><form id="wh-attr-form">';
        if (promptText) {
            html += '<details class="wh-prompt-panel" open>';
            html += '<summary>پرامپت ارسالی</summary>';
            html += '<textarea class="wh-prompt-text" readonly>' + escHtml(promptText) + '</textarea>';
            html += '</details>';
        }
        if (costInfo) {
            html += '<div class="wh-cost-note">' + escHtml(costInfo) + '</div>';
        }
        html += '<div class="wh-attrs">';

        attrs.forEach(function (item, idx) {
            var name = item.name || item.key || ('attr' + idx);
            var val = item.value || item.val || '';
            var rowId = 'wh-attr-' + idx;
            html += '<div class="wh-attr-row" data-row-id="' + escHtml(rowId) + '">';
            html += '<label class="wh-attr-check" for="' + escHtml(rowId + '-check') + '">';
            html += '<input id="' + escHtml(rowId + '-check') + '" type="checkbox" class="wh-attr-select" checked> ';
            html += 'انتخاب';
            html += '</label>';
            html += '<div class="wh-attr-fields">';
            html += '<input type="text" class="wh-attr-name regular-text" value="' + escHtml(name) + '" placeholder="نام ویژگی">';
            html += '<input type="text" class="wh-attr-value regular-text" value="' + escHtml(val) + '" placeholder="مقدار ویژگی">';
            html += '</div>';
            html += '</div>';
        });

        html += '</div>';
        html += '<div class="wh-actions">';
        html += '<button type="button" id="wh-add-attrs" class="button button-primary">' + escHtml((getStrings().add_text || 'افزودن ویژگی‌ها')) + '</button> ';
        html += '<button type="button" id="wh-cancel" class="button">' + escHtml((getStrings().cancel_text || 'انصراف')) + '</button>';
        html += '</div>';
        html += '</form>';

        return html;
    }

    function normalizeResponsePayload(data) {
        if (Array.isArray(data)) {
            return {
                attributes: data,
                prompt: '',
                estimated_cost: null,
                estimated_cost_toman: null,
                usage: null
            };
        }

        return {
            attributes: (data && data.attributes) ? data.attributes : [],
            prompt: (data && (data.full_prompt || data.prompt)) ? (data.full_prompt || data.prompt) : '',
            estimated_cost: (data && typeof data.estimated_cost !== 'undefined') ? data.estimated_cost : null,
            estimated_cost_toman: (data && typeof data.estimated_cost_toman !== 'undefined') ? data.estimated_cost_toman : null,
            usage: (data && data.usage) ? data.usage : null
        };
    }

    function renderAttributesModal(payload, productId, sourceButton) {
        var strings = getStrings();
        var normalized = normalizeResponsePayload(payload);
        var costInfo = formatCostText(normalized.estimated_cost, normalized.usage, normalized.estimated_cost_toman);
        var win = createModal(buildAttributesMarkup(normalized.attributes, normalized.prompt, costInfo));

        win.modal.on('click', '#wh-cancel', function (e) {
            e.preventDefault();
            win.overlay.remove();
            win.modal.remove();
        });

        win.modal.on('click', '#wh-add-attrs', function (e) {
            e.preventDefault();

            var selected = [];
            win.modal.find('.wh-attr-row').each(function () {
                var $row = $(this);
                var $select = $row.find('.wh-attr-select');
                if ($select.is(':checked')) {
                    var name = ($row.find('.wh-attr-name').val() || '').trim();
                    var value = ($row.find('.wh-attr-value').val() || '').trim();
                    if (name) {
                        selected.push({
                            name: name,
                            value: value
                        });
                    }
                }
            });

            if (selected.length === 0) {
                alert(strings.empty_message || 'هیچ ویژگی‌ای انتخاب نشده است.');
                return;
            }

            var post = {
                action: 'wh_add_attributes',
                nonce: whData.nonce,
                product_id: productId,
                attributes: selected
            };

            $.post(whData.ajax_url, post).done(function (r) {
                if (r && r.success) {
                    var d = r.data || {};
                    var msg = d.message || (strings.success_text || 'ویژگی‌ها اضافه شدند.');
                    if (typeof d.added_terms !== 'undefined') {
                        msg += '\n(' + d.added_terms + ' مقدار، ' + (d.new_attrs || 0) + ' ویژگی جدید)';
                    }
                    if (d.verify && d.verify.length) {
                        msg += '\nروی محصول الان:\n' + d.verify.join('\n');
                    }
                    if (d.errors && d.errors.length) {
                        msg += '\nخطاها: ' + d.errors.join(' | ');
                    }

                    win.overlay.remove();
                    win.modal.remove();

                    // On the product edit screen the Attributes metabox does not refresh by
                    // itself, and saving the product would overwrite the freshly added
                    // attribute. Reload so it appears in the panel and sticks on save.
                    var onEditScreen = $('#woocommerce-product-data').length || ($('#post_ID').length && $('body').hasClass('post-type-product'));

                    if (onEditScreen) {
                        if (window.confirm(msg + '\n\nبرای نمایش در تب «ویژگی‌ها» و جلوگیری از پاک‌شدن هنگام ذخیره، صفحه رفرش شود؟\n(اگر تغییرات ذخیره‌نشده دارید، ابتدا انصراف دهید و محصول را ذخیره کنید.)')) {
                            window.location.reload();
                        }
                    } else {
                        alert(msg);
                    }
                } else {
                    alert((strings.error_text || 'خطا') + ': ' + ((r && r.data) || 'خطا در افزودن'));
                }
            }).fail(function () {
                alert('در ارسال درخواست خطا رخ داد.');
            });
        });

        if (sourceButton && sourceButton.length) {
            setButtonState(sourceButton, false);
        }
    }

    function setShortDescriptionEditorContent(text) {
        var $textarea = $('#excerpt');
        if ($textarea.length) {
            $textarea.val(text).trigger('input').trigger('change');
        }

        if (window.tinymce && tinymce.get('excerpt')) {
            tinymce.get('excerpt').setContent(text);
        }
    }

    function setProductDescriptionEditorContent(text) {
        var $textarea = $('#content');
        if ($textarea.length) {
            $textarea.val(text).trigger('input').trigger('change');
        }

        if (window.tinymce && tinymce.get('content')) {
            tinymce.get('content').setContent(text);
        }
    }

    function placeProductDescriptionButton() {
        var $scope = $('#postdivrich');
        if (!$scope.length) {
            return false;
        }

        var $mediaButtons = $scope.find('.wp-media-buttons').first();
        var $inside = $scope.find('.inside').first();

        if (!$mediaButtons.length && !$inside.length) {
            return false;
        }

        if ($scope.find('.wh-productdesc-actions').length) {
            return true;
        }

        var label = escHtml((getStrings().product_desc_text || getStrings().short_desc_text || 'تولید متن با هوش مصنوعی'));
        var html = ''
            + '<span class="wh-productdesc-actions">'
            + '<button type="button" class="button button-primary wh-generate-product-description">' + label + '</button>'
            + '<span class="wh-productdesc-cost"></span>'
            + '</span>';

        if ($mediaButtons.length) {
            $mediaButtons.append(html);
        } else {
            $inside.prepend(html);
        }
        return true;
    }

    function initProductDescriptionButtonPlacement() {
        if (!$('body').hasClass('wp-admin')) {
            return;
        }

        var attempts = 0;
        var timer = window.setInterval(function () {
            attempts += 1;
            if (placeProductDescriptionButton() || attempts >= 12) {
                window.clearInterval(timer);
            }
        }, 400);
    }

    function requestProductDescription(productId, $button) {
        var strings = getStrings();
        var $cost = $('.wh-productdesc-cost').first();

        if ($button && $button.length) {
            $button.data('wh-original-text', $button.text());
            $button.prop('disabled', true).addClass('is-busy').text(strings.product_desc_loading_text || strings.short_desc_loading_text || 'در حال تولید متن...');
        }

        $.post(whData.ajax_url, {
            action: 'wh_generate_product_description',
            nonce: whData.nonce,
            product_id: productId
        }).done(function (resp) {
            if ($button && $button.length) {
                var original = $button.data('wh-original-text');
                $button.prop('disabled', false).removeClass('is-busy');
                if (original) {
                    $button.text(original);
                }
            }

            if (resp && resp.success) {
                var data = resp.data || {};
                if (data.product_description) {
                    setProductDescriptionEditorContent(data.product_description);
                }

                var costInfo = formatCostText(data.estimated_cost, data.usage, data.estimated_cost_toman);
                if ($cost.length) {
                    $cost.text(costInfo);
                }
            } else {
                alert((strings.error_text || 'خطا') + ': ' + ((resp && resp.data) || 'پاسخ نامعتبر از سرور'));
            }
        }).fail(function () {
            if ($button && $button.length) {
                var original2 = $button.data('wh-original-text');
                $button.prop('disabled', false).removeClass('is-busy');
                if (original2) {
                    $button.text(original2);
                }
            }
            alert('در ارتباط با سرور خطا رخ داد.');
        });
    }

    function placeShortDescriptionButton() {
        var $scope = $('#postexcerpt');
        if (!$scope.length) {
            return false;
        }

        var $mediaButtons = $scope.find('.wp-media-buttons').first();
        var $inside = $scope.find('.inside').first();

        if (!$mediaButtons.length && !$inside.length) {
            return false;
        }

        if ($scope.find('.wh-shortdesc-actions').length) {
            return true;
        }

        var label = escHtml((getStrings().short_desc_text || 'تولید متن با هوش مصنوعی'));
        var html = ''
            + '<span class="wh-shortdesc-actions">'
            + '<button type="button" class="button button-primary wh-generate-short-description">' + label + '</button>'
            + '<span class="wh-shortdesc-cost"></span>'
            + '</span>';

        if ($mediaButtons.length) {
            $mediaButtons.append(html);
        } else {
            $inside.prepend(html);
        }
        return true;
    }

    function initShortDescriptionButtonPlacement() {
        if (!$('body').hasClass('wp-admin')) {
            return;
        }

        var attempts = 0;
        var timer = window.setInterval(function () {
            attempts += 1;
            if (placeShortDescriptionButton() || attempts >= 12) {
                window.clearInterval(timer);
            }
        }, 400);
    }

    function requestShortDescription(productId, $button) {
        var strings = getStrings();
        var $cost = $('.wh-shortdesc-cost').first();

        if ($button && $button.length) {
            $button.data('wh-original-text', $button.text());
            $button.prop('disabled', true).addClass('is-busy').text(strings.short_desc_loading_text || 'در حال تولید متن...');
        }

        $.post(whData.ajax_url, {
            action: 'wh_generate_short_description',
            nonce: whData.nonce,
            product_id: productId
        }).done(function (resp) {
            if ($button && $button.length) {
                var original = $button.data('wh-original-text');
                $button.prop('disabled', false).removeClass('is-busy');
                if (original) {
                    $button.text(original);
                }
            }

            if (resp && resp.success) {
                var data = resp.data || {};
                if (data.short_description) {
                    setShortDescriptionEditorContent(data.short_description);
                }

                var costInfo = formatCostText(data.estimated_cost, data.usage, data.estimated_cost_toman);
                if ($cost.length) {
                    $cost.text(costInfo);
                }
            } else {
                alert((strings.error_text || 'خطا') + ': ' + ((resp && resp.data) || 'پاسخ نامعتبر از سرور'));
            }
        }).fail(function () {
            if ($button && $button.length) {
                var original2 = $button.data('wh-original-text');
                $button.prop('disabled', false).removeClass('is-busy');
                if (original2) {
                    $button.text(original2);
                }
            }
            alert('در ارتباط با سرور خطا رخ داد.');
        });
    }

    function requestAttributes(productId, sourceButton) {
        var strings = getStrings();
        var $button = sourceButton || $('#wh-generate-btn');

        setButtonState($button, true);

        $.post(whData.ajax_url, {
            action: 'wh_generate_attributes',
            nonce: whData.nonce,
            product_id: productId
        }).done(function (resp) {
            if (resp && resp.success) {
                renderAttributesModal(resp.data || [], productId, $button);
            } else {
                setButtonState($button, false);
                alert((strings.error_text || 'خطا') + ': ' + ((resp && resp.data) || 'پاسخ نامعتبر از سرور'));
            }
        }).fail(function () {
            setButtonState($button, false);
            alert('در ارتباط با سرور خطا رخ داد.');
        });
    }

    function placeEditorAttributeButton() {
        var $cta = $('.wh-product-attributes-cta').first();
        if (!$cta.length) {
            return false;
        }

        var $scope = $('#woocommerce-product-data');
        if (!$scope.length) {
            return false;
        }

        var selectors = [
            '.add_new_attribute',
            '.button.add_attribute',
            '.attribute_actions .button.add_attribute',
            '.attribute-actions .button.add_attribute',
            '.attribute_actions .button',
            '.attribute-actions .button',
            '.page-title-action',
            'button:contains("افزودن جدید")',
            'a:contains("افزودن جدید")',
            'button:contains("Add New")',
            'a:contains("Add New")'
        ];

        var $target = $();
        for (var i = 0; i < selectors.length; i++) {
            var $found = $scope.find(selectors[i]).first();
            if ($found.length) {
                $target = $found;
                break;
            }
        }

        if ($target.length) {
            $cta.insertAfter($target);
            return true;
        }

        var $fallback = $scope.find('.woocommerce_options_panel, .wc-metaboxes-wrapper, .panel').first();
        if ($fallback.length) {
            $cta.appendTo($fallback);
            return true;
        }

        return false;
    }

    function initEditorAttributeButtonPlacement() {
        if (!$('body').hasClass('wp-admin')) {
            return;
        }

        var attempts = 0;
        var timer = window.setInterval(function () {
            attempts += 1;
            if (placeEditorAttributeButton() || attempts >= 12) {
                window.clearInterval(timer);
            }
        }, 400);
    }

    $(document).on('click', '#wh-generate-btn', function (e) {
        e.preventDefault();
        var productId = whData.product_id || $(this).data('product-id');
        if (!productId) {
            alert('شناسه محصول پیدا نشد.');
            return;
        }

        requestAttributes(productId, $(this));
    });

    $(document).on('click', '.wh-add-attributes-action', function (e) {
        e.preventDefault();
        var $link = $(this);
        var productId = $link.data('product-id');

        if (!productId) {
            alert('شناسه محصول پیدا نشد.');
            return;
        }

        if ($link.hasClass('is-busy')) {
            return;
        }

        $link.data('wh-original-text', $link.text());
        $link.addClass('is-busy').text(getStrings().loading_text || 'در حال تولید...');

        $.post(whData.ajax_url, {
            action: 'wh_generate_attributes',
            nonce: whData.nonce,
            product_id: productId
        }).done(function (resp) {
            $link.removeClass('is-busy');
            if ($link.data('wh-original-text')) {
                $link.text($link.data('wh-original-text'));
            }

            if (resp && resp.success) {
                renderAttributesModal(resp.data || [], productId, $link);
            } else {
                alert((getStrings().error_text || 'خطا') + ': ' + ((resp && resp.data) || 'پاسخ نامعتبر از سرور'));
            }
        }).fail(function () {
            $link.removeClass('is-busy');
            if ($link.data('wh-original-text')) {
                $link.text($link.data('wh-original-text'));
            }
            alert('در ارتباط با سرور خطا رخ داد.');
        });
    });

    $(document).on('click', '.wh-generate-short-description', function (e) {
        e.preventDefault();
        var productId = $('#post_ID').val() || whData.product_id;
        if (!productId) {
            alert('شناسه محصول پیدا نشد.');
            return;
        }

        requestShortDescription(productId, $(this));
    });

    $(document).on('click', '.wh-generate-product-description', function (e) {
        e.preventDefault();
        var productId = $('#post_ID').val() || whData.product_id;
        if (!productId) {
            alert('شناسه محصول پیدا نشد.');
            return;
        }

        requestProductDescription(productId, $(this));
    });

    // -------------------------------------------------------------------------
    // Sample products (settings page)
    // -------------------------------------------------------------------------
    function renderSamplePreview($row, attributes) {
        var strings = getStrings();
        var $preview = $row.find('.wh-sample-preview');

        if (!attributes || !attributes.length) {
            $preview.empty();
            return;
        }

        var html = '<div class="wh-sample-attrs-title">' + escHtml(strings.sample_attrs_label || 'ویژگی‌های خوانده‌شده') + ':</div>';
        html += '<ul class="wh-sample-attrs-list">';
        attributes.forEach(function (attr) {
            var name = (attr && (attr.name || attr.key)) || '';
            var value = (attr && (attr.value || attr.val)) || '';
            if (!name) {
                return;
            }
            html += '<li><strong>' + escHtml(name) + '</strong>' + (value ? ': ' + escHtml(value) : '') + '</li>';
        });
        html += '</ul>';

        $preview.html(html);
    }

    function readStoredSampleAttributes($row) {
        var raw = $row.find('.wh-sample-attributes').val();
        if (!raw) {
            return [];
        }
        try {
            var parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function initExistingSamplePreviews() {
        $('#wh-sample-products .wh-sample-product').each(function () {
            renderSamplePreview($(this), readStoredSampleAttributes($(this)));
        });
    }

    function getNextSampleIndex() {
        var max = -1;
        $('#wh-sample-products .wh-sample-product').each(function () {
            var idx = parseInt($(this).attr('data-index'), 10);
            if (!isNaN(idx) && idx > max) {
                max = idx;
            }
        });
        return max + 1;
    }

    function addSampleRow() {
        var template = $('#wh-sample-row-template').html();
        if (!template) {
            return;
        }
        var index = getNextSampleIndex();
        var markup = template.replace(/__INDEX__/g, index);
        $('#wh-sample-products').append(markup);
    }

    function fetchSampleAttributes($row, $button) {
        var strings = getStrings();
        var url = ($row.find('.wh-sample-url').val() || '').trim();

        if (!url) {
            alert(strings.sample_url_required || 'ابتدا آدرس محصول را وارد کنید.');
            return;
        }

        $button.data('wh-original-text', $button.text());
        $button.prop('disabled', true).addClass('is-busy').text(strings.sample_fetch_loading || 'در حال خواندن...');

        $.post(whData.ajax_url, {
            action: 'wh_fetch_sample_product',
            nonce: whData.nonce,
            url: url
        }).done(function (resp) {
            $button.prop('disabled', false).removeClass('is-busy').text($button.data('wh-original-text') || (strings.sample_fetch_text || 'خواندن ویژگی‌ها'));

            if (resp && resp.success) {
                var data = resp.data || {};
                var attrs = data.attributes || [];

                $row.find('.wh-sample-title').val(data.title || '');
                $row.find('.wh-sample-attributes').val(JSON.stringify(attrs));
                renderSamplePreview($row, attrs);

                if (!attrs.length) {
                    alert(strings.sample_no_attrs || 'هیچ ویژگی‌ای پیدا نشد.');
                }
            } else {
                alert((strings.error_text || 'خطا') + ': ' + ((resp && resp.data) || 'خواندن ویژگی‌ها ناموفق بود.'));
            }
        }).fail(function () {
            $button.prop('disabled', false).removeClass('is-busy').text($button.data('wh-original-text') || (strings.sample_fetch_text || 'خواندن ویژگی‌ها'));
            alert('در ارتباط با سرور خطا رخ داد.');
        });
    }

    $(document).on('click', '#wh-add-sample', function (e) {
        e.preventDefault();
        addSampleRow();
    });

    $(document).on('click', '.wh-remove-sample', function (e) {
        e.preventDefault();
        $(this).closest('.wh-sample-product').remove();
    });

    $(document).on('click', '.wh-fetch-sample', function (e) {
        e.preventDefault();
        fetchSampleAttributes($(this).closest('.wh-sample-product'), $(this));
    });

    $(initEditorAttributeButtonPlacement);
    $(initShortDescriptionButtonPlacement);
    $(initProductDescriptionButtonPlacement);
    $(initExistingSamplePreviews);
})(jQuery);
