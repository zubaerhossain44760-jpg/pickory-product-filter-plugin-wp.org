/**
 * Pickory Product Filter — Admin Term Meta JS
 * Handles color picker and media library for swatch thumbnails.
 */
(function ($) {
    'use strict';

    $(function () {
        // 1. Initialize Color Picker
        $('.ppfx-color-picker').wpColorPicker();

        // 2. Media Uploader
        let media_wrapper;

        $(document).on('click', '.ppfx-image-upload-btn', function (e) {
            e.preventDefault();

            const btn = $(this);
            media_wrapper = btn.closest('.ppfx-image-upload-wrapper');

            const frame = wp.media({
                title: 'Select or Upload Swatch Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            });

            frame.on('select', function () {
                const attachment = frame.state().get('selection').first().toJSON();

                // Update hidden input
                media_wrapper.find('input[type="hidden"]').val(attachment.id);

                // Update preview
                const preview = media_wrapper.find('.ppfx-image-preview');
                preview.find('img').attr('src', attachment.url);
                preview.show();

                // Show remove button
                media_wrapper.find('.ppfx-image-remove-btn').show();
            });

            frame.open();
        });

        // 3. Remove Image Handler
        $(document).on('click', '.ppfx-image-remove-btn', function (e) {
            e.preventDefault();

            const btn = $(this);
            const wrapper = btn.closest('.ppfx-image-upload-wrapper');

            wrapper.find('input[type="hidden"]').val('');
            wrapper.find('.ppfx-image-preview').hide().find('img').attr('src', '');
            btn.hide();
        });

        // 4. Force color picker initialization on dynamic rows (if WooCommerce uses them)
        $(document).ajaxComplete(function () {
            $('.ppfx-color-picker').each(function () {
                if (!$(this).next().hasClass('wp-picker-container')) {
                    $(this).wpColorPicker();
                }
            });
        });
    });

})(jQuery);
