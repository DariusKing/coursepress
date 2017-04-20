<?php

/************************** CREATE A PACKAGE CLASS *****************************
 *******************************************************************************
 * Create a new list table package that extends the core WP_List_Table class.
 * WP_List_Table contains most of the framework for generating the table, but we
 * need to define and override some methods so that our data can be displayed
 * exactly the way we need it to be.
 *
 * To display this example on a page, you will first need to instantiate the class,
 * then call $yourInstance->prepare_items() to handle any data manipulation, then
 * finally call $yourInstance->display() to render the table to the page.
 *
 * Our theme for this list table is going to be movies.
 */

class CoursePress_Helper_Table_CourseAssessments extends CoursePress_Helper_Table_CourseStudent {

	private $date_format;
	private $search;
	private $student_ids;
	private $the_unit;
	private $paged = 0;
	private $type;

	/** ************************************************************************
	 * REQUIRED. Set up a constructor that references the parent constructor. We
	 * use the parent reference to set some default configs.
	 ***************************************************************************/
	public function __construct() {
		parent::__construct();
		$this->date_format = get_option( 'date_format' );
	}

	public function columns( $content, $column_name, $item_id ) {
		switch ( $column_name ) {
			case 'display_name':
			case 'first_name':
			case 'last_name':
			return sprintf(
				'%s', get_user_option( $column_name, $item_id )
			);
			case 'certificates':
				return $this->column_certificates( $item_id );
			case 'modules':
				return sprintf(
					'<span class="cp-edit-grade" data-student="%d"><i class="dashicons dashicons-list-view"></i>',
					$item_id
				);
			case 'view_all':
				$view_link = add_query_arg(
					array(
						'page' => 'coursepress_assessments',
						'student_id' => $item_id,
						'course_id' => $this->course_id,
						'display' => 'all_answered',
						'view_answer' => '',
					),
					admin_url( 'admin.php' )
				);
				return sprintf(
					'<a href="%s" target="_blank" class="cp-popup"><span class="dashicons dashicons-external"></span></a>',
					esc_url( $view_link )
				);
			case 'grade':
				$course_grade = CoursePress_Data_Student::average_course_responses( $item_id, $this->course_id );
			return sprintf( '<span class="final-grade">%d%%</span>',  $course_grade );
			case 'last_active':
				$student_progress = CoursePress_Data_Student::get_completion_data( $item_id, $this->course_id );
				$last_active = 0;
				$is_completed = CoursePress_Helper_Utility::get_array_val(
					$student_progress,
					'completion/completed'
				);
				$is_completed = cp_is_true( $is_completed );
				if ( ! empty( $student_progress['units'] ) ) {
					$units = (array) $student_progress['units'];
					foreach ( $units as $unit_id => $unit ) {
						if ( ! empty( $units[ $unit_id ]['responses'] ) ) {
							$responses = $units[ $unit_id ]['responses'];
							foreach ( $responses as $module_id => $response ) {
								$last = array_pop( $response );
								if ( ! empty( $last['date'] ) ) {
									$date = CoursePress_Data_Course::strtotime( $last['date'] );
									$last_active = max( (int) $last_active, $date );
								}
							}
						}
					}
					if ( $last_active > 0 ) {
						return date_i18n( $this->date_format, $last_active );
					}
				}
			return '-';
		}
		return $content;
	}


	/** ************************************************************************
	 * REQUIRED! This method dictates the table's columns and titles. This should
	 * return an array where the key is the column slug (and class) and the value
	 * is the column's title text. If you need a checkbox for bulk actions, refer
	 * to the $columns array below.
	 *
	 * The 'cb' column is treated differently than the rest. If including a checkbox
	 * column in your table you must create a column_cb() method. If you don't need
	 * bulk actions or checkboxes, simply leave the 'cb' entry out of your array.
	 *
	 * @see WP_List_Table::::single_row_columns()
	 * @return array An associative array containing column information: 'slugs'=>'Visible Titles'
	 **************************************************************************/
	public function get_columns() {
		$course_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : null;
		$columns = array(
			'username'     => esc_html__( 'Username', 'CP_TD' ),
		    'display_name' => esc_html__( 'Display Name', 'CP_TD' ),
			'last_active'  => esc_html__( 'Last Active', 'CP_TD' ),
			'grade'        => esc_html__( 'Grade', 'CP_TD' ),
			'certificates' => esc_html__( 'Certified', 'CP_TD' ),
			'modules'      => esc_html__( 'Modules', 'CP_TD' ),
			'view_all'     => esc_html__( 'View All', 'CP_TD' ),
		);

		if ( ! CoursePress_Data_Capabilities::can_withdraw_students( $course_id ) ) {
			unset( $columns['actions'] );
		}

		return $columns;
	}

	public function get_hidden_columns() {
		return array();
	}

	/** ************************************************************************
	 * Optional. If you want one or more columns to be sortable (ASC/DESC toggle),
	 * you will need to register it here. This should return an array where the
	 * key is the column that needs to be sortable, and the value is db column to
	 * sort by. Often, the key and value will be the same, but this is not always
	 * the case (as the value is a column name from the database, not the list table).
	 *
	 * This method merely defines which columns should be sortable and makes them
	 * clickable - it does not handle the actual sorting. You still need to detect
	 * the ORDERBY and ORDER querystring variables within prepare_items() and sort
	 * your data accordingly (usually by modifying your query).
	 *
	 * @return array An associative array containing all the columns that should be sortable: 'slugs'=>array('data_values',bool)
	 **************************************************************************/
	public function get_sortable_columns() {
		$c = array(
			'display_name' => array( 'display_name', false ),
			'grade' => array( 'grade', false ),
			'last_active' => array( 'last_active', false ),
			'username' => array( 'login', false ),
		);
		return $c;
	}

	public function student_row_actions( $actions, $item ) {
		$this->students[] = $item->ID;
		$profile_link = CoursePress_Data_Student::get_admin_profile_url( $item->ID );
		$workbook_link = add_query_arg(
			array(
				'post_type' => CoursePress_Data_Course::get_post_type_name(),
				'page' => 'coursepress_assessments',
				'view' => 'profile',
				'student_id' => $item->ID,
				'course_id' => $this->course_id,
			),
			remove_query_arg(
				array(
					'tab',
					'post',
					'action',
				),
				admin_url( 'edit.php' )
			)
		);

		$actions = array(
			'id' => sprintf( '<span>%s</span>', esc_html( sprintf( __( 'ID: %d', 'CP_TD' ), $item->ID ) ) ),
			'profile' => sprintf( '<a href="%s">%s</a>', $profile_link, esc_html__( 'Student Profile', 'CP_TD' ) ),
			'workbook' => sprintf( '<a href="%s">%s</a>', $workbook_link, esc_html__( 'Workbook', 'CP_TD' ) ),
		);

		if ( current_user_can( 'edit_users' ) ) {
			$actions['edit_user_profile'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					add_query_arg(
						array(
							'courses' => 'show',
						),
						get_edit_user_link( $item->ID )
					)
				),
				__( 'Edit User Profile', 'CP_TD' )
			);
		}

		if ( $this->can_withdraw_students ) {
			$actions['trash'] = sprintf(
				'<a href="#" class="withdraw-student" data-id="%s" data-nonce="%s">%s</a>',
				esc_attr( $item->ID ),
				esc_attr( wp_create_nonce( 'withdraw-single-student-'.$item->ID ) ),
				esc_html__( 'Withdraw', 'CP_TD' )
			);
		}
		return $actions;
	}

	/** ************************************************************************
	 * REQUIRED! This is where you prepare your data for display. This method will
	 * usually be used to query the database, sort and filter the data, and generally
	 * get it ready to be displayed. At a minimum, we should set $this->items and
	 * $this->set_pagination_args(), although the following properties and methods
	 * are frequently interacted with here...
	 *
	 * @uses $this->_column_headers
	 * @uses $this->items
	 * @uses $this->get_columns()
	 * @uses $this->get_sortable_columns()
	 * @uses $this->get_pagenum()
	 * @uses $this->set_pagination_args()
	 **************************************************************************/
	public function prepare_items() {

		$results = array();
		if ( ! empty( $this->search ) ) {
			$this->student_ids = CoursePress_Admin_Assessment::search_students( $this->course_id, $this->search );

			if ( ! empty( $this->student_ids ) ) {
				$results = CoursePress_Admin_Assessment::filter_students( $this->course_id, $this->the_unit, $this->type, $this->student_ids );
			}
		} else {
			$results = CoursePress_Admin_Assessment::filter_students( $this->course_id, $this->the_unit, $this->type );
		}

		global $wpdb;

		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$per_page = 20;
		$current_page = $this->paged;

		$offset = ( $current_page - 1 ) * $per_page;

		$this->_column_headers = array( $columns, $hidden, $sortable );

		if ( is_multisite() ) {
			$course_meta_key = $wpdb->prefix . 'enrolled_course_date_' . $this->course_id;
		} else {
			$course_meta_key = 'enrolled_course_date_' . $this->course_id;
		}

		// Could use the Course Model methods here, but lets try stick to one query
		$query_args = array(
			'meta_query' => array(
				array(
					'key' => $course_meta_key,
					'compare' => 'EXISTS',
				),
			),
			'number' => $per_page,
			'offset' => $offset,
			'include' => array_values( $results['students'] ),
		);
		$usersearch = isset( $_REQUEST['s'] ) ? wp_unslash( trim( $_REQUEST['s'] ) ) : '';

		if ( ! empty( $usersearch ) ) {
			$query_args['search'] = '*' . $usersearch . '*';
		}

		if ( isset( $_REQUEST['orderby'] ) ) {
			$query_args['orderby'] = $_REQUEST['orderby'];
			switch ( $_REQUEST['orderby'] ) {
				case 'first_name':
				case 'last_name':
					$query_args['meta_query'] = array(
					'relation' => 'AND',
					array(
						'key' => $_REQUEST['orderby'],
						'compare' => 'EXISTS',
					),
					array(
						'key' => $course_meta_key,
						'compare' => 'EXISTS',
					),
					);
					$query_args['orderby'] = 'meta_value';
					break;
			}
		}
		if ( isset( $_REQUEST['order'] ) ) {
			$query_args['order'] = $_REQUEST['order'];
		}

		/**
		 * fil certificates
		 */
		$certificates = CoursePress_Data_Certificate::get_certificated_students_by_course_id( $this->course_id );

		/**
		 * Certificates
		 */
		if ( ! empty( $certificates ) ) {
			switch ( $this->filter_show ) {
				case 'no':
					$query_args['exclude'] = $certificates;
				break;
				case 'yes':
					$query_args['include'] = $certificates;
				break;
			}
		}

		$users = new WP_User_Query( $query_args );

		foreach ( $users->get_results() as $one ) {
			$one->data->certified = in_array( $one->ID, $certificates )? 'yes' : 'no';
			$this->items[] = $one;
		}

		$total_items = $users->get_total();
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page' => $per_page,
			)
		);

	}

	public function no_items() {
		$course_id = (int) $_GET['id'];

		if ( CoursePress_Data_Capabilities::can_assign_course_student( $course_id ) || CoursePress_Data_Capabilities::can_invite_students( $course_id ) ) {
			esc_html_e( 'There are no students enrolled in this course. Add them below.', 'CP_TD' );
		} else {
			esc_html_e( 'There are no students enrolled in this course.', 'CP_TD' );
		}
	}

	public function set_search( $search ) {
		$this->search = $search;
	}

	public function set_the_unit( $the_unit ) {
		$this->the_unit = $the_unit;
	}

	public function set_paged( $paged ) {
		$this->paged = $paged;
	}

	public function set_type( $type ) {
		$this->type = $type;
	}

	public function set_student_ids( $student_ids ) {
		$this->student_ids = $student_ids;
	}

	/**
	 * empty function!
	 */
	public function extra_tablenav( $which ) {
	}

	protected function get_bulk_actions() {
		return array();
	}


	public function get_pagenum() {
		$pagenum = $this->paged;

		if ( isset( $this->_pagination_args['total_pages'] ) && $pagenum > $this->_pagination_args['total_pages'] ) {
			$pagenum = $this->_pagination_args['total_pages']; }

		return max( 1, $pagenum );
	}


	public function print_column_headers( $with_id = true ) {
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		$current_url = add_query_arg(
			array(
				'post_type' => CoursePress_Data_Course::get_post_type_name(),
				'page' => 'coursepress_assessments',
				'course_id' => $this->course_id,
				'unit' => 'all',
				'type' => 'all',
			),
			admin_url( 'edit.php' )
		);

			$current_url = remove_query_arg( 'paged', $current_url );

		if ( isset( $_GET['orderby'] ) ) {
			$current_orderby = $_GET['orderby'];
		} else {
			$current_orderby = '';
		}

		if ( isset( $_GET['order'] ) && 'desc' === $_GET['order'] ) {
			$current_order = 'desc';
		} else {
			$current_order = 'asc';
		}

		if ( ! empty( $columns['cb'] ) ) {
			static $cb_counter = 1;
			$columns['cb'] = '<label class="screen-reader-text" for="cb-select-all-' . $cb_counter . '">' . __( 'Select All' ) . '</label>'
				. '<input id="cb-select-all-' . $cb_counter . '" type="checkbox" />';
			$cb_counter++;
		}

		foreach ( $columns as $column_key => $column_display_name ) {
			$class = array( 'manage-column', "column-$column_key" );

			if ( in_array( $column_key, $hidden ) ) {
				$class[] = 'hidden';
			}

			if ( 'cb' === $column_key ) {
				$class[] = 'check-column'; } elseif ( in_array( $column_key, array( 'posts', 'comments', 'links' ) ) ) {
				$class[] = 'num'; }

				if ( $column_key === $primary ) {
					$class[] = 'column-primary';
				}

				if ( isset( $sortable[ $column_key ] ) ) {
					list( $orderby, $desc_first ) = $sortable[ $column_key ];

					if ( $current_orderby === $orderby ) {
						$order = 'asc' === $current_order ? 'desc' : 'asc';
						$class[] = 'sorted';
						$class[] = $current_order;
					} else {
						$order = $desc_first ? 'desc' : 'asc';
						$class[] = 'sortable';
						$class[] = $desc_first ? 'asc' : 'desc';
					}

					$column_display_name = '<a href="' . esc_url( add_query_arg( compact( 'orderby', 'order' ), $current_url ) ) . '"><span>' . $column_display_name . '</span><span class="sorting-indicator"></span></a>';
				}

				$tag = ( 'cb' === $column_key ) ? 'td' : 'th';
				$scope = ( 'th' === $tag ) ? 'scope="col"' : '';
				$id = $with_id ? "id='$column_key'" : '';

				if ( ! empty( $class ) ) {
					$class = "class='" . join( ' ', $class ) . "'"; }

				echo "<$tag $scope $id $class>$column_display_name</$tag>";
		}
	}
}
