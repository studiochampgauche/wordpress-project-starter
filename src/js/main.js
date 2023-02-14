'use strict';
import App from './app.js';
import * as Barba from './barba/barba.js';

class Main{
	constructor(){
		window.scrollTo(0,0);

		if ('scrollRestoration' in history)
			history.scrollRestoration = 'manual';

		const isIE11 = !!window.MSInputMethodContext && !!document.documentMode;
		const isEdge = /Edge/.test(navigator.userAgent);

		if(isIE11 || isEdge)
			setTimeout(function(){ window.scrollTo(0, 0); }, 300);
		

		const app = new App();

		window.onload = app.onBeforeOnce();

		barba.init({
			debug: false,
			cacheIgnore: true,
			prefetchIgnore: true,
			preventRunning: true,
			transitions: [
				{
					once: ({next}) => app.onOnce(next.container, next.namespace),
					leave({current}){

						const done = this.async();

						app.onLeave(current.container, current.namespace, done);

					},
					afterLeave: () => app.onAfterLeave(),
					enter: ({next}) => app.onEnter(next.container, next.namespace),
					afterEnter: () => app.onAfterEnter()
				}
			]
		});

	}
}

new Main();