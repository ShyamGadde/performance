const consoleLogPrefix = '[Embed Optimizer]';

/**
 * @typedef {import("../optimization-detective/types.d.ts").ElementMetrics} ElementMetrics
 * @typedef {import("../optimization-detective/types.d.ts").URLMetric} URLMetric
 */

/**
 * Log a message.
 *
 * @param {...*} message
 */
function log( ...message ) {
	// eslint-disable-next-line no-console
	console.log( consoleLogPrefix, ...message );
}

/**
 * Embed element heights.
 *
 * @type {Map<string, DOMRectReadOnly>}
 */
const loadedElementContentRects = new Map();

/**
 * Initialize.
 *
 * @param {Object}  args         Args.
 * @param {boolean} args.isDebug Whether to show debug messages.
 */
export async function initialize( { isDebug } ) {
	const embedWrappers =
		/** @type NodeListOf<HTMLDivElement> */ document.querySelectorAll(
			'.wp-block-embed > .wp-block-embed__wrapper[data-od-xpath]'
		);

	for ( const embedWrapper of embedWrappers ) {
		monitorEmbedWrapperForResizes( embedWrapper );
	}

	if ( isDebug ) {
		log( 'Loaded embed content rects:', loadedElementContentRects );
	}
}

/**
 * Initialize.
 *
 * @param {Object}    args           Args.
 * @param {boolean}   args.isDebug   Whether to show debug messages.
 * @param {URLMetric} args.urlMetric Pending URL metric.
 */
export async function finalize( { urlMetric, isDebug } ) {
	if ( isDebug ) {
		log( 'URL metric to be sent:', urlMetric );
	}

	for ( const element of urlMetric.elements ) {
		if ( loadedElementContentRects.has( element.xpath ) ) {
			if ( isDebug ) {
				log(
					`Overriding boundingClientRect for ${ element.xpath }:`,
					element.boundingClientRect,
					'=>',
					loadedElementContentRects.get( element.xpath )
				);
			}
			// TODO: Maybe element.boundingClientRect should rather be element.initialBoundingClientRect and the schema is extended by Embed Optimizer to add an element.finalBoundingClientRect (same goes for intersectionRect and intersectionRatio).
			element.boundingClientRect = loadedElementContentRects.get(
				element.xpath
			);
		}
	}
}

/**
 * Monitors embed wrapper for resizes.
 *
 * @param {HTMLDivElement} embedWrapper Embed wrapper DIV.
 */
function monitorEmbedWrapperForResizes( embedWrapper ) {
	if ( ! ( 'odXpath' in embedWrapper.dataset ) ) {
		throw new Error( 'Embed wrapper missing data-od-xpath attribute.' );
	}
	const xpath = embedWrapper.dataset.odXpath;
	const observer = new ResizeObserver( ( entries ) => {
		const [ entry ] = entries;
		loadedElementContentRects.set( xpath, entry.contentRect );
	} );
	observer.observe( embedWrapper, { box: 'content-box' } );
}
