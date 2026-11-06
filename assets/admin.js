( function ( $ ) {
	var cfg = window.shakewellAuthors;
	var $list = $( '.shakewell-authors__list' );
	var $status = $( '.shakewell-authors__status' );
	var $results = $( '.shakewell-authors__results' );
	var timer;

	function fmt( s ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		return s.replace( /%(\d\$)?[sd]/g, function ( m, n ) {
			return args[ n ? parseInt( n, 10 ) - 1 : 0 ];
		} );
	}

	function button( act, label, name ) {
		return $( '<button type="button" class="button button-small">' )
			.attr( 'data-act', act )
			.attr( 'aria-label', fmt( cfg.i18n[ act ], name ) )
			.text( label );
	}

	function decorate( $li ) {
		var name = $li.find( '.shakewell-authors__name' ).text();
		$li.find( 'button' ).remove();
		$li.append(
			button( 'up', cfg.i18n.upLabel, name ),
			button( 'down', cfg.i18n.dnLabel, name ),
			button( 'remove', cfg.i18n.rmLabel, name )
		);
	}

	function add( item ) {
		if ( $list.find( 'input[value="' + item.ref + '"]' ).length ) {
			return;
		}
		var $li = $( '<li>' );
		$( '<input type="hidden" name="shakewell_authors[]">' ).val( item.ref ).appendTo( $li );
		$( '<span class="shakewell-authors__name">' ).text( item.label ).appendTo( $li );
		$( '<em>' ).text( ' (' + item.kind + ')' ).appendTo( $li );
		$list.append( $li );
		decorate( $li );
		$status.text( fmt( cfg.i18n.added, item.label ) );
	}

	$list.children().each( function () {
		decorate( $( this ) );
	} );
	$list.sortable();

	$list.on( 'click', 'button', function () {
		var $btn = $( this );
		var $li = $btn.closest( 'li' );
		var act = $btn.attr( 'data-act' );
		var name = $li.find( '.shakewell-authors__name' ).text();
		if ( 'remove' === act ) {
			$li.remove();
			$status.text( fmt( cfg.i18n.removed, name ) );
			return;
		}
		if ( 'up' === act && $li.prev().length ) {
			$li.insertBefore( $li.prev() );
		} else if ( 'down' === act && $li.next().length ) {
			$li.insertAfter( $li.next() );
		}
		$btn.trigger( 'focus' );
		$status.text( fmt( cfg.i18n.moved, name, $li.index() + 1, $list.children().length ) );
	} );

	$( '#shakewell-authors-search' )
		.on( 'keydown', function ( e ) {
			// Enter must not submit the post form.
			if ( 13 === e.which ) {
				e.preventDefault();
			}
		} )
		.on( 'input', function () {
			var q = $.trim( this.value );
			clearTimeout( timer );
			timer = setTimeout( function () {
				if ( ! q ) {
					$results.empty();
					return;
				}
				$.post( cfg.ajaxUrl, { action: 'shakewell_authors_search', nonce: cfg.nonce, q: q } ).done( function ( res ) {
					$results.empty();
					var items = res && res.success ? res.data : [];
					if ( ! items.length ) {
						$results.append( $( '<li>' ).text( cfg.i18n.none ) );
					}
					$.each( items, function ( i, item ) {
						var $b = $( '<button type="button" class="button button-small">' )
							.text( item.label + ' (' + item.kind + ')' )
							.attr( 'aria-label', fmt( cfg.i18n.add, item.label + ', ' + item.kind ) )
							.on( 'click', function () {
								add( item );
							} );
						$results.append( $( '<li>' ).append( $b ) );
					} );
				} );
			}, 300 );
		} );
}( jQuery ) );
