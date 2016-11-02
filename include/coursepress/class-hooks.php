<?php
/**
 * CourePress Hooks
 *
 * @package WordPress
 * @subpackage CoursePress
 **/
class CoursePress_Hooks {
	static $warning_message = '';

	public static function init() {
		// Listen to course withdrawal request
		add_action( 'init', array( 'CoursePress_Data_Student', 'withdraw_from_course' ) );

		// Listen to module submission
		add_action( 'init', array( 'CoursePress_Module', 'process_submission' ) );

		// Listen to enrollment request
		add_action( 'init', array( 'CoursePress_Template_Student', 'process_enrollment' ) );

		// Listen to comment submission
		add_action( 'init', array( 'CoursePress_Data_Discussion', 'init' ), 100 );

		// Log student visit on modules
		add_action( 'coursepress_module_view', array( 'CoursePress_Data_Student', 'log_student_activity' ), 10, 2 );

		// Edit Course
		add_filter( 'post_updated_messages', array( 'CoursePress_Admin_Edit', 'updated_messages' ) );
		add_action( 'dbx_post_advanced', array( 'CoursePress_Admin_Edit', 'init_hooks' ) );
		// Per course certificate preview
		add_action( 'init', array( 'CoursePress_Admin_Edit', 'certificate_preview' ) );
		// Update Course
		add_action( 'wp_ajax_update_course', array( 'CoursePress_Admin_Controller_Course', 'update_course' ) );
		// Update UnitBuilder
		add_action( 'wp_ajax_unit_builder', array( 'CoursePress_Admin_Controller_Unit', 'unit_builder_ajax' ) );

		// Hook to admin ajax request
		add_action( 'wp_ajax_coursepress_request', array( __CLASS__, 'process_request' ) );
		add_action( 'wp_ajax_nopriv_coursepress_request', array( __CLASS__, 'process_request' ) );

		// Course list
		add_action( 'admin_init', array( 'CoursePress_Admin_Courses', 'init' ) );

		// Course Instructors list
		add_action( 'admin_init', array( 'CoursePress_Admin_Instructors', 'init' ) );

		// Search user
		add_action( 'wp_ajax_coursepress_user_search', array( 'CoursePress_Admin_Students', 'search_user' ) );

		// Set front scripts
		add_action( 'wp_enqueue_scripts', array( 'CoursePress_Helper_Javascript', 'front_assets' ) );
		// Print assets at wp_footer if CP shortcode is used!
		add_action( 'wp_footer', array( 'CoursePress_Helper_Javascript', 'maybe_print_assets' ) );

		// Set admin scripts
		add_action( 'admin_enqueue_scripts', array( 'CoursePress_Helper_Javascript', 'enqueue_admin_scripts' ) );
		add_action( 'admin_footer', array( 'CoursePress_Helper_Javascript', 'enqueue_scripts' ) );
	}

	/**
	 * Receives ajax request.
	 **/
	public static function process_request() {
		$input = json_decode( file_get_contents( 'php://input' ) );

		if ( ! empty( $input->cpnonce ) && wp_verify_nonce( $input->cpnonce, 'coursepress_nonce' ) ) {
			$method = $input->method;
			$class = $input->className;

			if ( class_exists( $class ) && method_exists( $class, $method ) ) {
				$input = CoursePress_Helper_Utility::object_to_array( $input );

				call_user_func( array( $class, $method ), $input );

				exit;
			}
		}
	}
}