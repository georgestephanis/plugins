/**
 * Format seconds as HH:MM:SS.
 *
 * @param {number} seconds Total seconds to format.
 * @return {string} Zero-padded HH:MM:SS string.
 */
export function formatTime( seconds ) {
	const h = Math.floor( seconds / 3600 );
	const m = Math.floor( ( seconds % 3600 ) / 60 );
	const s = seconds % 60;
	return [ h, m, s ]
		.map( ( n ) => String( n ).padStart( 2, '0' ) )
		.join( ':' );
}

/**
 * Create a ticking timer.
 *
 * Calls onTick immediately with the initial offset, then once per second.
 * Calling start() on a running timer resets it. onStop is called when stop()
 * is invoked (including the implicit stop inside start()).
 *
 * @param {Function} onTick   Receives elapsed seconds; called on start and each tick.
 * @param {Function} [onStop] Called when the timer is stopped (optional).
 * @return {{ start: Function, stop: Function }} Timer object with start and stop methods.
 */
export function createTimer( onTick, onStop = () => {} ) {
	let interval = null;
	let startTs = 0;

	function stop() {
		if ( interval ) {
			clearInterval( interval );
			interval = null;
		}
		onStop();
	}

	function start( offset = 0 ) {
		stop();
		startTs = Math.floor( Date.now() / 1000 ) - offset;
		onTick( offset );
		interval = setInterval( () => {
			onTick( Math.floor( Date.now() / 1000 ) - startTs );
		}, 1000 );
	}

	return { start, stop };
}
