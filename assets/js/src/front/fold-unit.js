/* global CoursePress */

(function() {
    'use strict';

    CoursePress.Define( 'FoldCourseUnits', function( $, doc ) {

      var foldMenu,foldButton,target;

      foldButton = $( '<span class="fold"></span>' ).insertBefore( $('.unit') );

      foldMenu = function() {

        target = $(this).next().find('.module-tree');

        $(this).toggleClass('folded');

        if ($(this).is( '.folded' )) {
          target.slideUp();
        } else {
          target.slideDown();
        }

      };

      $(doc).on( 'click', '.fold', foldMenu );

    });
})();