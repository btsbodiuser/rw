/**
 * RBT Variation JS
 * © 2025 Sabbir Hossen and Hemal Akhand
 * Released under the RBT License
 */

(function (window, document, $, undefined) {
    'use strict';
    var rbtJs = {
        i: function (e) {
            rbtJs.d();
            rbtJs.methods();
        },

        d: function (e) {
            this._window = $(window),
                this._document = $(document),
                this._body = $('body'),
                this._html = $('html')
        },
        methods: function (e) {
            if ($('.rbt-product-variations').length > 0) {
                rbtJs.productVariations();
            }
        },

        productVariations: function () {

            /**
             * ===========================================
             * Generate Variation Markup.
             * ===========================================
             **/

            function normalizeText(value, fallback) {
                if (value === null || value === undefined || value === '') {
                    return fallback || '';
                }

                return String(value);
            }

            function escapeHtml(value) {
                return normalizeText(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function sanitizeUrl(value, fallback) {
                var safeUrl = normalizeText(value, fallback).trim();

                if (!safeUrl) {
                    return '';
                }

                if (/^(?:javascript|vbscript):/i.test(safeUrl)) {
                    return normalizeText(fallback).trim();
                }

                if (/^data:/i.test(safeUrl) && !/^data:image\//i.test(safeUrl)) {
                    return normalizeText(fallback).trim();
                }

                return safeUrl;
            }

            function sanitizeColor(value, fallback) {
                var safeColor = normalizeText(value, fallback).trim();

                if (!safeColor || /[;"'<>]/.test(safeColor)) {
                    return normalizeText(fallback).trim();
                }

                if (window.CSS && typeof window.CSS.supports === 'function') {
                    return window.CSS.supports('color', safeColor) ? safeColor : normalizeText(fallback).trim();
                }

                if (
                    /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/.test(safeColor) ||
                    /^rgba?\([^)]{1,64}\)$/.test(safeColor) ||
                    /^hsla?\([^)]{1,64}\)$/.test(safeColor) ||
                    /^[a-zA-Z]{3,20}$/.test(safeColor)
                ) {
                    return safeColor;
                }

                return normalizeText(fallback).trim();
            }

            function parseVariationData(rawValue) {
                if (typeof rawValue !== 'string' || rawValue.length === 0) {
                    return null;
                }

                try {
                    return JSON.parse(rawValue);
                } catch (error) {
                    return null;
                }
            }

            function getVariationData($element) {
                if (!$element || !$element.length) {
                    return null;
                }

                var cachedData = $element.data('variations');
                if (cachedData && typeof cachedData === 'object') {
                    return cachedData;
                }

                return parseVariationData($element.attr('data-variations'));
            }

            function formatPriceValue(value) {
                var numericValue = Number(value);

                if (!isFinite(numericValue)) {
                    return '';
                }

                return numericValue % 1 === 0 ? String(numericValue) : numericValue.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
            }

            // generate color variation markup.
            function generateColorVariationMarkup(singleType, type, variation = 'variation-1') {
                const attributes = singleType.attributes;
                let html = '';
                if ('variation-1' === variation) {
                    html += `<div class="rbt-color-select-area" data-variation_key="${escapeHtml(type)}" data-type="list">`;
                    html += '<ul class="rbt-switcher-root rbt-switcher-color-list rbt-switcher-color-list-lg product-switcher-activation">';

                    attributes.forEach((attribute) => {
                        const color = escapeHtml(normalizeText(attribute.color));
                        const colorStyle = escapeHtml(sanitizeColor(attribute.color, '#d9d9d9'));
                        const label = escapeHtml(normalizeText(attribute.label, 'Color'));
                        html += `
                            <li><a class="rbt-switcher--color tooltips"
                                data-tooltip="${label}" data-tooltip-position="top" href="#0" data-value="${color}">
                                <div class="rbt-color-circle" style="background-color: ${colorStyle}"></div>
                            </a></li>
                            `;
                    });
                    html += '</ul>';
                    html += '</div>';
                }

                return html;
            }

            // generate dropdown variation markup.
            function generateDropdownVariationMarkup(singleType, type, variation = 'variation-1') {
                let html = '';
                const attributes = singleType.attributes;
                if ('variation-1' === variation) {
                    html += `<div data-type="select" data-variation_key="${escapeHtml(type)}" class="rbt-modern-select single-prd-select height-36 rbt-rounded-styled rbt-modern-select-md-width rbt-modern-select-md-width">`;
                    html += '<select class="rbt-switcher-root rbt-select-activation">';
                    attributes.forEach((attribute) => {
                        const label = escapeHtml(normalizeText(attribute.label, 'Option'));
                        const slug = escapeHtml(normalizeText(attribute.slug));
                        html += `<option value="${slug}" data-value="${slug}">${label}</option>`;

                    });
                    html += '</select>';
                    html += '</div>';
                }

                return html;
            }

            // generate button variation markup.
            function generateButtonVariationMarkup(singleType, type, variation = "variation-1") {
                let html = '';
                let attributes = singleType.attributes;
                if ('variation-1' === variation) {
                    html += `<div data-type="list" data-variation_key="${escapeHtml(type)}" class="rbt-product-switch-area">`;
                    html += '<ul class="rbt-switcher-root rbt-switcher-product-list product-switcher-activation">';
                    attributes.forEach((attribute) => {
                        const label = escapeHtml(normalizeText(attribute.label, 'Image'));
                        const slug = escapeHtml(normalizeText(attribute.slug));
                        if ('variation-1' === variation) {
                            html += `<li>`;
                            html += `<a data-value="${slug}" class="rbt-btn rbt-btn-border rbt-btn-xs rbt-store-button" href="#0">
                                                        <span class="rbt-store-radio-button">${label}</span>
                                                </a>`;
                            html += '</li>';
                        }
                    });
                    html += '</ul>';
                    html += '</div>';
                }

                return html;
            }

            // generate image variation markup.
            function generateImageVariationMarkup(singleType, type, variation = 'variation-1') {
                let html = '';
                let attributes = singleType.attributes;
                if ('variation-1' === variation) {
                    html += `<div data-type="list" data-variation_key="${escapeHtml(type)}" class="rbt-product-switch-area">`;
                    html += '<ul class="rbt-switcher-root rbt-switcher-product-list product-switcher-activation">';
                    attributes.forEach((attribute) => {
                        const image_url = escapeHtml(normalizeText(attribute.image_url));
                        const safeImageUrl = escapeHtml(sanitizeUrl(attribute.image_url, 'assets/images/system/placeholder.png'));
                        const alt = escapeHtml(normalizeText(attribute.alt, 'Image'));
                        const label = escapeHtml(normalizeText(attribute.label, 'Image'));
                        if ('variation-1' === variation) {
                            html += `<li>`;
                            html += `<a data-value="${image_url}" class="rbt-switcher--prd rbt-bg-color-brand-100 rbt-image-tooltip-activation rbt-switcher--prd-one" href="#0">
                                                <img class="rbt-h-unset" src="${safeImageUrl}" alt="${alt}">
                                                <div class="rbt-image-tooltip-box">
                                                    <img class="rbt-h-unset" src="${safeImageUrl}" alt="${alt}">
                                                    <span class="img-desc-text">${label}</span>
                                                </div>
                                            </a>`;
                            html += '</li>';
                        }
                    });
                    html += '</ul>';
                    html += '</div>';
                }

                return html;
            }

            // Generate variation markup and return.
            function generateVariationMakup(singleType, typeOfVariation) {
                let html = '';
                const label = escapeHtml(normalizeText(singleType?.label, 'Option'));
                const type = singleType?.type;

                let markup = '';

                switch (type) {
                    case 'color':
                        markup += generateColorVariationMarkup(singleType, typeOfVariation);
                        break;
                    case 'dropdown':
                        markup += generateDropdownVariationMarkup(singleType, typeOfVariation);
                        break;
                    case 'image':
                        markup += generateImageVariationMarkup(singleType, typeOfVariation);
                        break;
                    case 'button':
                        markup += generateButtonVariationMarkup(singleType, typeOfVariation);
                        break;

                }

                // replace markup.

                html += `
                    <div class="rbt-info-wrapper d-flex mt--24">
                        <div class="prd-info-section">
                            <div class="prd-id-text">
                                <p class="text-bold rbt-variation-label">${label}: <span class="rbt-text">No Selection</span></p>
                                ${markup}
                            </div>
                        </div>
                    </div>
                `;

                return html;
            }

            // Check if two object are equal.
            function areObjectsEqual(obj1, obj2) {
                // Check if both are objects
                if (typeof obj1 !== 'object' || typeof obj2 !== 'object' || obj1 === null || obj2 === null) {
                    return obj1 === obj2; // Check primitive values
                }

                // Get keys of both objects
                const keys1 = Object.keys(obj1);
                const keys2 = Object.keys(obj2);

                // Check if number of keys is different
                if (keys1.length !== keys2.length) {
                    return false;
                }

                // Check if every key in obj1 is also in obj2 and their values are equal
                for (const key of keys1) {
                    // Check if the key exists in obj2
                    if (!keys2.includes(key)) {
                        return false; // Key No Selection in obj2
                    }
                    // Check if the values are equal (recursively)
                    if (!areObjectsEqual(obj1[key], obj2[key])) {
                        return false; // Values are not equal
                    }
                }

                return true; // All checks passed
            }

            // Generate thumbnails.
            function generateThumbnails(selectedVariationValues, variation = 'variation-1') {
                let thumbHTML = '';
                const thumbnails = selectedVariationValues?.images?.thumbnails;
                if (thumbnails) {
                    Object.keys(thumbnails).forEach((index) => {
                        const thumbnail = thumbnails[index];
                        const alt = escapeHtml(normalizeText(thumbnail?.alt, 'Image'));
                        const url = escapeHtml(sanitizeUrl(thumbnail?.url, 'assets/images/system/placeholder.png'));
                        if ('variation-1' === variation) {
                            thumbHTML += `
                            <div class="swiper-slide rbt-scroll-trigger fade_in animation-order-${index}">
                                <button class="thumbnail d-block position-relative">
                                    <span class="rbt-thumb-img-sm">
                                        <img class="w-100" src="${url}" alt="${alt}">
                                    </span>
                                </button>
                            </div>
                            `;
                        }

                    })
                }

                return thumbHTML;
            }

            // Generate main images.
            function generateMainImages(selectedVariationValues, variation = 'variation-1') {
                let imageHTML = '';
                const images = selectedVariationValues?.images?.full;
                if (images) {
                    Object.keys(images).forEach((index) => {
                        const image = images[index] || 'assets/images/system/placeholder.png';
                        const url = escapeHtml(sanitizeUrl(image?.url, 'assets/images/system/placeholder.png'));
                        const alt = escapeHtml(normalizeText(image?.alt, 'Alt Content'));
                        if ('variation-1' === variation) {
                            imageHTML += `
                            <div class="swiper-slide rbt-scroll-trigger fade_in animation-order-${index}">
                                <div class="thumbnail">
                                    <div class="rbt-product-single-img">
                                        <img class="w-100" data-fancybox="product-single-image" src="${url}" alt="${alt}">
                                    </div>
                                </div>
                            </div>
                            `;
                        }
                    })
                }

                return imageHTML;

            }

            // Generate thumbnails.
            function generateBothImages(selectedVariationValues, variation = 'variation-default') {
                let thumbHTML = '';
                const thumbnails = selectedVariationValues?.images?.thumbnails;
                const fullImages = selectedVariationValues?.images?.full;
                let zeroBaseIndex = 0;
                let stringOne;
                let stringTwo;

                if ('variation-masonary' === variation) {
                    stringOne = 'small-thumbnail';
                    stringTwo = 'large-thumbnail';
                }

                if (thumbnails) {
                    Object.keys(thumbnails).forEach((index) => {
                        // Thumbnail attributes.
                        const thumbnail = thumbnails[index];
                        const thumbnailAlt = escapeHtml(normalizeText(thumbnail?.alt, 'Image'));
                        const thumbnailURL = escapeHtml(sanitizeUrl(thumbnail?.url, 'assets/images/system/placeholder.png'));


                        // Full image attributes.
                        const fullImage = fullImages[index];
                        const fullImageAlt = escapeHtml(normalizeText(fullImage?.alt, 'Image'));
                        const fullImageURL = escapeHtml(sanitizeUrl(fullImage?.url, 'assets/images/system/placeholder.png'));

                        if ('variation-masonary' === variation) {
                            if (0 === zeroBaseIndex) {
                                thumbHTML += `
                                    <div class="col-xl-12 col-sm-6 col-12 rbt-scroll-trigger fade_in animation-order-${index}">
                                        <div class="thumbnail position-relative">
                                            <a class="rbt-product-single-img" data-fancybox="gallery" href="${fullImageURL}">
                                                <img class="w-100 rbt-rounded--12" src="${thumbnailURL}" alt="${thumbnailAlt}">
                                            </a>
                                    </div>
                                </div>
                                `;
                            } else {
                                let output;
                                if (Math.floor((zeroBaseIndex - 1) / 2) % 2 === 0) {
                                    output = (zeroBaseIndex % 2 !== 0) ? stringOne : stringTwo;
                                } else {
                                    output = (zeroBaseIndex % 2 !== 0) ? stringTwo : stringOne;
                                }
                                if (output === 'small-thumbnail') {
                                    thumbHTML += `
                                    <div class="col-xl-4 col-sm-6 col-12 mt--16 rbt-scroll-trigger fade_in animation-order-${index}">
                                        <div class="thumbnail">
                                            <a class="rbt-product-single-img" data-fancybox="gallery" href="${fullImageURL}">
                                                <img class="w-100 rbt-rounded--12" src="${thumbnailURL}" alt="${thumbnailAlt}">
                                            </a>
                                        </div>
                                    </div>
                                    `;
                                } else {
                                    thumbHTML += `
                                    <div class="col-xl-8 col-sm-6 col-12 mt--16 rbt-scroll-trigger fade_in animation-order-${index}">
                                        <div class="thumbnail">
                                            <a class="rbt-product-single-img" data-fancybox="gallery" href="${fullImageURL}">
                                                <img class="w-100 rbt-rounded--12" src="${thumbnailURL}" alt="${thumbnailAlt}">
                                            </a>
                                        </div>
                                    </div>
                                    `;
                                }
                            }

                        }
                        zeroBaseIndex++;
                    })
                }

                return thumbHTML;
            }

            // Generate thumbnails.
            function generateGrid4Images(selectedVariationValues, variation = 'variation-default') {
                let thumbHTML = '';
                const thumbnails = selectedVariationValues?.images?.thumbnails;
                const fullImages = selectedVariationValues?.images?.full;

                if (thumbnails) {
                    Object.keys(thumbnails).forEach((index) => {
                        // Thumbnail attributes.
                        const thumbnail = thumbnails[index];
                        const thumbnailAlt = escapeHtml(normalizeText(thumbnail?.alt, 'Product Images'));
                        const thumbnailURL = escapeHtml(sanitizeUrl(thumbnail?.url, 'assets/images/system/placeholder.png'));

                        // Full image attributes.
                        const fullImage = fullImages[index];
                        const fullImageURL = escapeHtml(sanitizeUrl(fullImage?.url, 'assets/images/system/placeholder.png'));

                        if (variation === 'grid-4') {
                            thumbHTML += `
                            <div class="col-lg-6 col-6 mt--24 rbt-scroll-trigger fade_in animation-order-${index}"">
                                <div class="thumbnail">
                                    <a href="${fullImageURL}" data-fancybox="gallery" class="rbt-product-single-img">
                                        <img class="rbt-rounded--12" src="${thumbnailURL}" data-zoom-image="${fullImageURL}" alt="${thumbnailAlt}">
                                    </a>
                                </div>
                            </div>
                        `;
                        }
                    });
                }

                return thumbHTML;
            }

            // Generate price markup.
            function generatePriceMarkup(selectedVariationValues, variation = 'variation-1') {
                let html = '';

                const price = selectedVariationValues?.price;

                if (undefined === price) {
                    return '';
                }

                const regularPrice = Number(price.regular);
                const salePrice = price.sale === undefined || price.sale === null || price.sale === '' ? null : Number(price.sale);

                if (!isFinite(regularPrice)) {
                    return '';
                }

                if (salePrice !== null && isFinite(salePrice) && salePrice < regularPrice) {
                    const originalPrice = formatPriceValue(salePrice);
                    const formattedRegularPrice = formatPriceValue(regularPrice);
                    const savingsPercentage = Math.round(((regularPrice - salePrice) / regularPrice) * 100); // Calculate savings percentage
                    if ('variation-1' === variation) {
                        html += `
                        <div class="pricing-part mt--0">
                            <del class="price-text">$${formattedRegularPrice}</del>
                            <span class="price-text">$${originalPrice}</span>
                            <span class="rbt-offer-badge rbt-offer-badge-md">Save ${savingsPercentage}%</span>
                        </div>
                        `;
                    }
                } else {
                    if ('variation-1' === variation) {
                        const formattedRegularPrice = formatPriceValue(regularPrice);

                        html += `
                        <div class="pricing-part mt--0">
                            <span class="price-text">$${formattedRegularPrice}</span>
                        </div>
                        `;
                    }
                }

                return html; // Return the generated HTML
            }


            // Generate variation attributes.
            $('.rbt-product-variations').each((_index, element) => {
                let html = '';
                const $this = $(element);
                const variationAtts = getVariationData($this);
                if (!variationAtts || !variationAtts.global || !variationAtts.global.types) return;

                const globalTypes = variationAtts?.global?.types;
                Object.keys(globalTypes).forEach(type => {
                    const singleType = globalTypes[type];

                    html += generateVariationMakup(singleType, type);

                });

                $('.rbt-store-variation-controls').html(html);
            });

            /**
             * ===========================================
             * Variation Functionality.
             * ===========================================
             */

            // change gallery image based on variation.
            const variations = getVariationData($('.rbt-product-variations').first());

            if (!variations || !variations.local || !variations.global || !variations.global.types) {
                return;
            }

            const localAtts = variations.local;
            // watch all available variations.
            const availableVariationTypes = variations.global.types;
            const availableVariationTypeKeys = Object.keys(availableVariationTypes);
            const variationValues = availableVariationTypeKeys.reduce((acc, key) => {
                acc[key] = null;
                return acc;
            }, {});

            const defaultLayoutOneMainImageMarkup = $('.rbt-store-thumb-main-1').html();
            const defaultLayoutOneThumbImageMarkup = $('.rbt-store-thumb-variation-1').html();
            let initialVariationMarkup;
            const variationLayout = $(document).find('.rbt-store-variation-controls').attr('data-variation');

            // Handle default markup.
            if ('variation-masonary' === variationLayout) {
                initialVariationMarkup = $('.rbt-masonary-variation-markup').html();
            }
            if ('variation-grid-4' === variationLayout) {
                initialVariationMarkup = $('.rbt-grid-4-variation-markup').html();
            }
            if ('variation-grid-masonary' === variationLayout) {
                initialVariationMarkup = $('.rbt-grid-masonary-variation-markup').html();
            }


            // switcher support
            $(document).on('click', '.rbt-switcher-root li a', function (e) {
                const $this = $(e.target);

                $(document).trigger('changed_variation', $this);
                const value = $this.closest('[data-value]').attr('data-value');

                const variationName = $this.closest('[data-variation_key]').attr('data-variation_key');
                variationValues[variationName] = value;

                // If any of variation is null, then return.
                for (const key in variationValues) {
                    if (variationValues[key] === null) {
                        return; // Exit the function if any value is null
                    }
                }


                /**
                 * Process the selected variation values.
                 */
                let selectedVariationValues = {};

                Object.keys(localAtts).forEach((key) => {
                    const singleObj = localAtts[key];
                    const attributes = singleObj.attributes;
                    // check if 2 objects are equal.
                    if (areObjectsEqual(attributes, variationValues)) {
                        selectedVariationValues = singleObj;
                        return;
                    }
                })

                // Handle thumbnail and images.
                if (selectedVariationValues) {
                    const thumbnails = generateThumbnails(selectedVariationValues);
                    const mainImage = generateMainImages(selectedVariationValues);
                    const bothImage = generateBothImages(selectedVariationValues, variationLayout);
                    const grid4Images = generateGrid4Images(selectedVariationValues, variationLayout);
                    const gridbothImage = generateGrid4Images(selectedVariationValues, variationLayout);

                    const priceMarkup = generatePriceMarkup(selectedVariationValues);

                    $('.rbt-store-thumb-variation-1').html(thumbnails);
                    if (mainImage.length > 0) {
                        $('.rbt-store-thumb-main-1').html(mainImage);
                    } else {
                        $('.rbt-store-thumb-main-1').html(defaultLayoutOneMainImageMarkup);
                        $('.rbt-store-thumb-variation-1').html(defaultLayoutOneThumbImageMarkup);
                    };

                    $('.rbt-store-price-1').html(priceMarkup);

                    if ('variation-masonary' === variationLayout) {
                        if (bothImage.length > 0) {
                            $('.rbt-masonary-variation-markup').html(bothImage);
                        } else {
                            $('.rbt-masonary-variation-markup').html(initialVariationMarkup);
                        }
                    };
                    if ('grid-4' === variationLayout) {
                        if (grid4Images.length > 0) {
                            $('.rbt-grid-4-variation-markup').html(grid4Images);
                        } else {
                            $('.rbt-grid-4-variation-markup').html(initialVariationMarkup);
                        }

                    };
                    if ('variation-grid-masonary' === variationLayout) {

                        if (gridbothImage.length > 0) {
                            $('.rbt-grid-masonary-variation-markup').html(gridbothImage);
                        } else {
                            $('.rbt-grid-masonary-variation-markup').html(initialVariationMarkup);
                        }


                    };

                    $(document).trigger('gallery_loaded');
                }




            })
            // Enable/disable field based on variation.
            let selectedValues = [];
            $(document).on('click change', '.rbt-switcher-root li a, .rbt-switcher-root select', function (e) {
                $(document).trigger('changed_variation')
                let isFoundVariation = true;
                const $this = $(e.target);

                const rootType = $this.closest('[data-type]').attr('data-type');
                let variationMain = $this.closest('.rbt-product-variations');
                let variation = getVariationData(variationMain);
                const variationKey = $this.closest('[data-variation_key]').attr('data-variation_key');
                let variationValues = '';
                let dataValue;
                const currentItemValue = $this.attr('data-value');
                const rootClass = $this.closest('.rbt-info-wrapper');

                let tooltip;

                if ('click' === e.type) {
                    tooltip = $this.attr('data-tooltip');

                    if (undefined === tooltip) {
                        tooltip = $this.closest('.rbt-switcher--prd').find('.img-desc-text').text();
                    }

                    if ($this.hasClass('rbt-store-radio-button')) {
                        tooltip = $this.text();
                    }

                } else if ('change' === e.type) {
                    // Handle 'select' event here
                    tooltip = $(this).find('option:selected').text();
                }

                rootClass.find('.rbt-variation-label .rbt-text').text(tooltip || 'No Selection');



                const parsedVariation = variation;
                if (!parsedVariation || !parsedVariation.local) {
                    return;
                }
                if ($this.hasClass('rbt-switcher--color')) {
                    Object.keys(parsedVariation.local).forEach((key) => {
                        const parsedVariationObj = parsedVariation.local[key];
                        const parsedAtts = parsedVariationObj.attributes;
                        Object.values(parsedAtts).map((item) => {
                            if (item !== currentItemValue) {
                                isFoundVariation = false;
                            }
                        })
                    });
                }

                if (false === isFoundVariation) {
                    $(document).trigger('disable_all_variations', $this);
                }
                if ('list' === rootType) {
                    dataValue = $this.closest('[data-value]').attr('data-value');
                } else if ('select' === rootType) {
                    dataValue = $this.val();
                }

                if (variation && variation.local) {
                    const localValue = variation.local;
                    const attributes = localValue[0].attributes;
                    variationValues = Object.keys(attributes).reduce((acc, key) => {
                        acc[key] = null;
                        return acc;
                    }, {});

                    // Deep drive to variation and generate matched attributes.
                    Object.keys(localValue).forEach((key) => {
                        const obj = localValue[key];
                        const objAtts = obj.attributes;
                        const clickedObj = objAtts[variationKey];
                        if (clickedObj === dataValue) {
                            Object.keys(objAtts).forEach((key) => {
                                const currentObj = objAtts[key];
                                if (variationValues[key] !== currentObj) {
                                    if (!Array.isArray(variationValues[key])) {
                                        variationValues[key] = [];
                                    }

                                    // Add currentObj to the array if it's not already present
                                    if (!variationValues[key].includes(currentObj)) {
                                        variationValues[key].push(currentObj);
                                    }
                                }
                            })


                        }
                    })
                    variationMain.find('[data-variation_key]').each((index, element) => {
                        const $this = $(element);
                        const variationKeyInner = $this.attr('data-variation_key');

                        const type = $this.attr('data-type');
                        if (type === 'list') {
                            const selector = $this.find('ul');
                            selector.find('li a').each((index, element) => {

                                const currentList = $(element);
                                const value = currentList.attr('data-value');

                                if (variationKeyInner !== variationKey) {
                                    if ((null !== variationValues[variationKeyInner]) && !variationValues[variationKeyInner].includes(value)) {
                                        currentList.addClass('disabled rbt-disabled', true);
                                    } else {
                                        currentList.removeClass('disabled rbt-disabled', true);
                                    }
                                }
                            });
                        } else if (type === 'select') {
                            const selector = $this.find('select');
                            // disabled based on condition.
                            selector.find('option').each((index, element) => {
                                const currentList = $(element);
                                const value = currentList.attr('data-value');
                                if ((variationKeyInner !== variationKey) && (null !== variationValues[variationKeyInner])) {

                                    if (!variationValues[variationKeyInner].includes(value)) {
                                        currentList.attr('disabled', true);
                                    } else {
                                        currentList.removeAttr('disabled', true);
                                    }
                                }
                            })

                            $(document).find('.rbt-select-activation').selectpicker('refresh');
                        }



                    });
                }


            });
            $(document).on('changed_variation', function () {
                $('.rbt-store-reset-variations').html('<button class="rbt-reset-variation rbt-btn rbt-btn-black rbt-btn-xs mt--16 rbt-scroll-trigger fade_in animation-order-1">Reset All</button>');
            });
            $(document).on('click', '.rbt-reset-variation', function (e) {
                const $this = $(e.currentTarget); // Use currentTarget instead of target
                const rootVariation = $this.closest('.rbt-product-variations');

                // Remove 'disabled' and 'rbt-disabled' classes from elements with 'rbt-disabled' class
                rootVariation.find('.rbt-disabled').removeClass('disabled rbt-disabled');
                rootVariation.find('[disabled="disabled"]').removeAttr('disabled');
                $(document).find('.rbt-select-activation').selectpicker('refresh');
                rootVariation.find('.rbt-variation-label .rbt-text').text('No Selection');
                rootVariation.find('.product-switcher-activation li').removeClass('active');
                $this.remove();
            });
            $(document).on('disable_all_variations', function (e, $this) {

                $this = $($this);
                const rootVariation = $this.closest('.rbt-product-variations');
                const currentVariation = $this.closest('[data-variation_key]');
                rootVariation.find('[data-variation_key]').find('ul li a').attr('disabled', true);
                rootVariation.find('[data-variation_key]').find('ul li a').addClass('disabled');
                rootVariation.find('select').find('option').each((index, element) => {
                    const currentList = $(element);
                    currentList.attr('disabled', true);
                })
                currentVariation.find('ul li a').attr('disabled', false);
                currentVariation.find('ul li a').removeClass('disabled');

                $(document).find('.rbt-select-activation').selectpicker('refresh');

            })

            $(document).on('change', '.rbt-switcher-root select', function (e) {
                // variationValues.rbt_product_attribute_style = 'casual';
                const $this = $(e.target);
                const value = $this.val();
                const variationName = $this.closest('[data-variation_key]').attr('data-variation_key');

                variationValues[variationName] = value;

            })

        }

    }
    $(window).ready(() => {
        rbtJs.i();
    })


})(window, document, jQuery);



