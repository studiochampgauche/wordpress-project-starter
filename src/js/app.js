'use strict';
import gsap from './gsap/index.js';
import gsapCore from './gsap/gsap-core.js';
import ScrollTrigger from './gsap/ScrollTrigger.js';
import ScrollSmoother from './gsap/ScrollSmoother.js';


export default class App{

	onBeforeOnce(){
		console.log('onBeforeOnce');
		this.scroller();
	}

	onOnce(container, namespace){
		console.log('onOnce');
	}


	onLeave(container, namespace, done){

		console.log('is leaving');

		const tl = gsap.timeline({
			onComplete: () => done()
		});

		tl.to(container, .4, {
			opacity: 0
		});


		return tl;
	}

	onAfterLeave(){
		this.gscroll.paused(true);
		this.gscroll.scrollTop(0);
		ScrollTrigger.refresh();
	}

	onEnter(container, namespace){

		console.log('is entering');

		const tl = gsap.timeline();

		tl.fromTo(container, {
			opacity: 0
		}, {
			opacity: 1,
			duration: .4
		});


		return tl;
	}

	onAfterEnter(){
		this.gscroll.paused(false);
	}

	scroller(){

		gsap.registerPlugin(ScrollTrigger, ScrollSmoother);

		this.gscroll = ScrollSmoother.create({
			wrapper: '#pageWrapper',
			content: '#pageContent',
			ignoreMobileResize: true,
			normalizeScroll: true,
			smoothTouch: .1,
			smooth: 1
		});

	}

}

new App();