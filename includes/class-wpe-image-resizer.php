<?php
/**
 * Image Resizer module.
 *
 * Resizes images to a max of 800px or 1200px, overwriting the original file.
 *
 * @package eCommerceWunderkiste
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WPE_Image_Resizer
 */
class WPE_Image_Resizer {

    /**
     * Allowed target sizes.
     *
     * @var int[]
     */
    private $allowed_sizes = array( 800, 1200 );

    /**
     * Constructor.
     */
    public function __construct() {
        add_filter( 'attachment_fields_to_edit', array( $this, 'add_resize_button' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_wpe_resize_image', array( $this, 'ajax_resize_image' ) );
        add_filter( 'manage_upload_columns', array( $this, 'add_list_column' ) );
        add_action( 'manage_media_custom_column', array( $this, 'fill_list_column' ), 10, 2 );
        add_filter( 'bulk_actions-upload', array( $this, 'add_bulk_actions' ) );
    }

    /**
     * Can the current user resize this specific attachment?
     *
     * `upload_files` alone is not enough: it is granted to Authors, and the
     * resize overwrites the original file irreversibly. We therefore require
     * edit rights on the individual attachment as well.
     *
     * @param int $attachment_id Attachment ID.
     * @return bool
     */
    private function user_can_resize( $attachment_id ) {
        $attachment_id = (int) $attachment_id;

        if ( $attachment_id <= 0 ) {
            return false;
        }

        if ( ! current_user_can( 'upload_files' ) ) {
            return false;
        }

        return current_user_can( 'edit_post', $attachment_id );
    }

    /**
     * Add resize buttons to the attachment details modal.
     *
     * @param array   $form_fields Attachment form fields.
     * @param WP_Post $post        Attachment post.
     * @return array
     */
    public function add_resize_button( $form_fields, $post ) {
        if ( ! wp_attachment_is_image( $post->ID ) || ! $this->user_can_resize( $post->ID ) ) {
            return $form_fields;
        }

        $metadata     = wp_get_attachment_metadata( $post->ID );
        $current_size = '';

        if ( is_array( $metadata ) && isset( $metadata['width'], $metadata['height'] ) ) {
            $current_size = '<span class="wpe-current-size" style="color:#666;font-size:12px;">' .
                sprintf(
                    /* translators: %1$d: width in pixels, %2$d: height in pixels */
                    esc_html__( 'Current: %1$d x %2$d px', 'ecommerce-wunderkiste' ),
                    (int) $metadata['width'],
                    (int) $metadata['height']
                ) .
                '</span><br>';
        }

        $nonce = wp_create_nonce( 'wpe_resize_' . $post->ID );
        $html  = $current_size . '<div class="wpe-resize-container" style="display:flex;gap:8px;margin-bottom:8px;">';

        foreach ( $this->allowed_sizes as $size ) {
            $html .= '<button type="button" class="button button-small wpe-resize-trigger" data-id="' . esc_attr( $post->ID )
                . '" data-size="' . esc_attr( $size ) . '" data-nonce="' . esc_attr( $nonce ) . '">' . esc_html( $size . 'px' ) . '</button>';
        }

        $html .= '</div><p class="description" style="margin-top:5px;">'
            . esc_html__( 'Overwrites the original image (92% quality). This cannot be undone.', 'ecommerce-wunderkiste' )
            . '</p><span class="wpe-resize-status" style="color:#2271b1;font-weight:bold;display:none;"></span>';

        $form_fields['wpe_resize'] = array(
            'label' => __( 'Resize', 'ecommerce-wunderkiste' ),
            'input' => 'html',
            'html'  => $html,
        );

        return $form_fields;
    }

    /**
     * Register bulk actions in the media library.
     *
     * @param array $bulk_actions Existing bulk actions.
     * @return array
     */
    public function add_bulk_actions( $bulk_actions ) {
        if ( ! current_user_can( 'upload_files' ) ) {
            return $bulk_actions;
        }

        $bulk_actions['wpe_bulk_800']  = __( 'Resize to 800px', 'ecommerce-wunderkiste' );
        $bulk_actions['wpe_bulk_1200'] = __( 'Resize to 1200px', 'ecommerce-wunderkiste' );

        return $bulk_actions;
    }

    /**
     * Enqueue the resizer script and styles.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_scripts( $hook ) {
        $screen = get_current_screen();

        if ( ! $screen || ! in_array( $screen->base, array( 'upload', 'post' ), true ) ) {
            return;
        }

        if ( ! current_user_can( 'upload_files' ) ) {
            return;
        }

        wp_register_script( 'wpe-image-resizer', false, array( 'jquery' ), WPE_VERSION, true );
        wp_enqueue_script( 'wpe-image-resizer' );

        wp_localize_script(
            'wpe-image-resizer',
            'wpeResizer',
            array(
                'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
                'bulkNonce'  => wp_create_nonce( 'wpe_bulk_resize' ),
                'i18n'       => array(
                    'confirmSingle' => __( 'Permanently resize this image to %dpx? The original will be overwritten.', 'ecommerce-wunderkiste' ),
                    'confirmBulk'   => __( 'Resize the selected images to %dpx? The originals will be overwritten.', 'ecommerce-wunderkiste' ),
                    'working'       => __( 'Working...', 'ecommerce-wunderkiste' ),
                    'done'          => __( 'Done:', 'ecommerce-wunderkiste' ),
                    'unknownError'  => __( 'Unknown error', 'ecommerce-wunderkiste' ),
                    'serverError'   => __( 'Server error.', 'ecommerce-wunderkiste' ),
                    'selectOne'     => __( 'Please select at least one image.', 'ecommerce-wunderkiste' ),
                    'resizing'      => __( 'Resizing images...', 'ecommerce-wunderkiste' ),
                    'finished'      => __( 'Finished!', 'ecommerce-wunderkiste' ),
                    'successful'    => __( 'successful', 'ecommerce-wunderkiste' ),
                    'errors'        => __( 'errors', 'ecommerce-wunderkiste' ),
                    'current'       => __( 'Current:', 'ecommerce-wunderkiste' ),
                ),
            )
        );

        wp_add_inline_script( 'wpe-image-resizer', $this->get_script() );

        wp_register_style( 'wpe-image-resizer', false, array(), WPE_VERSION );
        wp_enqueue_style( 'wpe-image-resizer' );
        wp_add_inline_style(
            'wpe-image-resizer',
            '.column-wpe_resizer{width:120px}'
            . '.wpe-resize-container .button{min-width:45px;padding:0 8px}'
            . '.wpe-current-size strong{color:#1d2327}'
            . '#wpe-bulk-modal{position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:100000;display:flex;align-items:center;justify-content:center}'
            . '#wpe-bulk-modal .wpe-bulk-inner{background:#fff;padding:30px;border-radius:8px;max-width:500px;width:90%}'
            . '.wpe-progress-bar{background:#ddd;height:24px;border-radius:12px;overflow:hidden;margin-bottom:15px}'
            . '.wpe-progress-fill{background:#2271b1;height:100%;width:0;transition:width .3s}'
            . '.wpe-progress-log{max-height:200px;overflow-y:auto;margin-top:15px;font-size:12px;background:#f6f7f7;padding:10px;border-radius:4px}'
        );
    }

    /**
     * The resizer JavaScript.
     *
     * All server-supplied strings are inserted with .text()/.append() rather
     * than string-concatenated HTML, so attachment titles cannot inject markup.
     *
     * @return string
     */
    private function get_script() {
        return <<<'JS'
jQuery(function ($) {
    var i18n = wpeResizer.i18n;

    function sprintfSize(template, size) {
        return template.replace('%d', size);
    }

    $(document).on('click', '.wpe-resize-trigger', function (e) {
        e.preventDefault();

        var button = $(this);
        var container = button.closest('.wpe-resize-container');
        var scope = container.length ? container.parent() : button.parent();
        var status = scope.find('.wpe-resize-status').first();
        var sizeDisplay = scope.find('.wpe-current-size').first();
        var attachmentId = button.data('id');
        var targetSize = parseInt(button.data('size'), 10) || 800;

        if (!window.confirm(sprintfSize(i18n.confirmSingle, targetSize))) {
            return;
        }

        scope.find('.wpe-resize-trigger').prop('disabled', true);
        status.text(i18n.working).css('color', '#2271b1').show();

        $.ajax({
            url: wpeResizer.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpe_resize_image',
                attachment_id: attachmentId,
                target_size: targetSize,
                security: button.data('nonce')
            },
            success: function (response) {
                if (response && response.success) {
                    status.text(i18n.done + ' ' + response.data.dimensions).css('color', 'green');
                    if (sizeDisplay.length) {
                        sizeDisplay.text(i18n.current + ' ' + response.data.dimensions);
                    }
                    window.setTimeout(function () {
                        scope.find('.wpe-resize-trigger').prop('disabled', false);
                        status.fadeOut();
                    }, 3000);
                } else {
                    var message = (response && response.data && response.data.message) ? response.data.message : i18n.unknownError;
                    status.text(message).css('color', 'red');
                    scope.find('.wpe-resize-trigger').prop('disabled', false);
                }
            },
            error: function () {
                status.text(i18n.serverError).css('color', 'red');
                scope.find('.wpe-resize-trigger').prop('disabled', false);
            }
        });
    });

    $(document).on('click', '#doaction, #doaction2', function (e) {
        var action = $(this).prev('select').val();

        if (action !== 'wpe_bulk_800' && action !== 'wpe_bulk_1200') {
            return;
        }

        e.preventDefault();

        var size = action === 'wpe_bulk_800' ? 800 : 1200;
        var checked = $('input[name="media[]"]:checked');

        if (checked.length === 0) {
            window.alert(i18n.selectOne);
            return;
        }

        if (!window.confirm(sprintfSize(i18n.confirmBulk, size))) {
            return;
        }

        var ids = checked.map(function () { return $(this).val(); }).get();

        var inner = $('<div class="wpe-bulk-inner"></div>');
        var heading = $('<h2 style="margin:0 0 20px;color:#1d2327;"></h2>').text(i18n.resizing);
        var bar = $('<div class="wpe-progress-bar"><div class="wpe-progress-fill"></div></div>');
        var text = $('<div class="wpe-progress-text" style="text-align:center;font-size:14px;color:#50575e;"></div>').text('0 / ' + ids.length);
        var log = $('<div class="wpe-progress-log"></div>');
        inner.append(heading, bar, text, log);

        var modal = $('<div id="wpe-bulk-modal"></div>').append(inner);
        $('body').append(modal);

        var processed = 0;
        var success = 0;
        var errors = 0;

        function logLine(color, label, detail) {
            $('<div style="margin-bottom:3px;"></div>')
                .css('color', color)
                .text(label + ': ' + detail)
                .prependTo(log);
        }

        function processNext(index) {
            if (index >= ids.length) {
                text.text(i18n.finished + ' ' + success + ' ' + i18n.successful + ', ' + errors + ' ' + i18n.errors);
                window.setTimeout(function () {
                    modal.fadeOut(300, function () {
                        $(this).remove();
                        window.location.reload();
                    });
                }, 2000);
                return;
            }

            var id = ids[index];
            var row = $('input[name="media[]"][value="' + id + '"]').closest('tr');
            var title = $.trim(row.find('.column-title strong').text()) || ('ID ' + id);

            $.post(wpeResizer.ajaxUrl, {
                action: 'wpe_resize_image',
                attachment_id: id,
                target_size: size,
                security: wpeResizer.bulkNonce
            }, function (r) {
                processed++;
                bar.find('.wpe-progress-fill').css('width', Math.round((processed / ids.length) * 100) + '%');
                text.text(processed + ' / ' + ids.length);

                if (r && r.success) {
                    success++;
                    logLine('#00a32a', title, r.data.dimensions);
                } else {
                    errors++;
                    logLine('#d63638', title, (r && r.data && r.data.message) ? r.data.message : i18n.unknownError);
                }

                window.setTimeout(function () { processNext(index + 1); }, 200);
            }).fail(function () {
                processed++;
                errors++;
                logLine('#d63638', title, i18n.serverError);
                window.setTimeout(function () { processNext(index + 1); }, 200);
            });
        }

        processNext(0);
    });
});
JS;
    }

    /**
     * AJAX handler.
     */
    public function ajax_resize_image() {
        $attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
        $target_size   = isset( $_POST['target_size'] ) ? absint( wp_unslash( $_POST['target_size'] ) ) : 0;
        $nonce         = isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : '';

        if ( ! in_array( $target_size, $this->allowed_sizes, true ) ) {
            $target_size = 800;
        }

        // Accept either the per-attachment nonce or the bulk nonce. Which one
        // is checked is no longer decided by a request parameter, and neither
        // nonce grants access on its own - the capability check below is what
        // authorises the write.
        $nonce_valid = wp_verify_nonce( $nonce, 'wpe_resize_' . $attachment_id )
            || wp_verify_nonce( $nonce, 'wpe_bulk_resize' );

        if ( ! $nonce_valid ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload the page.', 'ecommerce-wunderkiste' ) ), 403 );
        }

        if ( ! $this->user_can_resize( $attachment_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this image.', 'ecommerce-wunderkiste' ) ), 403 );
        }

        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Not an image.', 'ecommerce-wunderkiste' ) ), 400 );
        }

        $path = get_attached_file( $attachment_id );

        if ( ! $path || ! file_exists( $path ) ) {
            wp_send_json_error( array( 'message' => __( 'File not found.', 'ecommerce-wunderkiste' ) ), 404 );
        }

        $editor = wp_get_image_editor( $path );

        if ( is_wp_error( $editor ) ) {
            wp_send_json_error( array( 'message' => $editor->get_error_message() ), 500 );
        }

        /**
         * Filter the JPEG quality used when the resizer rewrites an image.
         *
         * @param int $quality       Quality between 1 and 100.
         * @param int $attachment_id Attachment ID.
         */
        $editor->set_quality( (int) apply_filters( 'wpe_image_resizer_quality', 92, $attachment_id ) );

        $size = $editor->get_size();

        if ( $size['width'] <= $target_size && $size['height'] <= $target_size ) {
            wp_send_json_error(
                array(
                    'message' => sprintf(
                        /* translators: %d: target size in pixels */
                        __( 'Already %dpx or smaller.', 'ecommerce-wunderkiste' ),
                        $target_size
                    ),
                ),
                400
            );
        }

        /**
         * Filter whether a copy of the original file is kept before overwriting.
         *
         * @param bool $keep_backup   Defaults to false.
         * @param int  $attachment_id Attachment ID.
         */
        if ( apply_filters( 'wpe_image_resizer_keep_backup', false, $attachment_id ) ) {
            $backup = $path . '.wpe-backup';

            if ( ! file_exists( $backup ) ) {
                @copy( $path, $backup ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            }
        }

        $resized = $editor->resize( $target_size, $target_size, false );

        if ( is_wp_error( $resized ) ) {
            wp_send_json_error( array( 'message' => $resized->get_error_message() ), 500 );
        }

        $saved = $editor->save( $path );

        if ( is_wp_error( $saved ) ) {
            wp_send_json_error( array( 'message' => $saved->get_error_message() ), 500 );
        }

        $metadata = wp_generate_attachment_metadata( $attachment_id, $path );

        if ( ! is_wp_error( $metadata ) && ! empty( $metadata ) ) {
            wp_update_attachment_metadata( $attachment_id, $metadata );
        }

        $new_size = $editor->get_size();

        wp_send_json_success(
            array(
                'dimensions' => $new_size['width'] . ' x ' . $new_size['height'] . ' px',
                'width'      => (int) $new_size['width'],
                'height'     => (int) $new_size['height'],
            )
        );
    }

    /**
     * Register the media list column.
     *
     * @param array $columns Existing columns.
     * @return array
     */
    public function add_list_column( $columns ) {
        if ( current_user_can( 'upload_files' ) ) {
            $columns['wpe_resizer'] = __( 'Resizer', 'ecommerce-wunderkiste' );
        }

        return $columns;
    }

    /**
     * Render the media list column.
     *
     * @param string $column_name Column key.
     * @param int    $post_id     Attachment ID.
     */
    public function fill_list_column( $column_name, $post_id ) {
        if ( 'wpe_resizer' !== $column_name ) {
            return;
        }

        if ( ! wp_attachment_is_image( $post_id ) || ! $this->user_can_resize( $post_id ) ) {
            echo '<span style="color:#999;">&mdash;</span>';
            return;
        }

        $nonce    = wp_create_nonce( 'wpe_resize_' . $post_id );
        $metadata = wp_get_attachment_metadata( $post_id );

        if ( is_array( $metadata ) && isset( $metadata['width'], $metadata['height'] ) ) {
            printf(
                '<span class="wpe-current-size" style="font-size:11px;color:#666;display:block;margin-bottom:4px;">%s</span>',
                esc_html( $metadata['width'] . 'x' . $metadata['height'] )
            );
        }

        echo '<div class="wpe-resize-container" style="display:flex;gap:4px;flex-wrap:wrap;">';

        foreach ( $this->allowed_sizes as $size ) {
            printf(
                '<button type="button" class="button button-small wpe-resize-trigger" data-id="%1$s" data-size="%2$s" data-nonce="%3$s">%2$s</button>',
                esc_attr( $post_id ),
                esc_attr( $size ),
                esc_attr( $nonce )
            );
        }

        echo '</div><span class="wpe-resize-status" style="display:block;font-size:11px;margin-top:2px;"></span>';
    }
}
