<?php

class CoursePress_View_Admin_Communication_Discussion {

	public static $slug = 'coursepress_discussions';
	private static $title = '';
	private static $menu_title = '';

	public static function init() {
		self::$title = __( 'Discussions', 'CP_TD' );
		self::$menu_title = __( 'Discussions', 'CP_TD' );

		add_action( 'coursepress_admin_' . self::$slug, array( __CLASS__, 'render_page' ) );
		add_filter( 'coursepress_admin_valid_pages', array( __CLASS__, 'add_valid' ) );
		add_filter( 'coursepress_admin_pages', array( __CLASS__, 'add_page' ) );
		add_action( 'coursepress_settings_page_pre_render_' . self::$slug, array( __CLASS__, 'process_form' ) );

		// Update Discussion
		add_action( 'wp_ajax_update_discussion', array( __CLASS__, 'update_discussion' ) );
	}

	public static function add_page( $pages ) {
		$pages[ self::$slug ] = array(
			'title' => self::$title,
			'menu_title' => self::$menu_title,
			/** This filter is documented in include/coursepress/helper/class-setting.php */
			'cap' => apply_filters( 'coursepress_capabilities', 'coursepress_discussions_cap' ),
		);

		return $pages;
	}

	public static function add_valid( $valid_pages ) {
		$valid_pages[] = self::$slug;

		return $valid_pages;
	}

	public static function process_form() {

		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'edit_discussion' ) ) {

			// Update the discussion
			$id = isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : false;

			/**
			 * check permissions
			 */
			if ( ! empty( $id ) ) {
				if ( ! CoursePress_Data_Capabilities::can_update_discussion( $id ) ) {
					return __( 'You do not have permission to edit this discussion.', 'CP_TD' );
				}
			}

			$content = CoursePress_Helper_Utility::filter_content( $_POST['post_content'] );
			$title = CoursePress_Helper_Utility::filter_content( $_POST['post_title'] );
			$course_id = 'all' === $_POST['meta_course_id'] ? $_POST['meta_course_id'] : (int) $_POST['meta_course_id'];
			$unit_id = 'course' === $_POST['meta_unit_id'] ? $_POST['meta_unit_id'] : (int) $_POST['meta_unit_id'];
			$post_status = isset( $_POST['post_status'] ) ? $_POST['post_status'] : 'draft';

			$args = array(
				'post_title' => $title,
				'post_content' => $content,
				'post_type' => CoursePress_Data_Discussion::get_post_type_name(),
				'post_status' => $post_status,
			);

			if ( ! empty( $id ) ) {
				$args['ID'] = $id;
			}

			$id = wp_insert_post( $args );

			update_post_meta( $id, 'course_id', $course_id );
			update_post_meta( $id, 'unit_id', $unit_id );

			$url = admin_url( 'admin.php?page=' . self::$slug );
			wp_redirect( esc_url_raw( $url ) );
			exit;
		}
	}

	public static function render_page() {
		$allowed_actions = array( 'edit' );

		$action = isset( $_GET['action'] ) && in_array( $_GET['action'], $allowed_actions ) ? sanitize_text_field( $_GET['action'] ) : '';

		$list_discussion = new CoursePress_Helper_Table_DiscussionList();
		$list_discussion->prepare_items();

		$content = '<div class="wrap">';

		if ( empty( $action ) ) {
			$content .= CoursePress_Helper_UI::get_admin_page_title(
				self::$menu_title,
				__( 'New Discussion', 'CP_TD' ),
				admin_url( 'admin.php?page=' . self::$slug . '&action=edit&id=new' ),
				CoursePress_Data_Capabilities::can_add_discussion( 0 )
			);
			$bulk_nonce = wp_create_nonce( 'bulk_action_nonce' );
			$content .= '<div class="nonce-holder" data-nonce="' . $bulk_nonce . '"></div>';
			ob_start();
			$list_discussion->display();
			$content .= ob_get_clean();
		} else {
			switch ( $action ) {
				case 'edit':
					$title = __( 'Edit Discussion', 'CP_TD' );
					if ( isset( $_GET['id'] ) && 'new' == $_GET['id'] ) {
						$title = __( 'Add New Discussion', 'CP_TD' );
					}
					$content .= CoursePress_Helper_UI::get_admin_page_title( $title );
					$content .= self::render_edit_page();
					break;
			}
		}

		$content .= '</div>';
		$content .= '</div>';

		echo $content;
	}

	public static function render_edit_page() {
		$the_id = isset( $_GET['id'] ) ? $_GET['id'] : false;
		$the_id = 'new' === $the_id ? $the_id : (int) $the_id;

		if ( empty( $the_id ) ) {
			return '';
		}

		if ( 'new' !== $the_id ) {
			if ( ! CoursePress_Data_Capabilities::can_update_discussion( $the_id ) ) {
				return __( 'You do not have permission to edit this discussion.', 'CP_TD' );
			}
			$post = get_post( $the_id );
			$attributes = CoursePress_Data_Discussion::attributes( $the_id );
			$course_id = $attributes['course_id'];
			$unit_id = $attributes['unit_id'];
			$post_status = $post->post_status;
			$post_title = $post->post_title;
			$post_content = $post->post_content;
		} else {
			if ( ! CoursePress_Data_Capabilities::can_add_discussion( 0 ) ) {
				return __( 'You do not have permission to add discussion.', 'CP_TD' );
			}
			$course_id = 'all';
			$unit_id = 'course';
			$post_status = 'publish';
			$post_title = '';
			$post_content = '';
		}

		$options = array();
		$options['value'] = $course_id;
		$options['class'] = 'medium';
		if ( CoursePress_Data_Capabilities::can_add_discussion_to_all() ) {
		} else {
			$options['courses'] = self::get_courses();
			if ( empty( $options['courses'] ) ) {
				return __( 'You do not have permission to add discussion.', 'CP_TD' );
			}
		}

		/**
		 * units
		 */
		$options_unit = array();
		$options_unit['value'] = $unit_id;
		$options_unit['class'] = 'medium';
		$options_unit['first_option'] = array(
			'text' => __( 'All units', 'CP_TD' ),
			'value' => 'course',
		);

		$content = '';

		$content .= '<form method="POST" class="edit">';

		$content .= '
			<input type="hidden" name="post_status" value="' . esc_attr( $post_status ) . '" />
			' . wp_nonce_field( 'edit_discussion', '_wpnonce', true, false ) . '
			<label><strong>' . esc_html__( 'Discussion Title', 'CP_TD' ). '</strong><br />
			<input type="text" class="wide" name="post_title" value="' . esc_attr( $post_title ) . '" /></label>

			<label><strong>' . esc_html__( 'Discussion Content', 'CP_TD' ). '</strong><br />';

		$editor_name = 'post_content';
		$editor_id = 'postContent';
		$args = array(
			'textarea_name' => $editor_name,
			'editor_class' => 'cp-editor',
			'textarea_rows' => 10,
		);

		// Filter $args
		$args = apply_filters( 'coursepress_element_editor_args', $args, $editor_name, $editor_id );

		ob_start();
		wp_editor( $post_content, $editor_id, $args );
		$content .= ob_get_clean();
		$content .= '</label>';

		$content .= '
			<label><strong>' . esc_html__( 'Related Course', 'CP_TD' ) . '</strong><br />
			' . CoursePress_Helper_UI::get_course_dropdown( 'course_id', 'meta_course_id', false, $options ) . '
			</label>
			<label><strong>' . esc_html__( 'Related Unit', 'CP_TD' ) . '</strong><br />
			' . CoursePress_Helper_UI::get_unit_dropdown( 'unit_id', 'meta_unit_id', $course_id, false, $options_unit ) . '
			</label>
			<label class="right">
				<input type="submit" class="button button-primary" value="' . esc_attr__( 'Save Discussion', 'CP_TD' ) . '" />
			</label>
		';

		$content .= '</form>';

		return $content;
	}

	public static function update_discussion() {

		$data = json_decode( file_get_contents( 'php://input' ) );
		$json_data = array();
		$success = false;

		$action = isset( $data->action ) ? $data->action : '';
		$json_data['action'] = $action;

		if ( empty( $data->action ) ) {
			$json_data['message'] = __( 'Discussion Update: No action.', 'CP_TD' );
			wp_send_json_error( $json_data );
		}

		switch ( $action ) {

			case 'delete':

				if ( wp_verify_nonce( $data->data->nonce, 'delete-discussion' ) ) {

					$discussion_id = $data->data->discussion_id;

					wp_delete_post( $discussion_id );

					$json_data['discussion_id'] = $discussion_id;
					$json_data['nonce'] = wp_create_nonce( 'delete-discussion' );
					$success = true;
				}

				break;

			case 'toggle':

				$discussion_id = $data->data->discussion_id;

				if ( wp_verify_nonce( $data->data->nonce, 'publish-discussion' ) ) {

					wp_update_post( array(
						'ID' => $discussion_id,
						'post_status' => $data->data->status,
					) );

					$json_data['nonce'] = wp_create_nonce( 'publish-discussion' );
					$success = true;

				}

				$json_data['discussion_id'] = $discussion_id;
				$json_data['state'] = $data->data->state;

				break;

			case 'bulk_unpublish':
			case 'bulk_publish':
			case 'bulk_delete':

				$ids = $data->data->ids;

				if ( wp_verify_nonce( $data->data->nonce, 'bulk_action_nonce' ) ) {

					foreach ( $ids as $id ) {

						if ( 'bulk_unpublish' === $action ) {
							if ( CoursePress_Data_Capabilities::can_update_discussion( $id ) ) {
								wp_update_post( array(
									'ID' => $id,
									'post_status' => 'draft',
								) );
							}
						}

						if ( 'bulk_publish' === $action ) {
							if ( CoursePress_Data_Capabilities::can_update_discussion( $id ) ) {
								wp_update_post( array(
									'ID' => $id,
									'post_status' => 'publish',
								) );
							}
						}

						if ( 'bulk_delete' === $action ) {
							if ( CoursePress_Data_Capabilities::can_delete_notification( $id ) ) {
								wp_delete_post( $id );
							}
						}
					}

					$success = true;

				}

				$json_data['ids'] = $ids;

				break;

			case 'unit_items':

				$course_id = $discussion_id = $data->data->course_id;
				$json_data['items'] = array();

				$units = get_posts( array(
					'post_type' => CoursePress_Data_Unit::get_post_type_name(),
					'post_parent' => $course_id,
				) );

				// Sort units
				if ( 'all' !== $course_id && ! empty( $units ) ) {
					foreach ( $units as $unit ) {
						$unit->unit_order = (int) get_post_meta( $unit->ID, 'unit_order', true );
					}
					$units = CoursePress_Helper_Utility::sort_on_object_key( $units, 'unit_order' );

					foreach ( $units as $unit ) {
						$json_data['items'][] = array( 'key' => $unit->ID, 'value' => $unit->post_title );
					}

					$success = true;
				}

				break;

		}

		if ( $success ) {
			wp_send_json_success( $json_data );
		} else {
			wp_send_json_error( $json_data );
		}
	}

	/**
	 * Get courses list if curen user do not have 'manage_options'
	 *
	 * @since 2.0.0
	 *
	 * @return array $courses Array of WP_Post objects
	 */
	private static function get_courses() {
		$user_id = get_current_user_id();
		if ( empty( $user_id ) ) {
			return array();
		}
		/**
		 * check is author
		 */
		/** This filter is documented in include/coursepress/helper/class-setting.php */
		$capability = apply_filters( 'coursepress_capabilities', 'coursepress_create_my_discussion_cap' );
		$is_author = user_can( $user_id, $capability );
		/**
		 * check is instructor
		 */
		/** This filter is documented in include/coursepress/helper/class-setting.php */
		$capability = apply_filters( 'coursepress_capabilities', 'coursepress_create_my_assigned_discussion_cap' );
		$is_instructor = user_can( $user_id, $capability );
		$instructor_courses = array();
		if ( $is_instructor ) {
			$instructor_courses = CoursePress_Data_Instructor::get_assigned_courses_ids( $user_id );
		}
		/**
		 * no rights?
		 */
		if ( ! $is_author && ! $is_instructor ) {
			return array();
		}
		$all_courses = get_posts( 'post_type=' . CoursePress_Data_Course::get_post_type_name() );
		$courses = array();
		foreach ( $all_courses as $course ) {
			/**
			 * add if author
			 */
			if ( $is_author && $user_id == $course->post_author ) {
				$courses[] = $course;
				continue;
			}
			/**
			 * add if assigned
			 */
			if ( $is_instructor && in_array( $course->ID, $instructor_courses ) ) {
				$courses[] = $course;
			}
		}
		return $courses;
	}
}