<?php
/*
 Plugin Name: Runscope Status Page
 Plugin URI: https://github.com/yllus/runscope-status
 Description: Display a pretty status page at a URL on your WordPress website, with all data pulled from a Runscope bucket.
 Author: Sully Syed
 Version: 1.0
 Author URI: http://yllus.com/
*/
class RunscopeStatus {
    /**
     * A reference to an instance of this class.
     */
    private static $instance;

    /**
     * The array of templates that this plugin tracks.
     */
    protected $templates;

    /**
     * Returns an instance of this class. 
     */
    public static function get_instance() {
        if ( null == self::$instance ) {
            self::$instance = new RunscopeStatus();
        } 

        return self::$instance;
    } 

    /**
     * Initializes the plugin by setting filters and administration functions.
     */
    private function __construct() {
        $this->templates = array();

        // Add a filter to the attributes metabox to inject template into the cache.
        add_filter( 'page_attributes_dropdown_pages_args', array( $this, 'register_page_template' ) );

        // Add a filter to the save post to inject out template into the page cache
        add_filter( 'wp_insert_post_data', array( $this, 'register_page_template' ) );

        // Add a filter to the template include to determine if the page has our 
		// template assigned and return it's path
        add_filter( 'template_include', array( $this, 'view_page_template' ) );

        // Add your templates to this array.
        $this->templates = array(
            'page-RUNSCOPESTATUS.php'     => 'Runscope Status Page',
        );

        // If we're viewing our custom page template, enqueue a specific stylesheet.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		// Add our custom Runscope metabox for anyone editing a Page with this template set.
		add_action( 'admin_init', array( $this, 'add_metaboxes' ) );

		// Save data from our custom Runscope metabox.
		add_action( 'save_post', array( $this, 'save_metabox_data' ), 10, 2 );
    } 

    public static function metabox_runscope_settings( $post ) {
    	$str_rsp_access_token 	= get_post_meta( $post->ID, 'rsp_access_token', true );
    	$str_rsp_bucket_key 	= get_post_meta( $post->ID, 'rsp_bucket_key', true );
    	?>
    	<label><strong>Access Token</strong></label>
	    <br>
	    <input type="password" id="rsp_access_token" name="rsp_access_token" value="<?php echo $str_rsp_access_token; ?>" style="width: 80%;" />
	    <br>
	    If you don't already have an OAuth2 access token to use, visit <a target="_blank" href="https://www.runscope.com/applications">https://www.runscope.com/applications</a> and 
	    create one. Once it's been created, grab the <strong>Personal Access Token > Access Token</strong> value and enter it into the field above.

	    <br><br>

	    <label><strong>Bucket Key</strong></label>
	    <br>
	    <input type="text" id="rsp_bucket_key" name="rsp_bucket_key" value="<?php echo $str_rsp_bucket_key; ?>" style="width: 80%;" />
	    <br>
	    Copy and paste in the Bucket Key value containing the Tests you wish to see displayed on this status page. You can grab the Bucket Key value by selecting 
	    the Bucket you wish to use, clicking <strong>API Tests</strong>, then <strong>Bucket Settings</strong> and then looking at the URL. The Bucket Key is the 
	    value found between radar/ and /configure (eg. <strong>r6jaa77r5ltx</strong> in the URL https://www.runscope.com/radar/r6jaa77r5ltx/configure ).

	    <br><br>

	    <strong>Note:</strong> Need additional status pages for each of your buckets? You can always create additional buckets in Runscope and then create a Page in WordPress to point to each of them!
    	<?php
    }

    /**
     * ?
     *
     */
    public function add_metaboxes() {
    	if ( is_admin() && isset($_GET['post']) ) {
			$post_id = $_GET['post'] ? (int) $_GET['post'] : 0;
			$template_file = get_post_meta( $post_id, '_wp_page_template', true );

			if ( $template_file == 'page-RUNSCOPESTATUS.php' ) {
        		add_meta_box('runscope_metabox_text_box', 'Runscope Status Page Settings', array( $this, 'metabox_runscope_settings'), 'page', 'normal', 'high');
        	}
		}
	}

    /**
     * ?
     *
     */
	public static function save_metabox_data() {
	    global $post;

	    update_post_meta( $post->ID, 	'rsp_access_token', 	$_POST['rsp_access_token'] );
		update_post_meta( $post->ID, 	'rsp_bucket_key', 		$_POST['rsp_bucket_key'] );
	}

    /**
     * Enqueue a bit of CSS only on our custom page template.
     *
     */
    public function enqueue_styles() {
		if ( is_page_template('page-RUNSCOPESTATUS.php')  ) {
        	wp_enqueue_style( 'rsp-page-styles', plugin_dir_url( __FILE__ ) . '/runscope-status.css' );
        }
	}

    /**
     * Adds our template to the pages cache in order to trick WordPress
     * into thinking the template file exists where it doens't really exist.
     *
     */
    public function register_page_template( $atts ) {
        // Create the key used for the themes cache
        $cache_key = 'page_templates-' . md5( get_theme_root() . '/' . get_stylesheet() );

        // Retrieve the cache list. 
		// If it doesn't exist, or it's empty prepare an array
		$templates = wp_get_theme()->get_page_templates();
        if ( empty( $templates ) ) {
            $templates = array();
        } 

        // New cache, therefore remove the old one
        wp_cache_delete( $cache_key , 'themes');

        // Now add our template to the list of templates by merging our templates
        // with the existing templates array from the cache.
        $templates = array_merge( $templates, $this->templates );

        // Add the modified cache to allow WordPress to pick it up for listing
        // available templates
        wp_cache_add( $cache_key, $templates, 'themes', 1800 );

        return $atts;
    } 

    /**
     * Checks if the template is assigned to the page
     */
    public function view_page_template( $template ) {
        global $post;

        if ( !isset($this->templates[get_post_meta( $post->ID, '_wp_page_template', true )] ) ) {
            return $template;
        } 

        $file = plugin_dir_path(__FILE__). get_post_meta( $post->ID, '_wp_page_template', true );
		
        // Just to be safe, we check if the file exist first
        if ( file_exists( $file ) ) {
            return $file;
        } 
		else { 
			echo $file; 
		}

        return $template;
    } 

    public static function ajax_display_test_results() {
    	$post_id = 79;

    	$obj_response = RunscopeStatus::get_bucket_tests($post_id);
    	if ( $obj_response->rsp_success != '0' ) {
    		echo "Sorry, there was an issue retrieving your Runscope tests (" . $obj_response->rsp_message . ").";

    		exit;
    	}

    	$arr_test_results = array();
    	foreach ( $obj_response->data as $test ) {
    		$obj_response_test = RunscopeStatus::get_test_results($post_id, $test->id);

    		print_r($obj_response_test);
    	}

    	//print_r($obj_response);
    	exit;
    }

    public static function get_test_results( $post_id, $test_id ) {
    	$str_rsp_access_token 	= get_post_meta( $post_id, 'rsp_access_token', true );
    	$str_rsp_bucket_key 	= get_post_meta( $post_id, 'rsp_bucket_key', true );

    	$str_url = 'https://api.runscope.com/buckets/' . $str_rsp_bucket_key . '/tests/' . $test_id . '/results';
    	$args = $args = array(
			'method' 	=> 'GET',
			'timeout' 	=> 10,
			'sslverify' => false,
			'headers' 	=> array(
				'Authorization' 	=> 'Bearer ' . $str_rsp_access_token,
			)
		);

    	$response = wp_remote_request( $str_url, $args );

    	if ( is_wp_error($response) ) {
    		$response_message = $response->get_error_message();

    		$obj_response = new stdClass();
    		$obj_response->rsp_success = '-3';
			$obj_response->rsp_message = $response_message;

			return $obj_response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( false === strstr( $response_code, '200' ) ) {	
			$response_message = wp_remote_retrieve_response_message( $response );

			$obj_response = new stdClass();
    		$obj_response->rsp_success = '-4';
			$obj_response->rsp_message = $response_message;

			return $obj_response;
		}

		$obj_response = json_decode( wp_remote_retrieve_body( $response ) );
		$obj_response->rsp_success = '0';
		$obj_response->rsp_message = 'API successfully contacted and test details retrieved.';

		return $obj_response;
    }

    public static function get_bucket_tests( $post_id ) {
    	$str_rsp_access_token 	= get_post_meta( $post_id, 'rsp_access_token', true );
    	$str_rsp_bucket_key 	= get_post_meta( $post_id, 'rsp_bucket_key', true );

    	$str_url = 'https://api.runscope.com/buckets/' . $str_rsp_bucket_key . '/tests';
    	$args = $args = array(
			'method' 	=> 'GET',
			'timeout' 	=> 10,
			'sslverify' => false,
			'headers' 	=> array(
				'Authorization' 	=> 'Bearer ' . $str_rsp_access_token,
			)
		);

    	$response = wp_remote_request( $str_url, $args );

    	if ( is_wp_error($response) ) {
    		$response_message = $response->get_error_message();

    		$obj_response = new stdClass();
    		$obj_response->rsp_success = '-1';
			$obj_response->rsp_message = $response_message;

			return $obj_response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( false === strstr( $response_code, '200' ) ) {	
			$response_message = wp_remote_retrieve_response_message( $response );

			$obj_response = new stdClass();
    		$obj_response->rsp_success = '-2';
			$obj_response->rsp_message = $response_message;

			return $obj_response;
		}

		$obj_response = json_decode( wp_remote_retrieve_body( $response ) );
		$obj_response->rsp_success = '0';
		$obj_response->rsp_message = 'API successfully contacted and bucket tests retrieved.';

		return $obj_response;
    }
} 
add_action( 'plugins_loaded', array( 'RunscopeStatus', 'get_instance' ) );
?>