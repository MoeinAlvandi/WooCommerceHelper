<?php
/**
 * Plugin Name: WooCommerce Helper
 * Description: This plugin helps us import attribute descriptions and short descriptions using AI.
 * Version: 0.4.4
 * Author: Moein Alvandi
 * Author URI: https://moeinalvandi.com/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WooCamers_Helper {
    const OPTION_NAME = 'wh_settings';
    const VERSION     = '0.4.4';

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );

        add_action( 'woocommerce_single_product_summary', array( $this, 'render_buttons' ), 35 );

        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_post_wh_save_settings', array( $this, 'handle_settings_save' ) );
        add_action( 'woocommerce_product_options_attributes', array( $this, 'render_product_attributes_button' ) );
        add_filter( 'manage_edit-product_columns', array( $this, 'add_product_list_column' ) );
        add_action( 'manage_product_posts_custom_column', array( $this, 'render_product_list_column' ), 10, 2 );
        add_filter( 'post_row_actions', array( $this, 'add_product_row_action' ), 10, 2 );

        add_action( 'wp_ajax_wh_generate_attributes', array( $this, 'ajax_generate_attributes' ) );
        add_action( 'wp_ajax_nopriv_wh_generate_attributes', array( $this, 'ajax_generate_attributes' ) );

        add_action( 'wp_ajax_wh_add_attributes', array( $this, 'ajax_add_attributes' ) );
        add_action( 'wp_ajax_nopriv_wh_add_attributes', array( $this, 'ajax_add_attributes' ) );

        add_action( 'wp_ajax_wh_generate_short_description', array( $this, 'ajax_generate_short_description' ) );
        add_action( 'wp_ajax_nopriv_wh_generate_short_description', array( $this, 'ajax_generate_short_description' ) );

        add_action( 'wp_ajax_wh_generate_product_description', array( $this, 'ajax_generate_product_description' ) );
        add_action( 'wp_ajax_nopriv_wh_generate_product_description', array( $this, 'ajax_generate_product_description' ) );

        add_action( 'wp_ajax_wh_fetch_sample_product', array( $this, 'ajax_fetch_sample_product' ) );
    }

    private function get_default_prompt_template() {
        return "شما یک متخصص تولید ویژگی برای محصولات ووکامرس هستی.\n"
            . "فقط یک JSON معتبر برگردان که ساختار آن شامل کلید `attributes` باشد.\n"
            . "`attributes` باید یک آرایه از آیتم‌ها باشد و هر آیتم فقط دو کلید `name` و `value` داشته باشد.\n"
            . "هیچ متن اضافه، توضیح، یا Markdown برنگردان.\n\n"
            . "اطلاعات برند:\n"
            . "{brand_description}\n\n"
            . "اطلاعات محصول جاری:\n"
            . "عنوان: {title}\n"
            . "خلاصه: {excerpt}\n"
            . "توضیحات: {description}\n"
            . "قیمت: {price}\n"
            . "دسته‌ها: {categories}\n"
            . "SKU: {sku}\n\n"
            . "محصولات نمونه و ویژگی‌هایشان (در صورت وجود):\n"
            . "{sample_products}\n"
            . "اگر محصول جاری از نظر ماهیت و کاربرد شبیه یکی از محصولات نمونه بالا بود، از همان مجموعه نام ویژگی‌ها برای محصول جاری استفاده کن و مقدار مناسب هر ویژگی را متناسب با محصول جاری تولید کن. مقادیر نمونه فقط برای نشان‌دادن قالب هستند و نباید عیناً کپی شوند. اگر هیچ نمونه‌ای شبیه نبود، ویژگی‌ها را آزادانه تولید کن.\n";
    }

    private function get_default_short_description_prompt_template() {
        return "شما یک کپی‌نویس فروشگاهی برای ووکامرس هستی.\n"
            . "فقط یک متن کوتاه، طبیعی و روان برای توضیح مختصر محصول بنویس.\n"
            . "متن نهایی باید 2 تا 4 جمله باشد، بیش از حد تبلیغاتی نباشد و برای خریدار مفید باشد.\n"
            . "از اطلاعات زیر استفاده کن و اگر ویژگی‌های محصول مهم هستند، آن‌ها را در متن بگنجان.\n"
            . "فقط متن نهایی را برگردان، بدون Markdown، بدون تیتر و بدون نقل‌قول.\n\n"
            . "اطلاعات برند:\n"
            . "{brand_description}\n\n"
            . "اطلاعات محصول جاری:\n"
            . "عنوان: {title}\n"
            . "خلاصه فعلی: {current_short_description}\n"
            . "توضیحات: {description}\n"
            . "قیمت: {price}\n"
            . "دسته‌ها: {categories}\n"
            . "برچسب‌ها: {tags}\n"
            . "ویژگی‌ها: {attributes}\n"
            . "SKU: {sku}\n";
    }

    private function get_default_product_description_prompt_template() {
        return "شما یک کپی‌نویس ووکامرس هستی.\n"
            . "فقط یک توضیح محصول تقویت‌شده، روان و قانع‌کننده بنویس.\n"
            . "متن باید برای اقدام به خرید مفید باشد و مستقیماً با ویژگی‌ها و مشخصات محصول پیوند بخورد.\n"
            . "اگر اطلاعات ویژگی یا توضیحات محصول کامل نیست، بر اساس داده‌های موجود یک متن واقعی و مفید تولید کن.\n"
            . "فقط متن نهایی را برگردان، بدون Markdown، بدون عنوان و بدون نقل‌قول.\n\n"
            . "اطلاعات برند:\n"
            . "{brand_description}\n\n"
            . "اطلاعات محصول جاری:\n"
            . "عنوان: {title}\n"
            . "توضیح اختصاری الان: {current_short_description}\n"
            . "توضیح محصول الان: {current_description}\n"
            . "توضیحات: {description}\n"
            . "قیمت: {price}\n"
            . "دسته‌ها: {categories}\n"
            . "برچسب‌ها: {tags}\n"
            . "ویژگی‌ها: {attributes}\n"
            . "SKU: {sku}\n";
    }

    private function get_default_settings() {
        return array(
            'connection_mode'   => 'mock',
            'api_endpoint'      => '',
            'api_key'           => '',
            'usd_to_toman_rate' => '0',
            'openai_model'      => 'gpt-4o',
            'openai_reasoning'  => 'low',
            'avalai_model'      => 'gpt-4o-mini',
            'brand_description' => '',
            'sample_products'   => array(),
            'prompt_template'   => $this->get_default_prompt_template(),
            'product_description_prompt_template' => $this->get_default_product_description_prompt_template(),
            'short_description_prompt_template' => $this->get_default_short_description_prompt_template(),
        );
    }

    private function normalize_loaded_settings( $settings ) {
        $settings = is_array( $settings ) ? $settings : array();
        $settings = wp_parse_args( $settings, $this->get_default_settings() );

        if ( in_array( $settings['connection_mode'], array( 'openai', 'chat' ), true ) ) {
            $settings['connection_mode'] = 'gapgpt';
        }

        if ( ! in_array( $settings['connection_mode'], array( 'mock', 'endpoint', 'gapgpt', 'avalai' ), true ) ) {
            $settings['connection_mode'] = 'mock';
        }

        return $settings;
    }

    private function get_settings() {
        return $this->normalize_loaded_settings( get_option( self::OPTION_NAME, array() ) );
    }

    private function sanitize_settings( $input ) {
        $defaults = $this->get_default_settings();
        $input    = is_array( $input ) ? $input : array();

        $mode = isset( $input['connection_mode'] ) ? sanitize_key( $input['connection_mode'] ) : $defaults['connection_mode'];
        if ( in_array( $mode, array( 'openai', 'chat' ), true ) ) {
            $mode = 'gapgpt';
        }
        $mode = in_array( $mode, array( 'mock', 'endpoint', 'gapgpt', 'avalai' ), true ) ? $mode : $defaults['connection_mode'];

        return array(
            'connection_mode'   => $mode,
            'api_endpoint'      => isset( $input['api_endpoint'] ) ? esc_url_raw( trim( $input['api_endpoint'] ) ) : '',
            'api_key'           => isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '',
            'usd_to_toman_rate' => isset( $input['usd_to_toman_rate'] ) ? preg_replace( '/[^0-9.]/', '', (string) $input['usd_to_toman_rate'] ) : '0',
            'openai_model'      => isset( $input['openai_model'] ) ? sanitize_text_field( $input['openai_model'] ) : $defaults['openai_model'],
            'openai_reasoning'  => isset( $input['openai_reasoning'] ) ? sanitize_key( $input['openai_reasoning'] ) : $defaults['openai_reasoning'],
            'avalai_model'      => isset( $input['avalai_model'] ) ? sanitize_text_field( $input['avalai_model'] ) : $defaults['avalai_model'],
            'brand_description' => isset( $input['brand_description'] ) ? sanitize_textarea_field( $input['brand_description'] ) : '',
            'sample_products'   => isset( $input['sample_products'] ) ? $this->sanitize_sample_products( $input['sample_products'] ) : array(),
            'prompt_template'   => isset( $input['prompt_template'] ) ? sanitize_textarea_field( $input['prompt_template'] ) : $defaults['prompt_template'],
            'product_description_prompt_template' => isset( $input['product_description_prompt_template'] ) ? sanitize_textarea_field( $input['product_description_prompt_template'] ) : $defaults['product_description_prompt_template'],
            'short_description_prompt_template' => isset( $input['short_description_prompt_template'] ) ? sanitize_textarea_field( $input['short_description_prompt_template'] ) : $defaults['short_description_prompt_template'],
        );
    }

    private function sanitize_sample_products( $input ) {
        if ( ! is_array( $input ) ) {
            return array();
        }

        $clean = array();

        foreach ( $input as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $url   = isset( $row['url'] ) ? esc_url_raw( trim( (string) $row['url'] ) ) : '';
            $title = isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : '';

            $attributes = array();
            if ( isset( $row['attributes'] ) ) {
                $raw = $row['attributes'];

                // The UI stores fetched attributes as a JSON string in a hidden field.
                if ( is_string( $raw ) ) {
                    $decoded = json_decode( $raw, true );
                    $raw     = ( JSON_ERROR_NONE === json_last_error() ) ? $decoded : array();
                }

                $attributes = $this->normalize_attribute_items( $raw );
            }

            // Skip empty rows (no url and no attributes).
            if ( '' === $url && empty( $attributes ) ) {
                continue;
            }

            $clean[] = array(
                'url'        => $url,
                'title'      => $title,
                'attributes' => $attributes,
            );
        }

        return $clean;
    }

    private function build_sample_products_text( $samples ) {
        if ( ! is_array( $samples ) || empty( $samples ) ) {
            return '';
        }

        $blocks = array();
        $index  = 0;

        foreach ( $samples as $sample ) {
            if ( ! is_array( $sample ) || empty( $sample['attributes'] ) || ! is_array( $sample['attributes'] ) ) {
                continue;
            }

            $index++;
            $title = ! empty( $sample['title'] ) ? $sample['title'] : ( ! empty( $sample['url'] ) ? $sample['url'] : ( 'محصول نمونه ' . $index ) );

            $lines = array( 'محصول نمونه ' . $index . ': ' . wp_strip_all_tags( (string) $title ) );

            foreach ( $sample['attributes'] as $attr ) {
                if ( ! is_array( $attr ) || empty( $attr['name'] ) ) {
                    continue;
                }
                $name  = wp_strip_all_tags( (string) $attr['name'] );
                $value = isset( $attr['value'] ) ? wp_strip_all_tags( (string) $attr['value'] ) : '';
                $lines[] = ( '' !== $value ) ? ( '- ' . $name . ': ' . $value ) : ( '- ' . $name );
            }

            if ( count( $lines ) > 1 ) {
                $blocks[] = implode( "\n", $lines );
            }
        }

        return implode( "\n\n", $blocks );
    }

    /**
     * Resolve a product URL into a title + list of attributes.
     *
     * First tries to map the URL to a local WooCommerce product (most reliable),
     * otherwise fetches the page HTML and parses the WooCommerce attributes table.
     *
     * @param string $url
     * @return array|WP_Error array( 'title' => string, 'attributes' => array )
     */
    private function fetch_sample_product_attributes( $url ) {
        $url = trim( (string) $url );
        if ( '' === $url || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return new WP_Error( 'wh_invalid_url', 'آدرس محصول معتبر نیست.' );
        }

        // 1) Try to resolve to a local product first.
        $local = $this->extract_attributes_from_local_url( $url );
        if ( ! is_wp_error( $local ) && ! empty( $local['attributes'] ) ) {
            return $local;
        }

        // 2) Fall back to fetching and scraping the page HTML.
        $remote = $this->extract_attributes_from_remote_url( $url );
        if ( ! is_wp_error( $remote ) ) {
            return $remote;
        }

        // If local resolved a product but it simply had no attributes, surface that.
        if ( ! is_wp_error( $local ) ) {
            return $local;
        }

        return $remote;
    }

    private function extract_attributes_from_local_url( $url ) {
        if ( ! function_exists( 'url_to_postid' ) ) {
            return new WP_Error( 'wh_no_local', 'تابع url_to_postid در دسترس نیست.' );
        }

        $post_id = url_to_postid( $url );
        if ( ! $post_id || 'product' !== get_post_type( $post_id ) ) {
            return new WP_Error( 'wh_not_local_product', 'محصول داخلی برای این آدرس پیدا نشد.' );
        }

        $product = wc_get_product( $post_id );
        if ( ! $product ) {
            return new WP_Error( 'wh_local_missing', 'محصول داخلی قابل بارگذاری نیست.' );
        }

        $attributes = array();

        foreach ( $product->get_attributes() as $attribute ) {
            if ( ! $attribute instanceof WC_Product_Attribute ) {
                continue;
            }

            $name = $attribute->get_name();

            if ( $attribute->is_taxonomy() ) {
                $values = wc_get_product_terms( $post_id, $name, array( 'fields' => 'names' ) );
            } else {
                $values = array_map( 'strval', (array) $attribute->get_options() );
            }

            $label = wc_attribute_label( $name );
            $value = implode( ', ', array_filter( array_map( 'sanitize_text_field', (array) $values ) ) );

            if ( '' !== trim( (string) $label ) ) {
                $attributes[] = array(
                    'name'  => sanitize_text_field( $label ),
                    'value' => $value,
                );
            }
        }

        return array(
            'title'      => $product->get_name(),
            'attributes' => $attributes,
        );
    }

    private function extract_attributes_from_remote_url( $url ) {
        $response = wp_remote_get(
            $url,
            array(
                'timeout'     => 20,
                'redirection' => 5,
                'user-agent'  => 'WooCamersHelper/' . self::VERSION . '; ' . home_url( '/' ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'wh_fetch_failed', 'دریافت صفحه محصول ناموفق بود: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error( 'wh_fetch_status', 'صفحه محصول با کد ' . $code . ' پاسخ داد.' );
        }

        $html = wp_remote_retrieve_body( $response );
        if ( '' === trim( (string) $html ) ) {
            return new WP_Error( 'wh_fetch_empty', 'محتوای صفحه محصول خالی است.' );
        }

        $parsed = $this->parse_attributes_from_html( $html );
        if ( empty( $parsed['attributes'] ) ) {
            return new WP_Error( 'wh_no_attributes', 'هیچ ویژگی‌ای در صفحه محصول پیدا نشد. مطمئن شوید آدرس صفحه‌ی یک محصول ووکامرس با جدول ویژگی‌ها است.' );
        }

        return $parsed;
    }

    private function parse_attributes_from_html( $html ) {
        $result = array(
            'title'      => '',
            'attributes' => array(),
        );

        if ( ! class_exists( 'DOMDocument' ) ) {
            return $result;
        }

        $previous = libxml_use_internal_errors( true );
        $doc      = new DOMDocument();
        // Force UTF-8 handling for non-Latin (e.g. Persian) content.
        $doc->loadHTML( '<?xml encoding="UTF-8">' . $html );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );

        $xpath = new DOMXPath( $doc );

        // Product title (WooCommerce single product).
        $title_nodes = $xpath->query( "//h1[contains(concat(' ', normalize-space(@class), ' '), ' product_title ')]" );
        if ( $title_nodes && $title_nodes->length ) {
            $result['title'] = sanitize_text_field( trim( $title_nodes->item( 0 )->textContent ) );
        }

        // WooCommerce additional-information / attributes table.
        $rows = $xpath->query( "//table[contains(concat(' ', normalize-space(@class), ' '), ' shop_attributes ') or contains(concat(' ', normalize-space(@class), ' '), ' woocommerce-product-attributes ')]//tr" );

        if ( $rows && $rows->length ) {
            foreach ( $rows as $row ) {
                $label_nodes = $xpath->query( ".//th", $row );
                $value_nodes = $xpath->query( ".//td", $row );

                if ( ! $label_nodes->length || ! $value_nodes->length ) {
                    continue;
                }

                $name  = trim( preg_replace( '/\s+/u', ' ', $label_nodes->item( 0 )->textContent ) );
                $value = trim( preg_replace( '/\s+/u', ' ', $value_nodes->item( 0 )->textContent ) );

                $name  = sanitize_text_field( $name );
                $value = sanitize_text_field( $value );

                if ( '' !== $name ) {
                    $result['attributes'][] = array(
                        'name'  => $name,
                        'value' => $value,
                    );
                }
            }
        }

        return $result;
    }

    public function ajax_fetch_sample_product() {
        check_ajax_referer( 'wh_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'دسترسی مجاز نیست.' );
        }

        $url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
        if ( '' === $url ) {
            wp_send_json_error( 'آدرس محصول را وارد کنید.' );
        }

        $result = $this->fetch_sample_product_attributes( $url );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success(
            array(
                'title'      => isset( $result['title'] ) ? $result['title'] : '',
                'attributes' => isset( $result['attributes'] ) ? $result['attributes'] : array(),
            )
        );
    }

    private function should_load_admin_assets() {
        if ( ! function_exists( 'get_current_screen' ) ) {
            return false;
        }

        $screen = get_current_screen();
        if ( ! $screen ) {
            return false;
        }

        return ( 'edit' === $screen->base && 'product' === $screen->post_type )
            || ( 'post' === $screen->base && 'product' === $screen->post_type )
            || 'woocommerce_page_wh-settings' === $screen->id;
    }

    public function enqueue_scripts() {
        if ( function_exists( 'is_product' ) && is_product() ) {
            wp_enqueue_style( 'wh-style', plugin_dir_url( __FILE__ ) . 'assets/css/style.css', array(), self::VERSION );
            wp_enqueue_script( 'wh-front', plugin_dir_url( __FILE__ ) . 'assets/js/front.js', array( 'jquery' ), self::VERSION, true );
            $settings = $this->get_settings();

            global $product;
            $product_id = is_object( $product ) ? $product->get_id() : get_the_ID();

            wp_localize_script(
                'wh-front',
                'whData',
                array(
                    'ajax_url'      => admin_url( 'admin-ajax.php' ),
                    'nonce'         => wp_create_nonce( 'wh_nonce' ),
                    'product_id'    => $product_id,
                    'generate_text' => 'تولید ویژگی‌ها',
                    'loading_text'  => 'در حال تولید...',
                    'add_text'      => 'افزودن ویژگی‌ها',
                    'cost_label'    => 'هزینه تقریبی',
                    'usd_to_toman_rate' => (float) $settings['usd_to_toman_rate'],
                    'cancel_text'   => 'انصراف',
                    'empty_message' => 'هیچ ویژگی‌ای انتخاب نشده است.',
                    'success_text'  => 'ویژگی‌ها اضافه شدند.',
                    'error_text'    => 'خطا',
                )
            );
        }
    }

    public function enqueue_admin() {
        if ( ! $this->should_load_admin_assets() ) {
            return;
        }

        wp_enqueue_style( 'wh-style', plugin_dir_url( __FILE__ ) . 'assets/css/style.css', array(), self::VERSION );
        wp_enqueue_script( 'wh-front', plugin_dir_url( __FILE__ ) . 'assets/js/front.js', array( 'jquery' ), self::VERSION, true );
        $settings = $this->get_settings();

        wp_localize_script(
            'wh-front',
            'whData',
            array(
                'ajax_url'      => admin_url( 'admin-ajax.php' ),
                'nonce'         => wp_create_nonce( 'wh_nonce' ),
                'generate_text' => 'افزودن ویژگی',
                'loading_text'  => 'در حال تولید...',
                'add_text'      => 'افزودن ویژگی‌ها',
                'short_desc_text' => 'تولید متن با هوش مصنوعی',
                'short_desc_loading_text' => 'در حال تولید متن...',
                'product_desc_text' => 'تولید متن با هوش مصنوعی',
                'product_desc_loading_text' => 'در حال تولید متن...',
                'cost_label'    => 'هزینه تقریبی',
                'usd_to_toman_rate' => (float) $settings['usd_to_toman_rate'],
                'cancel_text'   => 'انصراف',
                'empty_message' => 'هیچ ویژگی‌ای انتخاب نشده است.',
                'success_text'  => 'ویژگی‌ها اضافه شدند.',
                'error_text'    => 'خطا',
                'sample_fetch_loading' => 'در حال خواندن...',
                'sample_fetch_text'    => 'خواندن ویژگی‌ها',
                'sample_url_required'  => 'ابتدا آدرس محصول را وارد کنید.',
                'sample_no_attrs'      => 'هیچ ویژگی‌ای پیدا نشد.',
                'sample_attrs_label'   => 'ویژگی‌های خوانده‌شده',
            )
        );
    }

    public function render_buttons() {
        echo '<div class="wh-buttons">';
        echo '<button id="wh-generate-btn" class="button button-primary">تولید ویژگی‌ها</button>';
        echo '</div>';
    }

    public function render_product_attributes_button() {
        global $post;
        $product_id = ( isset( $post->ID ) && 'product' === $post->post_type ) ? absint( $post->ID ) : 0;
        ?>
        <div class="wh-product-attributes-cta">
            <button
                type="button"
                class="button button-primary wh-add-attributes-action"
                data-product-id="<?php echo esc_attr( $product_id ); ?>"
                <?php disabled( ! $product_id ); ?>
            >
                افزودن ویژگی
            </button>
            <?php if ( ! $product_id ) : ?>
                <span class="description">ابتدا محصول را ذخیره کنید.</span>
            <?php endif; ?>
        </div>
        <?php
    }

    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            'WooCommerce Helper',
            'WooCommerce Helper',
            'manage_woocommerce',
            'wh-settings',
            array( $this, 'settings_page' )
        );
    }

    public function settings_page() {
        $options = $this->get_settings();
        $saved   = isset( $_GET['wh_saved'] ) ? sanitize_text_field( wp_unslash( $_GET['wh_saved'] ) ) : '';
        ?>
        <div class="wrap">
            <h1>WooCommerce Helper</h1>
            <?php if ( '1' === $saved ) : ?>
                <div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div>
            <?php endif; ?>
            <div class="notice notice-info inline">
                <p>هزینه هر درخواست بر اساس توکن‌های ورودی و خروجی محاسبه می‌شود. برای مدل‌های شناخته‌شده، افزونه بعد از هر اجرا هزینه تقریبی را نمایش می‌دهد.</p>
            </div>
            <p>اینجا حالت اتصال، کلید OpenAI، مدل، و قالب پرامپت را تنظیم می‌کنید. داده‌های محصول جاری به‌صورت خودکار داخل پرامپت جایگزین می‌شوند.</p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="wh_save_settings" />
                <?php wp_nonce_field( 'wh_save_settings', 'wh_save_settings_nonce' ); ?>

                <table class="form-table" role="presentation">
                    <tr valign="top">
                        <th scope="row"><label for="wh_connection_mode">حالت اتصال</label></th>
                        <td>
                            <select id="wh_connection_mode" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[connection_mode]">
                                <option value="mock" <?php selected( $options['connection_mode'], 'mock' ); ?>>آزمایشی / محلی</option>
                                <option value="endpoint" <?php selected( $options['connection_mode'], 'endpoint' ); ?>>API سفارشی</option>
                                <option value="gapgpt" <?php selected( $options['connection_mode'], 'gapgpt' ); ?>>Gap GPT / OpenAI Chat Completions</option>
                                <option value="avalai" <?php selected( $options['connection_mode'], 'avalai' ); ?>>AvalAI</option>
                            </select>
                            <p class="description">در حالت Gap GPT، اگر Endpoint خالی باشد، آدرس پیش‌فرض <code>https://api.openai.com/v1/chat/completions</code> استفاده می‌شود. در حالت AvalAI از آدرس <code>https://api.avalai.ir/v1/chat/completions</code> استفاده می‌شود.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="wh_api_endpoint">API Endpoint</label></th>
                        <td>
                            <input
                                type="url"
                                id="wh_api_endpoint"
                                name="<?php echo esc_attr( self::OPTION_NAME ); ?>[api_endpoint]"
                                value="<?php echo esc_attr( $options['api_endpoint'] ); ?>"
                                class="regular-text"
                                placeholder="https://api.example.com/generate"
                            />
                            <p class="description">برای API سفارشی از این آدرس استفاده می‌شود. اگر Gap GPT را انتخاب کرده‌اید، این فیلد اختیاری است.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="wh_api_key">API Key</label></th>
                        <td>
                            <input
                                type="password"
                                id="wh_api_key"
                                name="<?php echo esc_attr( self::OPTION_NAME ); ?>[api_key]"
                                value="<?php echo esc_attr( $options['api_key'] ); ?>"
                                class="regular-text"
                                autocomplete="off"
                            />
                            <p class="description">برای Gap GPT کلید OpenAI و برای AvalAI کلید avalai.ir را وارد کنید.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="wh_usd_to_toman_rate">نرخ دلار به تومان</label></th>
                        <td>
                            <input
                                type="number"
                                id="wh_usd_to_toman_rate"
                                name="<?php echo esc_attr( self::OPTION_NAME ); ?>[usd_to_toman_rate]"
                                value="<?php echo esc_attr( $options['usd_to_toman_rate'] ); ?>"
                                class="regular-text"
                                min="0"
                                step="1"
                            />
                            <p class="description">این عدد برای تبدیل هزینه API از دلار به تومان استفاده می‌شود. اگر وارد نشود، فقط هزینه دلاری نمایش داده می‌شود.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="wh_openai_model">مدل OpenAI</label></th>
                        <td>
                            <input
                                type="text"
                                id="wh_openai_model"
                                name="<?php echo esc_attr( self::OPTION_NAME ); ?>[openai_model]"
                                value="<?php echo esc_attr( $options['openai_model'] ); ?>"
                                class="regular-text"
                                placeholder="gpt-4o"
                            />
                            <p class="description">پیشنهاد پیش‌فرض <code>gpt-4o</code> است. اگر هزینه کمتر می‌خواهید، مدل کوچک‌تر وارد کنید.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="wh_avalai_model">مدل AvalAI</label></th>
                        <td>
                            <input
                                type="text"
                                id="wh_avalai_model"
                                name="<?php echo esc_attr( self::OPTION_NAME ); ?>[avalai_model]"
                                value="<?php echo esc_attr( $options['avalai_model'] ); ?>"
                                class="regular-text"
                                placeholder="gpt-4o-mini"
                            />
                            <p class="description">نام مدل برای AvalAI. مثال: <code>gpt-4o-mini</code>، <code>claude-sonnet-4-6</code>، <code>gemini-2.0-flash</code>.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="wh_openai_reasoning">Reasoning effort</label></th>
                        <td>
                            <select id="wh_openai_reasoning" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[openai_reasoning]">
                                <option value="none" <?php selected( $options['openai_reasoning'], 'none' ); ?>>none</option>
                                <option value="low" <?php selected( $options['openai_reasoning'], 'low' ); ?>>low</option>
                                <option value="medium" <?php selected( $options['openai_reasoning'], 'medium' ); ?>>medium</option>
                                <option value="high" <?php selected( $options['openai_reasoning'], 'high' ); ?>>high</option>
                            </select>
                            <p class="description">برای تولید ویژگی محصول، معمولاً <code>low</code> کافی است.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="wh_brand_description">توضیح برند</label></th>
                        <td>
                            <textarea
                                id="wh_brand_description"
                                name="<?php echo esc_attr( self::OPTION_NAME ); ?>[brand_description]"
                                rows="5"
                                class="large-text"
                                placeholder="مثال: ما یک فروشگاه تخصصی لوازم خانگی هستیم که محصولات باکیفیت اروپایی را با قیمت مناسب ارائه می‌دهیم..."
                            ><?php echo esc_textarea( $options['brand_description'] ); ?></textarea>
                            <p class="description">
                                برند یا فروشگاه خود را معرفی کنید: چه می‌فروشید، چه مخاطبی دارید، و لحن یا سبک محتوای مطلوب شما چیست.
                                این اطلاعات در تمام درخواست‌های تولید متن (توضیح مختصر، توضیح کامل، ویژگی‌ها) به API ارسال می‌شود.
                                همچنین می‌توانید از <code>{brand_description}</code> در پرامپت‌های زیر استفاده کنید.
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">محصولات نمونه</th>
                        <td>
                            <p class="description" style="margin-bottom:10px;">
                                لینک یک یا چند محصول نمونه را وارد کنید و دکمه «خواندن ویژگی‌ها» را بزنید تا ویژگی‌های آن‌ها به‌صورت خودکار خوانده و ذخیره شود.
                                هنگام تولید ویژگی برای هر محصول، هوش مصنوعی بررسی می‌کند که آیا محصول شبیه یکی از این نمونه‌هاست و در صورت شباهت از همان ویژگی‌ها استفاده می‌کند.
                                برای محصولات همین سایت، ویژگی‌ها مستقیماً از دیتابیس خوانده می‌شوند؛ در غیر این صورت صفحه محصول دریافت و جدول ویژگی‌ها استخراج می‌شود.
                            </p>

                            <div id="wh-sample-products">
                                <?php
                                $samples = ( isset( $options['sample_products'] ) && is_array( $options['sample_products'] ) ) ? $options['sample_products'] : array();
                                $sample_index = 0;
                                foreach ( $samples as $sample ) :
                                    $s_url   = isset( $sample['url'] ) ? $sample['url'] : '';
                                    $s_title = isset( $sample['title'] ) ? $sample['title'] : '';
                                    $s_attrs = ( isset( $sample['attributes'] ) && is_array( $sample['attributes'] ) ) ? $sample['attributes'] : array();
                                    $s_attrs_json = wp_json_encode( $s_attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
                                    ?>
                                    <div class="wh-sample-product" data-index="<?php echo esc_attr( $sample_index ); ?>">
                                        <div class="wh-sample-product-head">
                                            <input
                                                type="url"
                                                class="wh-sample-url regular-text"
                                                name="<?php echo esc_attr( self::OPTION_NAME ); ?>[sample_products][<?php echo esc_attr( $sample_index ); ?>][url]"
                                                value="<?php echo esc_attr( $s_url ); ?>"
                                                placeholder="https://example.com/product/..."
                                            />
                                            <input type="hidden" class="wh-sample-title" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[sample_products][<?php echo esc_attr( $sample_index ); ?>][title]" value="<?php echo esc_attr( $s_title ); ?>" />
                                            <input type="hidden" class="wh-sample-attributes" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[sample_products][<?php echo esc_attr( $sample_index ); ?>][attributes]" value="<?php echo esc_attr( $s_attrs_json ); ?>" />
                                            <button type="button" class="button wh-fetch-sample">خواندن ویژگی‌ها</button>
                                            <button type="button" class="button wh-remove-sample" aria-label="حذف">حذف</button>
                                        </div>
                                        <div class="wh-sample-preview"></div>
                                    </div>
                                    <?php
                                    $sample_index++;
                                endforeach;
                                ?>
                            </div>

                            <p style="margin-top:10px;">
                                <button type="button" class="button button-secondary" id="wh-add-sample">افزودن محصول نمونه</button>
                            </p>

                            <script type="text/html" id="wh-sample-row-template">
                                <div class="wh-sample-product" data-index="__INDEX__">
                                    <div class="wh-sample-product-head">
                                        <input type="url" class="wh-sample-url regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[sample_products][__INDEX__][url]" value="" placeholder="https://example.com/product/..." />
                                        <input type="hidden" class="wh-sample-title" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[sample_products][__INDEX__][title]" value="" />
                                        <input type="hidden" class="wh-sample-attributes" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[sample_products][__INDEX__][attributes]" value="" />
                                        <button type="button" class="button wh-fetch-sample">خواندن ویژگی‌ها</button>
                                        <button type="button" class="button wh-remove-sample" aria-label="حذف">حذف</button>
                                    </div>
                                    <div class="wh-sample-preview"></div>
                                </div>
                            </script>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="wh_prompt_template">متن پرامپت</label></th>
                        <td>
                            <textarea
                                id="wh_prompt_template"
                                name="<?php echo esc_attr( self::OPTION_NAME ); ?>[prompt_template]"
                                rows="12"
                                class="large-text code"
                            ><?php echo esc_textarea( $options['prompt_template'] ); ?></textarea>
                            <p class="description">
                                این مقادیر از محصول جاری به‌صورت خودکار جایگزین می‌شوند:
                                <code>{title}</code>
                                <code>{excerpt}</code>
                                <code>{description}</code>
                                <code>{price}</code>
                                <code>{categories}</code>
                                <code>{sku}</code>
                                <code>{tags}</code>
                                <code>{attributes}</code>
                                <code>{current_short_description}</code>
                                <code>{brand_description}</code>
                                <code>{sample_products}</code>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="wh_product_description_prompt_template">پرامپت توضیحات محصول</label></th>
                        <td>
                            <textarea
                                id="wh_product_description_prompt_template"
                                name="<?php echo esc_attr( self::OPTION_NAME ); ?>[product_description_prompt_template]"
                                rows="12"
                                class="large-text code"
                            ><?php echo esc_textarea( $options['product_description_prompt_template'] ); ?></textarea>
                            <p class="description">
                                برای این بخش، افزونه این اطلاعات را هم می‌فرستد:
                                <code>{title}</code>
                                <code>{current_short_description}</code>
                                <code>{current_description}</code>
                                <code>{description}</code>
                                <code>{price}</code>
                                <code>{categories}</code>
                                <code>{tags}</code>
                                <code>{attributes}</code>
                                <code>{sku}</code>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="wh_short_description_prompt_template">پرامپت توضیح مختصر</label></th>
                        <td>
                            <textarea
                                id="wh_short_description_prompt_template"
                                name="<?php echo esc_attr( self::OPTION_NAME ); ?>[short_description_prompt_template]"
                                rows="12"
                                class="large-text code"
                            ><?php echo esc_textarea( $options['short_description_prompt_template'] ); ?></textarea>
                            <p class="description">
                                برای این بخش، افزونه این اطلاعات را هم می‌فرستد:
                                <code>{title}</code>
                                <code>{current_short_description}</code>
                                <code>{description}</code>
                                <code>{price}</code>
                                <code>{categories}</code>
                                <code>{tags}</code>
                                <code>{attributes}</code>
                                <code>{sku}</code>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( 'ذخیره تنظیمات' ); ?>
            </form>
        </div>
        <?php
    }

    public function handle_settings_save() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Unauthorized', 403 );
        }

        check_admin_referer( 'wh_save_settings', 'wh_save_settings_nonce' );

        $incoming = isset( $_POST[ self::OPTION_NAME ] ) ? wp_unslash( $_POST[ self::OPTION_NAME ] ) : array();
        $settings = $this->sanitize_settings( $incoming );

        update_option( self::OPTION_NAME, $settings );

        $redirect = add_query_arg(
            array(
                'page'      => 'wh-settings',
                'wh_saved'  => '1',
            ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    public function add_product_row_action( $actions, $post ) {
        if ( empty( $post ) || 'product' !== $post->post_type ) {
            return $actions;
        }

        $actions['wh_generate_attributes'] = sprintf(
            '<a href="#" class="button button-small wh-add-attributes-action" data-product-id="%d">%s</a>',
            esc_attr( $post->ID ),
            esc_html( 'افزودن ویژگی' )
        );

        return $actions;
    }

    public function add_product_list_column( $columns ) {
        $new_columns = array();

        foreach ( $columns as $key => $label ) {
            $new_columns[ $key ] = $label;

            if ( 'name' === $key ) {
                $new_columns['wh_helper'] = 'Helper';
            }
        }

        if ( ! isset( $new_columns['wh_helper'] ) ) {
            $new_columns['wh_helper'] = 'Helper';
        }

        return $new_columns;
    }

    public function render_product_list_column( $column, $post_id ) {
        if ( 'wh_helper' !== $column ) {
            return;
        }

        echo '<a href="#" class="button button-small wh-add-attributes-action" data-product-id="' . esc_attr( $post_id ) . '">افزودن ویژگی</a>';
    }

    private function build_product_data( $product_id ) {
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return new WP_Error( 'wh_product_missing', 'Product not found' );
        }

        return array(
            'title'       => $product->get_name(),
            'excerpt'     => $product->get_short_description(),
            'description' => $product->get_description(),
            'price'       => $product->get_price(),
            'categories'  => wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) ),
            'sku'         => $product->get_sku(),
        );
    }

    private function format_product_attributes( $product, $product_id ) {
        if ( ! $product ) {
            return '';
        }

        $parts = array();

        foreach ( $product->get_attributes() as $attribute ) {
            if ( ! $attribute instanceof WC_Product_Attribute ) {
                continue;
            }

            $name = $attribute->get_name();
            $values = array();

            if ( $attribute->is_taxonomy() ) {
                $taxonomy = $name;
                $terms = wc_get_product_terms( $product_id, $taxonomy, array( 'fields' => 'names' ) );
                if ( ! empty( $terms ) ) {
                    $values = $terms;
                }
            } else {
                $values = array_map( 'strval', (array) $attribute->get_options() );
            }

            $label = wc_attribute_label( $name );
            $value_string = implode( ', ', array_filter( array_map( 'sanitize_text_field', $values ) ) );

            if ( '' !== trim( $label ) ) {
                $parts[] = $label . ': ' . $value_string;
            }
        }

        return implode( ' | ', array_filter( $parts ) );
    }

    private function get_product_tags_list( $product_id ) {
        $tags = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'names' ) );
        return ! empty( $tags ) ? implode( ', ', array_map( 'sanitize_text_field', $tags ) ) : '';
    }

    private function build_product_context( $product_id ) {
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return new WP_Error( 'wh_product_missing', 'Product not found' );
        }

        $base = $this->build_product_data( $product_id );
        if ( is_wp_error( $base ) ) {
            return $base;
        }

        $base['tags'] = $this->get_product_tags_list( $product_id );
        $base['attributes'] = $this->format_product_attributes( $product, $product_id );
        $base['current_description'] = $product->get_description();
        $base['current_short_description'] = $product->get_short_description();

        return $base;
    }

    private function replace_placeholders( $template, $data, $brand_description = '', $sample_products = '' ) {
        return strtr(
            $template,
            array(
                '{sample_products}'     => wp_strip_all_tags( (string) $sample_products ),
                '{title}'               => isset( $data['title'] ) ? wp_strip_all_tags( (string) $data['title'] ) : '',
                '{excerpt}'             => isset( $data['excerpt'] ) ? wp_strip_all_tags( (string) $data['excerpt'] ) : '',
                '{description}'         => isset( $data['description'] ) ? wp_strip_all_tags( (string) $data['description'] ) : '',
                '{price}'               => isset( $data['price'] ) ? wp_strip_all_tags( (string) $data['price'] ) : '',
                '{categories}'          => isset( $data['categories'] ) ? implode( ', ', array_map( 'sanitize_text_field', (array) $data['categories'] ) ) : '',
                '{sku}'                 => isset( $data['sku'] ) ? wp_strip_all_tags( (string) $data['sku'] ) : '',
                '{tags}'                => isset( $data['tags'] ) ? wp_strip_all_tags( (string) $data['tags'] ) : '',
                '{attributes}'          => isset( $data['attributes'] ) ? wp_strip_all_tags( (string) $data['attributes'] ) : '',
                '{current_description}' => isset( $data['current_description'] ) ? wp_strip_all_tags( (string) $data['current_description'] ) : '',
                '{current_short_description}' => isset( $data['current_short_description'] ) ? wp_strip_all_tags( (string) $data['current_short_description'] ) : '',
                '{brand_description}'   => wp_strip_all_tags( (string) $brand_description ),
            )
        );
    }

    private function normalize_attribute_items( $items ) {
        if ( ! is_array( $items ) ) {
            return array();
        }

        $normalized = array();

        foreach ( $items as $item ) {
            $name  = '';
            $value = '';

            if ( is_array( $item ) ) {
                $name  = isset( $item['name'] ) ? $item['name'] : ( isset( $item['key'] ) ? $item['key'] : '' );
                $value = isset( $item['value'] ) ? $item['value'] : ( isset( $item['val'] ) ? $item['val'] : '' );
            } elseif ( is_string( $item ) ) {
                $name = $item;
            }

            $name  = sanitize_text_field( $name );
            $value = sanitize_text_field( $value );

            if ( '' !== $name ) {
                $normalized[] = array(
                    'name'  => $name,
                    'value' => $value,
                );
            }
        }

        return $normalized;
    }

    private function extract_json_from_text( $text ) {
        $text = trim( (string) $text );

        if ( '' === $text ) {
            return null;
        }

        $text = preg_replace( '/^```(?:json)?\s*/i', '', $text );
        $text = preg_replace( '/\s*```$/', '', $text );

        $decoded = json_decode( $text, true );
        if ( JSON_ERROR_NONE === json_last_error() ) {
            return $decoded;
        }

        if ( preg_match( '/\[[\s\S]*\]/', $text, $matches ) ) {
            $decoded = json_decode( $matches[0], true );
            if ( JSON_ERROR_NONE === json_last_error() ) {
                return $decoded;
            }
        }

        if ( preg_match( '/\{[\s\S]*\}/', $text, $matches ) ) {
            $decoded = json_decode( $matches[0], true );
            if ( JSON_ERROR_NONE === json_last_error() ) {
                return $decoded;
            }
        }

        return null;
    }

    private function extract_api_error_message( $body, $fallback ) {
        $decoded = json_decode( $body, true );
        if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
            if ( ! empty( $decoded['error']['message'] ) ) {
                return sanitize_text_field( $decoded['error']['message'] );
            }
            if ( ! empty( $decoded['message'] ) ) {
                return sanitize_text_field( $decoded['message'] );
            }
        }

        $body = trim( (string) $body );
        return '' !== $body ? sanitize_text_field( wp_strip_all_tags( $body ) ) : $fallback;
    }

    private function parse_attributes_response( $body ) {
        $decoded = json_decode( $body, true );

        if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
            if ( isset( $decoded['attributes'] ) ) {
                return $this->normalize_attribute_items( $decoded['attributes'] );
            }

            if ( isset( $decoded['output_text'] ) ) {
                $parsed = $this->extract_json_from_text( $decoded['output_text'] );
                if ( is_array( $parsed ) ) {
                    if ( isset( $parsed['attributes'] ) ) {
                        return $this->normalize_attribute_items( $parsed['attributes'] );
                    }

                    return $this->normalize_attribute_items( $parsed );
                }
            }

            if ( isset( $decoded['output'][0]['content'][0]['text'] ) ) {
                $content = $decoded['output'][0]['content'][0]['text'];
                $parsed  = $this->extract_json_from_text( $content );

                if ( is_array( $parsed ) ) {
                    if ( isset( $parsed['attributes'] ) ) {
                        return $this->normalize_attribute_items( $parsed['attributes'] );
                    }

                    return $this->normalize_attribute_items( $parsed );
                }
            }

            if ( isset( $decoded['choices'][0]['message']['content'] ) ) {
                $content = $decoded['choices'][0]['message']['content'];
                $parsed  = $this->extract_json_from_text( $content );

                if ( is_array( $parsed ) ) {
                    if ( isset( $parsed['attributes'] ) ) {
                        return $this->normalize_attribute_items( $parsed['attributes'] );
                    }

                    return $this->normalize_attribute_items( $parsed );
                }
            }

            return $this->normalize_attribute_items( $decoded );
        }

        $parsed = $this->extract_json_from_text( $body );
        if ( is_array( $parsed ) ) {
            if ( isset( $parsed['attributes'] ) ) {
                return $this->normalize_attribute_items( $parsed['attributes'] );
            }

            return $this->normalize_attribute_items( $parsed );
        }

        return new WP_Error( 'wh_invalid_response', 'API error or unexpected response' );
    }

    private function parse_text_response( $body ) {
        $decoded = json_decode( $body, true );

        if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
            if ( isset( $decoded['output_text'] ) ) {
                return trim( wp_strip_all_tags( (string) $decoded['output_text'] ) );
            }

            if ( isset( $decoded['output'][0]['content'][0]['text'] ) ) {
                return trim( wp_strip_all_tags( (string) $decoded['output'][0]['content'][0]['text'] ) );
            }

            if ( isset( $decoded['choices'][0]['message']['content'] ) ) {
                return trim( wp_strip_all_tags( (string) $decoded['choices'][0]['message']['content'] ) );
            }

            if ( isset( $decoded['text'] ) && is_string( $decoded['text'] ) ) {
                return trim( wp_strip_all_tags( $decoded['text'] ) );
            }
        }

        $body = trim( (string) $body );
        $body = preg_replace( '/^```(?:text|markdown|md)?\s*/i', '', $body );
        $body = preg_replace( '/\s*```$/', '', $body );

        return trim( wp_strip_all_tags( $body ) );
    }

    private function get_openai_endpoint( $settings ) {
        return ! empty( $settings['api_endpoint'] ) ? $settings['api_endpoint'] : 'https://api.openai.com/v1/chat/completions';
    }

    private function get_avalai_endpoint() {
        return 'https://api.avalai.ir/v1/chat/completions';
    }

    private function get_model_pricing( $model ) {
        $model = strtolower( trim( (string) $model ) );

        $pricing = array(
            'gpt-4o' => array(
                'input'  => 2.50,
                'output' => 10.00,
            ),
            'gpt-5.4' => array(
                'input'  => 2.50,
                'output' => 15.00,
            ),
            'gpt-5.4-mini' => array(
                'input'  => 0.75,
                'output' => 4.50,
            ),
            'gpt-4o-mini' => array(
                'input'  => 0.15,
                'output' => 0.60,
            ),
        );

        return isset( $pricing[ $model ] ) ? $pricing[ $model ] : null;
    }

    private function calculate_estimated_cost( $model, $usage ) {
        if ( ! is_array( $usage ) ) {
            return null;
        }

        $pricing = $this->get_model_pricing( $model );
        if ( ! $pricing ) {
            return null;
        }

        $prompt_tokens     = isset( $usage['prompt_tokens'] ) ? (float) $usage['prompt_tokens'] : 0;
        $completion_tokens = isset( $usage['completion_tokens'] ) ? (float) $usage['completion_tokens'] : 0;

        $cost = ( $prompt_tokens * $pricing['input'] / 1000000 ) + ( $completion_tokens * $pricing['output'] / 1000000 );

        return round( $cost, 6 );
    }

    private function calculate_estimated_toman_cost( $estimated_cost_usd, $rate ) {
        $estimated_cost_usd = is_numeric( $estimated_cost_usd ) ? (float) $estimated_cost_usd : null;
        $rate               = is_numeric( $rate ) ? (float) $rate : 0;

        if ( null === $estimated_cost_usd || $rate <= 0 ) {
            return null;
        }

        return (int) round( $estimated_cost_usd * $rate );
    }

    private function extract_usage_data( $body ) {
        $decoded = json_decode( $body, true );
        if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
            return null;
        }

        return isset( $decoded['usage'] ) && is_array( $decoded['usage'] ) ? $decoded['usage'] : null;
    }

    private function build_openai_schema() {
        return array(
            'type'                 => 'object',
            'properties'           => array(
                'attributes' => array(
                    'type'  => 'array',
                    'items' => array(
                        'type'                 => 'object',
                        'properties'           => array(
                            'name'  => array(
                                'type' => 'string',
                            ),
                            'value' => array(
                                'type' => 'string',
                            ),
                        ),
                        'required'             => array( 'name', 'value' ),
                        'additionalProperties' => false,
                    ),
                ),
            ),
            'required'             => array( 'attributes' ),
            'additionalProperties' => false,
        );
    }

    private function maybe_build_request_args( $settings, $product_data, $prompt, $task = 'attributes' ) {
        $headers = array(
            'Content-Type' => 'application/json',
        );

        if ( ! empty( $settings['api_key'] ) ) {
            $headers['Authorization'] = 'Bearer ' . $settings['api_key'];
        }

        if ( in_array( $settings['connection_mode'], array( 'gapgpt', 'avalai' ), true ) ) {
            $product_context = wp_json_encode(
                $product_data,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            $model = ( 'avalai' === $settings['connection_mode'] )
                ? ( ! empty( $settings['avalai_model'] ) ? $settings['avalai_model'] : 'gpt-4o-mini' )
                : $settings['openai_model'];

            $body = array(
                'model' => $model,
                'messages' => array(
                    array(
                        'role'    => 'system',
                        'content' => 'You generate WooCommerce product content for WooCommerce. Follow the user instructions exactly.',
                    ),
                    array(
                        'role'    => 'user',
                        'content' => $prompt . "\n\n"
                            . "Product context JSON:\n"
                            . $product_context,
                    ),
                ),
                'temperature' => 0.2,
            );

            if ( 'attributes' === $task ) {
                $body['response_format'] = array(
                    'type'       => 'json_schema',
                    'json_schema' => array(
                        'name'   => 'product_attributes',
                        'schema' => $this->build_openai_schema(),
                        'strict' => true,
                    ),
                );
            } else {
                $body['temperature'] = 0.7;
            }
        } else {
            $body = array(
                'mode'    => $settings['connection_mode'],
                'task'    => $task,
                'prompt'  => $prompt,
                'product' => $product_data,
            );
        }

        return array(
            'headers' => $headers,
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        );
    }

    public function ajax_generate_attributes() {
        check_ajax_referer( 'wh_nonce', 'nonce' );

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( 'Invalid product' );
        }

        $product_data = $this->build_product_context( $product_id );
        if ( is_wp_error( $product_data ) ) {
            wp_send_json_error( $product_data->get_error_message() );
        }

        $settings = $this->get_settings();
        $brand_description = isset( $settings['brand_description'] ) ? $settings['brand_description'] : '';
        $sample_products_text = $this->build_sample_products_text( isset( $settings['sample_products'] ) ? $settings['sample_products'] : array() );
        $prompt   = $this->replace_placeholders( $settings['prompt_template'], $product_data, $brand_description, $sample_products_text );

        // If the user's saved template doesn't include the {sample_products} placeholder,
        // append the sample products automatically so the feature still works.
        if ( '' !== $sample_products_text && false === strpos( (string) $settings['prompt_template'], '{sample_products}' ) ) {
            $prompt .= "\n\nمحصولات نمونه و ویژگی‌هایشان:\n" . $sample_products_text
                . "\nاگر محصول جاری از نظر ماهیت و کاربرد شبیه یکی از محصولات نمونه بالا بود، از همان نام ویژگی‌ها استفاده کن و مقدار مناسب را متناسب با محصول جاری تولید کن. مقادیر نمونه را عیناً کپی نکن.";
        }

        if ( '' !== $brand_description ) {
            $product_data['brand_description'] = $brand_description;
        }

        $product_context_json = wp_json_encode(
            $product_data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $full_prompt = $prompt . "\n\nProduct context JSON:\n" . $product_context_json;

        if ( 'mock' === $settings['connection_mode'] ) {
            wp_send_json_success(
                array(
                    'attributes'    => array(
                        array( 'name' => 'رنگ', 'value' => 'قرمز' ),
                        array( 'name' => 'وزن', 'value' => '500 گرم' ),
                        array( 'name' => 'جنس', 'value' => 'پلی‌استر' ),
                    ),
                    'prompt'        => $prompt,
                    'full_prompt'   => $full_prompt,
                    'connection'    => 'mock',
                )
            );
        }

        if ( 'gapgpt' === $settings['connection_mode'] && empty( $settings['api_key'] ) ) {
            wp_send_json_error( 'OpenAI API Key is required for the Gap GPT connection mode.' );
        }

        if ( 'avalai' === $settings['connection_mode'] && empty( $settings['api_key'] ) ) {
            wp_send_json_error( 'AvalAI API Key is required for the AvalAI connection mode.' );
        }

        if ( 'endpoint' === $settings['connection_mode'] && empty( $settings['api_endpoint'] ) ) {
            wp_send_json_error( 'API Endpoint is required for the custom endpoint mode.' );
        }

        $args = $this->maybe_build_request_args( $settings, $product_data, $prompt );
        if ( 'gapgpt' === $settings['connection_mode'] ) {
            $endpoint = $this->get_openai_endpoint( $settings );
        } elseif ( 'avalai' === $settings['connection_mode'] ) {
            $endpoint = $this->get_avalai_endpoint();
        } else {
            $endpoint = $settings['api_endpoint'];
        }

        $resp = wp_remote_post( $endpoint, $args );
        if ( is_wp_error( $resp ) ) {
            wp_send_json_error( 'Request failed: ' . $resp->get_error_message() );
        }

        $code      = wp_remote_retrieve_response_code( $resp );
        $resp_body = wp_remote_retrieve_body( $resp );

        if ( $code < 200 || $code >= 300 ) {
            wp_send_json_error( $this->extract_api_error_message( $resp_body, 'API returned an error response' ) );
        }

        $attributes = $this->parse_attributes_response( $resp_body );
        if ( is_wp_error( $attributes ) ) {
            wp_send_json_error( $attributes->get_error_message() );
        }

        if ( empty( $attributes ) ) {
            wp_send_json_error( 'No attributes found in response' );
        }

        $usage = $this->extract_usage_data( $resp_body );
        $estimated_cost = $this->calculate_estimated_cost( $settings['openai_model'], $usage );
        $estimated_cost_toman = $this->calculate_estimated_toman_cost( $estimated_cost, $settings['usd_to_toman_rate'] );

        wp_send_json_success(
            array(
                'attributes' => $attributes,
                'prompt'     => $prompt,
                'full_prompt' => $full_prompt,
                'connection' => $settings['connection_mode'],
                'usage'      => $usage,
                'estimated_cost' => $estimated_cost,
                'estimated_cost_toman' => $estimated_cost_toman,
            )
        );
    }

    public function ajax_generate_short_description() {
        check_ajax_referer( 'wh_nonce', 'nonce' );

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( 'Invalid product' );
        }

        $product_context = $this->build_product_context( $product_id );
        if ( is_wp_error( $product_context ) ) {
            wp_send_json_error( $product_context->get_error_message() );
        }

        $settings = $this->get_settings();
        $brand_description = isset( $settings['brand_description'] ) ? $settings['brand_description'] : '';
        $prompt   = $this->replace_placeholders( $settings['short_description_prompt_template'], $product_context, $brand_description );

        if ( '' !== $brand_description ) {
            $product_context['brand_description'] = $brand_description;
        }

        if ( 'mock' === $settings['connection_mode'] ) {
            $mock_text = trim( $product_context['title'] . '، انتخابی خوش‌طعم و کاربردی برای مصرف روزانه است. با کیفیت مناسب، طعم دلپذیر و مشخصات روشن، برای استفاده در خانه یا فروشگاه گزینه‌ای قابل‌اعتماد محسوب می‌شود.' );
            wp_send_json_success(
                array(
                    'short_description' => $mock_text,
                    'prompt'             => $prompt,
                    'connection'         => 'mock',
                    'usage'              => null,
                    'estimated_cost'     => 0,
                    'estimated_cost_toman' => $this->calculate_estimated_toman_cost( 0, $settings['usd_to_toman_rate'] ),
                )
            );
        }

        if ( 'gapgpt' === $settings['connection_mode'] && empty( $settings['api_key'] ) ) {
            wp_send_json_error( 'OpenAI API Key is required for the Gap GPT connection mode.' );
        }

        if ( 'avalai' === $settings['connection_mode'] && empty( $settings['api_key'] ) ) {
            wp_send_json_error( 'AvalAI API Key is required for the AvalAI connection mode.' );
        }

        if ( 'endpoint' === $settings['connection_mode'] && empty( $settings['api_endpoint'] ) ) {
            wp_send_json_error( 'API Endpoint is required for the custom endpoint mode.' );
        }

        $args = $this->maybe_build_request_args( $settings, $product_context, $prompt, 'short_description' );
        if ( 'gapgpt' === $settings['connection_mode'] ) {
            $endpoint = $this->get_openai_endpoint( $settings );
        } elseif ( 'avalai' === $settings['connection_mode'] ) {
            $endpoint = $this->get_avalai_endpoint();
        } else {
            $endpoint = $settings['api_endpoint'];
        }

        $resp = wp_remote_post( $endpoint, $args );
        if ( is_wp_error( $resp ) ) {
            wp_send_json_error( 'Request failed: ' . $resp->get_error_message() );
        }

        $code      = wp_remote_retrieve_response_code( $resp );
        $resp_body = wp_remote_retrieve_body( $resp );

        if ( $code < 200 || $code >= 300 ) {
            wp_send_json_error( $this->extract_api_error_message( $resp_body, 'API returned an error response' ) );
        }

        $short_description = $this->parse_text_response( $resp_body );
        if ( '' === $short_description ) {
            wp_send_json_error( 'No text found in response' );
        }

        $usage = $this->extract_usage_data( $resp_body );
        $estimated_cost = $this->calculate_estimated_cost( $settings['openai_model'], $usage );
        $estimated_cost_toman = $this->calculate_estimated_toman_cost( $estimated_cost, $settings['usd_to_toman_rate'] );

        wp_send_json_success(
            array(
                'short_description' => $short_description,
                'prompt'             => $prompt,
                'connection'         => $settings['connection_mode'],
                'usage'              => $usage,
                'estimated_cost'     => $estimated_cost,
                'estimated_cost_toman' => $estimated_cost_toman,
            )
        );
    }

    public function ajax_generate_product_description() {
        check_ajax_referer( 'wh_nonce', 'nonce' );

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( 'Invalid product' );
        }

        $product_context = $this->build_product_context( $product_id );
        if ( is_wp_error( $product_context ) ) {
            wp_send_json_error( $product_context->get_error_message() );
        }

        $settings = $this->get_settings();
        $brand_description = isset( $settings['brand_description'] ) ? $settings['brand_description'] : '';
        $prompt   = $this->replace_placeholders( $settings['product_description_prompt_template'], $product_context, $brand_description );

        if ( '' !== $brand_description ) {
            $product_context['brand_description'] = $brand_description;
        }

        $product_context_json = wp_json_encode(
            $product_context,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $full_prompt = $prompt . "\n\nProduct context JSON:\n" . $product_context_json;

        if ( 'mock' === $settings['connection_mode'] ) {
            $mock_text = trim( $product_context['title'] . ' یک محصول کاربردی و باکیفیت است که با توجه به ویژگی‌ها و توضیحات موجود، گزینه‌ای مناسب برای خرید محسوب می‌شود. این محصول با تکیه بر مشخصات واقعی خود، نیاز کاربر را به‌خوبی پوشش می‌دهد و تجربه‌ای مطمئن ایجاد می‌کند.' );
            wp_send_json_success(
                array(
                    'product_description' => $mock_text,
                    'prompt'              => $prompt,
                    'full_prompt'         => $full_prompt,
                    'connection'          => 'mock',
                    'usage'               => null,
                    'estimated_cost'      => 0,
                    'estimated_cost_toman' => $this->calculate_estimated_toman_cost( 0, $settings['usd_to_toman_rate'] ),
                )
            );
        }

        if ( 'gapgpt' === $settings['connection_mode'] && empty( $settings['api_key'] ) ) {
            wp_send_json_error( 'OpenAI API Key is required for the Gap GPT connection mode.' );
        }

        if ( 'avalai' === $settings['connection_mode'] && empty( $settings['api_key'] ) ) {
            wp_send_json_error( 'AvalAI API Key is required for the AvalAI connection mode.' );
        }

        if ( 'endpoint' === $settings['connection_mode'] && empty( $settings['api_endpoint'] ) ) {
            wp_send_json_error( 'API Endpoint is required for the custom endpoint mode.' );
        }

        $args = $this->maybe_build_request_args( $settings, $product_context, $prompt, 'product_description' );
        if ( 'gapgpt' === $settings['connection_mode'] ) {
            $endpoint = $this->get_openai_endpoint( $settings );
        } elseif ( 'avalai' === $settings['connection_mode'] ) {
            $endpoint = $this->get_avalai_endpoint();
        } else {
            $endpoint = $settings['api_endpoint'];
        }

        $resp = wp_remote_post( $endpoint, $args );
        if ( is_wp_error( $resp ) ) {
            wp_send_json_error( 'Request failed: ' . $resp->get_error_message() );
        }

        $code      = wp_remote_retrieve_response_code( $resp );
        $resp_body = wp_remote_retrieve_body( $resp );

        if ( $code < 200 || $code >= 300 ) {
            wp_send_json_error( $this->extract_api_error_message( $resp_body, 'API returned an error response' ) );
        }

        $product_description = $this->parse_text_response( $resp_body );
        if ( '' === $product_description ) {
            wp_send_json_error( 'No text found in response' );
        }

        $usage = $this->extract_usage_data( $resp_body );
        $estimated_cost = $this->calculate_estimated_cost( $settings['openai_model'], $usage );
        $estimated_cost_toman = $this->calculate_estimated_toman_cost( $estimated_cost, $settings['usd_to_toman_rate'] );

        wp_send_json_success(
            array(
                'product_description' => $product_description,
                'prompt'              => $prompt,
                'full_prompt'         => $full_prompt,
                'connection'          => $settings['connection_mode'],
                'usage'               => $usage,
                'estimated_cost'      => $estimated_cost,
                'estimated_cost_toman' => $estimated_cost_toman,
            )
        );
    }

    private function wh_normalize_label( $text ) {
        $text = wp_strip_all_tags( (string) $text );
        // Remove ZWNJ / RLM / LRM marks that are common in Persian text.
        $text = str_replace( array( "\xE2\x80\x8C", "\xE2\x80\x8F", "\xE2\x80\x8E" ), '', $text );
        $text = preg_replace( '/\s+/u', ' ', $text );
        $text = trim( $text );

        return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
    }

    private function maybe_register_attribute_taxonomy( $taxonomy ) {
        if ( '' === (string) $taxonomy || taxonomy_exists( $taxonomy ) ) {
            return;
        }

        register_taxonomy(
            $taxonomy,
            array( 'product' ),
            array(
                'hierarchical' => false,
                'show_ui'      => false,
                'query_var'    => true,
                'rewrite'      => false,
            )
        );
    }

    /**
     * Find an existing global product attribute by label/name, or create it.
     * Returns the taxonomy name (e.g. "pa_color") or WP_Error.
     */
    private function get_or_create_global_attribute( $label ) {
        $label = sanitize_text_field( $label );
        if ( '' === $label ) {
            return new WP_Error( 'wh_attr_empty', 'نام ویژگی خالی است.' );
        }

        $target = $this->wh_normalize_label( $label );

        foreach ( wc_get_attribute_taxonomies() as $tax ) {
            $label_match = $this->wh_normalize_label( $tax->attribute_label ) === $target;
            $name_match  = $this->wh_normalize_label( $tax->attribute_name ) === $target;

            if ( $label_match || $name_match ) {
                $taxonomy = wc_attribute_taxonomy_name( $tax->attribute_name );
                $this->maybe_register_attribute_taxonomy( $taxonomy );
                return $taxonomy;
            }
        }

        // Not found — create a new global attribute.
        $slug = wc_sanitize_taxonomy_name( $label );
        if ( '' === $slug ) {
            $slug = 'wh-' . substr( md5( $label ), 0, 12 );
        }
        // Taxonomy name is limited to 32 chars (incl. the "pa_" prefix); WC limits the slug to 28.
        if ( strlen( $slug ) > 28 ) {
            $slug = substr( $slug, 0, 18 ) . substr( md5( $label ), 0, 8 );
        }

        $attribute_id = wc_create_attribute(
            array(
                'name'         => $label,
                'slug'         => $slug,
                'type'         => 'select',
                'order_by'     => 'menu_order',
                'has_archives' => false,
            )
        );

        // If the slug caused a problem, retry once with a safe ASCII slug.
        if ( is_wp_error( $attribute_id ) ) {
            $attribute_id = wc_create_attribute(
                array(
                    'name'         => $label,
                    'slug'         => 'wh-' . substr( md5( $label . microtime() ), 0, 12 ),
                    'type'         => 'select',
                    'order_by'     => 'menu_order',
                    'has_archives' => false,
                )
            );
        }

        if ( is_wp_error( $attribute_id ) ) {
            return $attribute_id;
        }

        $created  = wc_get_attribute( $attribute_id );
        $taxonomy = ( $created && ! empty( $created->slug ) ) ? $created->slug : wc_attribute_taxonomy_name( $slug );

        // Make sure later lookups in this same request see the new attribute.
        delete_transient( 'wc_attribute_taxonomies' );
        $this->maybe_register_attribute_taxonomy( $taxonomy );

        return $taxonomy;
    }

    /**
     * Find an existing term (value) inside the taxonomy, or create it.
     * Returns the term id (int) or WP_Error.
     */
    private function get_or_create_attribute_term( $value, $taxonomy ) {
        $value = sanitize_text_field( $value );
        if ( '' === $value ) {
            return new WP_Error( 'wh_term_empty', 'مقدار ویژگی خالی است.' );
        }

        $existing = term_exists( $value, $taxonomy );
        if ( $existing && ! is_wp_error( $existing ) && ! empty( $existing['term_id'] ) ) {
            return (int) $existing['term_id'];
        }

        $created = wp_insert_term( $value, $taxonomy );
        if ( is_wp_error( $created ) ) {
            $existing_id = $created->get_error_data( 'term_exists' );
            if ( $existing_id ) {
                return (int) $existing_id;
            }
            return $created;
        }

        return (int) $created['term_id'];
    }

    public function ajax_add_attributes() {
        check_ajax_referer( 'wh_nonce', 'nonce' );

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $attrs      = isset( $_POST['attributes'] ) ? wp_unslash( $_POST['attributes'] ) : array();

        if ( ! $product_id || empty( $attrs ) || ! is_array( $attrs ) ) {
            wp_send_json_error( 'Invalid data' );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( 'Product not found' );
        }

        // Group requested values per attribute name.
        $grouped = array();
        foreach ( $attrs as $a ) {
            if ( ! is_array( $a ) || empty( $a['name'] ) ) {
                continue;
            }

            $name  = sanitize_text_field( $a['name'] );
            $value = isset( $a['value'] ) ? sanitize_text_field( $a['value'] ) : '';

            if ( '' === $name || '' === $value ) {
                continue;
            }

            $grouped[ $name ][] = $value;
        }

        if ( empty( $grouped ) ) {
            wp_send_json_error( 'هیچ ویژگی معتبری برای افزودن وجود ندارد. نام و مقدار را بررسی کنید.' );
        }

        // Read the raw stored attributes meta (most reliable for taxonomy attributes).
        $product_attributes = get_post_meta( $product_id, '_product_attributes', true );
        if ( ! is_array( $product_attributes ) ) {
            $product_attributes = array();
        }

        $position     = count( $product_attributes );
        $added_terms  = 0;
        $new_attrs    = 0;
        $touched_tax  = array();
        $errors       = array();

        foreach ( $grouped as $name => $values ) {
            // 1) Find or create the global attribute (the "key").
            $taxonomy = $this->get_or_create_global_attribute( $name );
            if ( is_wp_error( $taxonomy ) ) {
                $errors[] = $name . ': ' . $taxonomy->get_error_message();
                continue;
            }

            // Make sure the taxonomy is registered before touching its terms.
            $this->maybe_register_attribute_taxonomy( $taxonomy );

            // 2) Find or create each value (term). Existing values are reused as-is.
            $new_term_ids = array();
            foreach ( array_unique( $values ) as $value ) {
                $term_id = $this->get_or_create_attribute_term( $value, $taxonomy );
                if ( is_wp_error( $term_id ) ) {
                    $errors[] = $name . ' / ' . $value . ': ' . $term_id->get_error_message();
                    continue;
                }
                $new_term_ids[] = (int) $term_id;
                $added_terms++;
            }

            if ( empty( $new_term_ids ) ) {
                continue;
            }

            // 3) Merge with terms already assigned to this product for this attribute.
            $current_ids = wp_get_object_terms( $product_id, $taxonomy, array( 'fields' => 'ids' ) );
            if ( is_wp_error( $current_ids ) ) {
                $current_ids = array();
            }

            $all_term_ids = array_values( array_unique( array_map( 'intval', array_merge( $current_ids, $new_term_ids ) ) ) );

            // 4) Assign the terms to the product object (this powers the product filters).
            $set = wp_set_object_terms( $product_id, $all_term_ids, $taxonomy, false );
            if ( is_wp_error( $set ) ) {
                $errors[] = $name . ': ' . $set->get_error_message();
                continue;
            }

            // 5) Register the attribute in the raw _product_attributes meta as a taxonomy attribute.
            if ( ! isset( $product_attributes[ $taxonomy ] ) ) {
                $position++;
                $new_attrs++;
            }

            $product_attributes[ $taxonomy ] = array(
                'name'         => $taxonomy,
                'value'        => '',
                'position'     => isset( $product_attributes[ $taxonomy ]['position'] ) ? $product_attributes[ $taxonomy ]['position'] : $position,
                'is_visible'   => 1,
                'is_variation' => isset( $product_attributes[ $taxonomy ]['is_variation'] ) ? (int) $product_attributes[ $taxonomy ]['is_variation'] : 0,
                'is_taxonomy'  => 1,
            );

            $touched_tax[] = $taxonomy;

            // Remove any leftover custom (non-taxonomy) attribute with the same label
            // so we don't end up with duplicate "key" rows on the product.
            foreach ( $product_attributes as $key => $row ) {
                if ( $key === $taxonomy || ! is_array( $row ) ) {
                    continue;
                }
                $row_is_taxonomy = ! empty( $row['is_taxonomy'] );
                $row_name        = isset( $row['name'] ) ? $row['name'] : '';
                if ( ! $row_is_taxonomy && $this->wh_normalize_label( $row_name ) === $this->wh_normalize_label( $name ) ) {
                    unset( $product_attributes[ $key ] );
                }
            }
        }

        if ( 0 === $added_terms ) {
            wp_send_json_error( ! empty( $errors ) ? implode( ' | ', $errors ) : 'هیچ ویژگی‌ای اضافه نشد.' );
        }

        // Persist the attributes meta directly.
        update_post_meta( $product_id, '_product_attributes', $product_attributes );

        // Re-save through the CRUD so WooCommerce fires its update hooks and
        // regenerates the product-attributes lookup table used by filters.
        $fresh = wc_get_product( $product_id );
        if ( $fresh ) {
            $fresh->save();
        }

        wc_delete_product_transients( $product_id );
        clean_post_cache( $product_id );

        // Verification: re-read what is actually stored on the product right now.
        $verify = array();
        foreach ( array_values( array_unique( $touched_tax ) ) as $tax ) {
            $term_names = wp_get_object_terms( $product_id, $tax, array( 'fields' => 'names' ) );
            $verify[]   = $tax . ' → ' . ( is_wp_error( $term_names ) ? 'ERR' : implode( ', ', $term_names ) );
        }

        $meta_now  = get_post_meta( $product_id, '_product_attributes', true );
        $meta_keys = is_array( $meta_now ) ? array_keys( $meta_now ) : array();

        wp_send_json_success(
            array(
                'message'     => 'ویژگی‌ها به‌صورت ویژگی سراسری ووکامرس ثبت و به محصول اضافه شدند.',
                'added_terms' => $added_terms,
                'new_attrs'   => $new_attrs,
                'taxonomies'  => array_values( array_unique( $touched_tax ) ),
                'verify'      => $verify,
                'meta_keys'   => $meta_keys,
                'errors'      => $errors,
            )
        );
    }
}

new WooCamers_Helper();
