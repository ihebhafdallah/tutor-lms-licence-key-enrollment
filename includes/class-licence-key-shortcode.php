<?php

class Tutor_Licence_Key_Shortcode
{

    public function __construct()
    {
        add_shortcode('tutor_licence_key', [$this, 'render_form']);
        add_action('init', [$this, 'handle_submission']);
    }

    public function render_form()
    {

        if (!is_user_logged_in()) {
            return '<p>' . __('Please login to use a licence key.', 'tutor-lms-licence-key-enrollment') . '</p>';
        }

        ob_start();
?>
        <form method="post" class="tutor-licence-key-form">

            <?php wp_nonce_field('tutor_licence_key_submit', 'tutor_licence_key_nonce'); ?>

            <input
                type="text"
                name="licence_key"
                class="tutor-form-control"
                placeholder="<?php echo esc_attr__('XXXX-XXXX-XXXX-XXXX', 'tutor-lms-licence-key-enrollment'); ?>"
                required />

            <button type="submit" name="submit_licence_key" class="tutor-btn tutor-btn-primary tutor-btn-block tutor-mt-24">
                <?php _e('Activate Licence', 'tutor-lms-licence-key-enrollment'); ?>
            </button>

        </form>

<?php
        if (!empty($_GET['licence_msg'])) {
            echo '<p class="licence-message">' . esc_html($_GET['licence_msg']) . '</p>';
        }

        return ob_get_clean();
    }

    public function handle_submission()
    {

        if (
            empty($_POST['licence_key']) ||
            empty($_POST['tutor_licence_key_nonce']) ||
            !wp_verify_nonce($_POST['tutor_licence_key_nonce'], 'tutor_licence_key_submit')
        ) {
            return;
        }

        if (!is_user_logged_in()) {
            return;
        }

        global $wpdb;

        $user_id     = get_current_user_id();
        $licence_key = sanitize_text_field($_POST['licence_key']);
        $table       = $wpdb->prefix . 'tutor_licence_keys';

        $licence = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE licence_key = %s AND status = 'active' OR 'sent'",
                $licence_key
            ),
            ARRAY_A
        );

        if (!$licence) {
            $this->redirect_msg(__('Invalid or expired licence key.', 'tutor-lms-licence-key-enrollment'));
        }

        $course_id = (int) $licence['course_id'];

        $product_id = tutor_utils()->get_course_product_id($course_id);

        if (!$product_id || !wc_get_product($product_id)) {
            $this->redirect_msg(__('Course product not found.', 'tutor-lms-licence-key-enrollment'));
        }

        $product = wc_get_product($product_id);

        $order = wc_create_order([
            'customer_id' => $user_id,
        ]);

        $order->add_product($product, 1);

        $order->calculate_totals();

        $order->update_status(
            'completed',
            __('Licence key enrollment', 'tutor-lms-licence-key-enrollment')
        );

        $wpdb->update(
            $table,
            ['status' => 'expired'],
            ['id' => (int) $licence['id']],
            ['%s'],
            ['%d']
        );

        $order->update_meta_data('_licence_key_used', $licence_key);
        $order->update_meta_data('_tutor_course_id', $course_id);
        $order->save();

        //$this->redirect_msg(__('Course activated successfully!', 'tutor-lms-licence-key-enrollment'));

        $course_url = get_permalink($course_id);

        wp_safe_redirect($course_url);
        exit;
    }

    private function redirect_msg($message)
    {
        wp_redirect(
            add_query_arg(
                'licence_msg',
                urlencode($message),
                wp_get_referer()
            )
        );
        exit;
    }
}
