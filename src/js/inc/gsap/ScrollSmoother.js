/*!
 * ScrollSmoother 3.11.4
 * https://greensock.com
 *
 * @license Copyright 2008-2022, GreenSock. All rights reserved.
 * Subject to the terms at https://greensock.com/standard-license or for
 * Club GreenSock members, the agreement issued with that membership.
 * @author: Jack Doyle, jack@greensock.com
*/
/* eslint-disable */

let gsap, _coreInitted, _win, _doc, _docEl, _body, _root, _toArray, _clamp, ScrollTrigger, _mainInstance, _expo, _getVelocityProp, _inputObserver, _context, _onResizeDelayedCall,
	_windowExists = () => typeof(window) !== "undefined",
	_getGSAP = () => gsap || (_windowExists() && (gsap = window.gsap) && gsap.registerPlugin && gsap),
	_bonusValidated = 1, //<name>ScrollSmoother</name>
	_isViewport = e => !!~_root.indexOf(e),
	_getTime = Date.now,
	_round = value => Math.round(value * 100000) / 100000 || 0,
	_autoDistance = (el, progress) => { // for calculating the distance (and offset) for elements with speed: "auto". Progress is for if it's "above the fold" (negative start position), so we can crop as little as possible.
		let parent = el.parentNode || _docEl,
			b1 = el.getBoundingClientRect(),
			b2 = parent.getBoundingClientRect(),
			gapTop = b2.top - b1.top,
			gapBottom = b2.bottom - b1.bottom,
			change = (Math.abs(gapTop) > Math.abs(gapBottom) ? gapTop : gapBottom) / (1 - progress),
			offset = -change * progress,
			ratio, extraChange;
		if (change > 0) { // if the image starts at the BOTTOM of the container, adjust things so that it shows as much of the image as possible while still covering.
			ratio = b2.height / (_win.innerHeight + b2.height);
			extraChange = ratio === 0.5 ? b2.height * 2 : Math.min(b2.height, -change * ratio / (2 * ratio - 1)) * 2 * (progress || 1);
			offset += progress ? -extraChange * progress : -extraChange / 2; // whatever the offset, we must double that in the opposite direction to compensate.
			change += extraChange;
		}
		return {change, offset};
	},
	_wrap = el => {
		let wrapper = _doc.querySelector(".ScrollSmoother-wrapper"); // some frameworks load multiple times, so one already exists, just use that to avoid duplicates
		if (!wrapper) {
			wrapper = _doc.createElement("div");
			wrapper.classList.add("ScrollSmoother-wrapper");
			el.parentNode.insertBefore(wrapper, el);
			wrapper.appendChild(el);
		}
		return wrapper;
	};

export class ScrollSmoother {

	constructor(vars) {
		_coreInitted || ScrollSmoother.register(gsap) || console.warn("Please gsap.registerPlugin(ScrollSmoother)");
		vars = this.vars = vars || {};

		_mainInstance && _mainInstance.kill();
		_mainInstance = this;
		_context(this);

		let {smoothTouch, onUpdate, onStop, smooth, onFocusIn, normalizeScroll, wholePixels} = vars,
			content, wrapper, height, mainST, effects, sections, intervalID, wrapperCSS, contentCSS, paused, pausedNormalizer, recordedRefreshScroll, recordedRefreshScrub,
			self = this,
			resizeObserver = typeof(ResizeObserver) !== "undefined" && vars.autoResize !== false && new ResizeObserver(() => ScrollTrigger.isRefreshing || _onResizeDelayedCall.restart(true)),
			effectsPrefix = vars.effectsPrefix || "",
			scrollFunc = ScrollTrigger.getScrollFunc(_win),
			smoothDuration = ScrollTrigger.isTouch === 1 ? (smoothTouch === true ? 0.8 : parseFloat(smoothTouch) || 0) : (smooth === 0 || smooth === false) ? 0 : parseFloat(smooth) || 0.8,
			speed = (smoothDuration && +vars.speed) || 1,
			currentY = 0,
			delta = 0,
			startupPhase = 1,
			tracker = _getVelocityProp(0),
			updateVelocity = () => tracker.update(-currentY),
			scroll = {y: 0},
			removeScroll = () => content.style.overflow = "visible",
			isProxyScrolling,
			killScrub = trigger => {
				trigger.update(); // it's possible that it hasn't been synchronized with the actual scroll position yet, like if it's later in the _triggers Array. If it was already updated, it'll skip the processing anyway.
				let scrub = trigger.getTween();
				if (scrub) {
					scrub.pause();
					scrub._time = scrub._dur; // force the playhead to completion without rendering just so that when it resumes, it doesn't jump back in the .resetTo().
					scrub._tTime = scrub._tDur;
				}
				isProxyScrolling = false;
				trigger.animation.progress(trigger.progress, true);
			},
			render = (y, force) => {
				if ((y !== currentY && !paused) || force) {
					wholePixels && (y = Math.round(y));
					if (smoothDuration) {
						content.style.transform = "matrix3d(1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, " + y + ", 0, 1)";
						//content.style.transform = "translateY(" + y + "px)"; // NOTE: when we used matrix3d() or set will-change: transform, it performed noticeably worse on iOS counter-intuitively!
						content._gsap.y = y + "px";
					}
					delta = y - currentY;
					currentY = y;
					ScrollTrigger.isUpdating || ScrollTrigger.update();
				}
			},
			scrollTop = function(value) {
				if (arguments.length) {
					(value < 0) && (value = 0);
					scroll.y = -value; // don't use currentY because we must accurately track the delta variable (in render() method)
					isProxyScrolling = true; // otherwise, if snapping was applied (or anything that attempted to SET the scroll proxy's scroll position), we'd set the scroll here which would then (on the next tick) update the content tween/ScrollTrigger which would try to smoothly animate to that new value, thus the scrub tween would impede the progress. So we use this flag to respond accordingly in the ScrollTrigger's onUpdate and effectively force the scrub to its end immediately.
					paused ? (currentY = -value) : render(-value);
					ScrollTrigger.isRefreshing ? mainST.update() : scrollFunc(value); // during a refresh, we revert all scrollers to 0 and then put them back. We shouldn't force the window to that value too during the refresh.
					return this;
				}
				return -currentY;
			},
			lastFocusElement, // if the user clicks a button that scrolls the page, for example, then unfocuses the window and comes back and activates the window/tab again, it'll want to focus back on that same button element but in that case we should skip it. Only jump there when a new element gets focus, like tabbing for accessibility.
			_onFocusIn = e => { // when the focus changes, make sure that element is on-screen
				wrapper.scrollTop = 0;
				if ((e.target.contains && e.target.contains(wrapper)) || (onFocusIn && onFocusIn(this, e) === false)) {
					return;
				}
				ScrollTrigger.isInViewport(e.target) || (e.target === lastFocusElement) ||  this.scrollTo(e.target, false, "center center");
				lastFocusElement = e.target;
			},
			adjustParallaxPosition = (triggers, createdAfterEffectWasApplied) => {
				let pins, start, dif, markers;
				effects.forEach(st => {
					pins = st.pins;
					markers = st.markers;
					triggers.forEach(trig => {
						if (st.trigger && trig.trigger && st !== trig && (trig.trigger === st.trigger || trig.pinnedContainer === st.trigger || st.trigger.contains(trig.trigger))) {
							start = trig.start;
							dif = (start - st.start - st.offset) / st.ratio - (start - st.start);
							// createdAfterEffectWasApplied && (dif -= (gsap.getProperty(st.trigger, "y") - st.startY) / st.ratio); // the effect applied a y offset, so if the ScrollTrigger was created after that, it'll be based on that position so we must compensate. Later we added code to ScrollTrigger to roll back in this situation anyway, so this isn't necessary. Saving it in case a situation arises where it comes in handy.
							pins.forEach(p => dif -= p.distance / st.ratio - p.distance);
							trig.setPositions(start + dif, trig.end + dif);
							trig.markerStart && markers.push(gsap.quickSetter([trig.markerStart, trig.markerEnd], "y", "px"));
							if (trig.pin && trig.end > 0) {
								dif = trig.end - trig.start;
								pins.push({start: trig.start, end: trig.end, distance: dif, trig: trig});
								st.setPositions(st.start, st.end + dif);
								st.vars.onRefresh(st);
							}
						}
					});
				});
			},
			onRefresh = () => {
				removeScroll();
				requestAnimationFrame(removeScroll);
				if (effects) { // adjust all the effect start/end positions including any pins!
					effects.forEach(st => {
						let start = st.start,
							end = st.auto ? Math.min(ScrollTrigger.maxScroll(st.scroller), st.end) : start + (st.end - start) / st.ratio,
							offset = (end - st.end) / 2; // we split the difference so that it reaches its natural position in the MIDDLE of the viewport
						start -= offset;
						end -= offset;
						st.offset = offset || 0.0001; // we assign at least a tiny value because we check in the onUpdate for .offset being set in order to apply values.
						st.pins.length = 0;
						st.setPositions(Math.min(start, end), Math.max(start, end));
						st.vars.onRefresh(st);
					});
					adjustParallaxPosition(ScrollTrigger.sort());
				}
				tracker.reset();
			},
			addOnRefresh = () => ScrollTrigger.addEventListener("refresh", onRefresh),
			restoreEffects = () => effects && effects.forEach(st => st.vars.onRefresh(st)),
			revertEffects = () => {
				effects && effects.forEach(st => st.vars.onRefreshInit(st));
				return restoreEffects;
			},
			effectValueGetter = (name, value, index, el) => {
				return () => {
					let v = typeof(value) === "function" ? value(index, el) : value;
					v || v === 0 || (v = el.getAttribute("data-" + effectsPrefix + name) || (name === "speed" ? 1 : 0));
					el.setAttribute("data-" + effectsPrefix + name, v);
					return v === "auto" ? v : parseFloat(v);
				};
			},
			createEffect = (el, speed, lag, index, effectsPadding) => {
				effectsPadding = (typeof(effectsPadding) === "function" ? effectsPadding(index, el) : effectsPadding) || 0;
				let getSpeed = effectValueGetter("speed", speed, index, el),
					getLag = effectValueGetter("lag", lag, index, el),
					startY = gsap.getProperty(el, "y"),
					cache = el._gsap,
					ratio, st, autoSpeed, scrub, progressOffset, yOffset,
					initDynamicValues = () => {
						speed = getSpeed();
						lag = getLag();
						ratio = parseFloat(speed) || 1;
						autoSpeed = speed === "auto";
						progressOffset = autoSpeed ? 0 : 0.5;
						scrub && scrub.kill();
						scrub = lag && gsap.to(el, {ease: _expo, overwrite: false, y: "+=0", duration: lag});
						if (st) {
							st.ratio = ratio;
							st.autoSpeed = autoSpeed;
						}
					},
					revert = () => {
						cache.y = startY + "px";
						cache.renderTransform(1);
						initDynamicValues();
					},
					pins = [],
					markers = [],
					change = 0,
					updateChange = self => {
						if (autoSpeed) {
							revert();
							let auto = _autoDistance(el, _clamp(0, 1, -self.start / (self.end - self.start)));
							change = auto.change;
							yOffset = auto.offset;
						} else {
							change = (self.end - self.start) * (1 - ratio);
							yOffset = 0;
						}
						pins.forEach(p => change -= p.distance * (1 - ratio));
						self.vars.onUpdate(self);
						scrub && scrub.progress(1);
					};
				initDynamicValues();
				if (ratio !== 1 || autoSpeed || scrub) {
					st = ScrollTrigger.create({
						trigger: autoSpeed ? el.parentNode : el,
						start: "top bottom+=" + effectsPadding,
						end: "bottom top-=" + effectsPadding,
						scroller: wrapper,
						scrub: true,
						refreshPriority: -999, // must update AFTER any other ScrollTrigger pins
						onRefreshInit: revert,
						onRefresh: updateChange,
						onKill: self => {
							let i = effects.indexOf(self);
							i >= 0 && effects.splice(i, 1);
							revert();
						},
						onUpdate: self => {
							let y = startY + change * (self.progress - progressOffset),
								i = pins.length,
								extraY = 0,
								pin, scrollY, end;
							if (self.offset) { // wait until the effects are adjusted.
								if (i) { // pinning must be handled in a special way because when pinned, slope changes to 1.
									scrollY = -currentY; // -scroll.y;
									end = self.end;
									while (i--) {
										pin = pins[i];
										if (pin.trig.isActive || (scrollY >= pin.start && scrollY <= pin.end)) { // currently pinned so no need to set anything
											if (scrub) {
												pin.trig.progress += pin.trig.direction < 0 ? 0.001 : -0.001; // just to make absolutely sure that it renders (if the progress didn't change, it'll skip)
												pin.trig.update(0, 0, 1);
												scrub.resetTo("y", parseFloat(cache.y), -delta, true);
												startupPhase && scrub.progress(1);
											}
											return;
										}
										(scrollY > pin.end) && (extraY += pin.distance);
										end -= pin.distance;
									}
									y = startY + extraY + change * (((gsap.utils.clamp(self.start, self.end, scrollY) - self.start - extraY) / (end - self.start)) - progressOffset);
								}
								y = _round(y + yOffset);
								markers.length && !autoSpeed && markers.forEach(setter => setter(y - extraY));
								if (scrub) {
									scrub.resetTo("y", y, -delta, true);
									startupPhase && scrub.progress(1);
								} else {
									cache.y = y + "px";
									cache.renderTransform(1);
								}
							}
						}
					});
					updateChange(st);
					gsap.core.getCache(st.trigger).stRevert = revertEffects; // if user calls ScrollSmoother.create() with effects and THEN creates a ScrollTrigger on the same trigger element, the effect would throw off the start/end positions thus we needed a way to revert things when creating a new ScrollTrigger in that scenario, so we use this stRevert property of the GSCache inside ScrollTrigger.
					st.startY = startY;
					st.pins = pins;
					st.markers = markers;
					st.ratio = ratio;
					st.autoSpeed = autoSpeed;
					el.style.willChange = "transform";
				}
				return st;
			};

		addOnRefresh();
		ScrollTrigger.addEventListener("killAll", addOnRefresh);
		gsap.delayedCall(0.5, () => startupPhase = 0);

		this.scrollTop = scrollTop;

		this.scrollTo = (target, smooth, position) => {
			let p = gsap.utils.clamp(0, ScrollTrigger.maxScroll(_win), isNaN(target) ? this.offset(target, position) : +target);
			!smooth ? scrollTop(p) : paused ? gsap.to(this, {duration: smoothDuration, scrollTop: p, overwrite: "auto", ease: _expo}) : scrollFunc(p);
		};

		this.offset = (target, position) => {
			target = _toArray(target)[0];
			let cssText = target.style.cssText, // because if there's an effect applied, we revert(). We need to restore.
				st = ScrollTrigger.create({trigger: target, start: position || "top top"}),
				y;
			effects && adjustParallaxPosition([st], true);
			y = st.start;
			st.kill(false);
			target.style.cssText = cssText;
			gsap.core.getCache(target).uncache = 1;
			return y;
		};

		function refreshHeight() {
			height = content.clientHeight;
			content.style.overflow = "visible"
			_body.style.height = (_win.innerHeight + (height - _win.innerHeight) / speed) + "px";
			return (height - _win.innerHeight);
		}

		this.content = function(element) {
			if (arguments.length) {
				let newContent = _toArray(element || "#smooth-content")[0] || console.warn("ScrollSmoother needs a valid content element.") || _body.children[0];
				if (newContent !== content) {
					content = newContent;
					contentCSS = content.getAttribute("style") || "";
					resizeObserver && resizeObserver.observe(content);
					gsap.set(content, {overflow: "visible", width: "100%", boxSizing: "border-box", y: "+=0"});
					smoothDuration || gsap.set(content, {clearProps: "transform"});
				}
				return this;
			}
			return content;
		}

		this.wrapper = function(element) {
			if (arguments.length) {
				wrapper = _toArray(element || "#smooth-wrapper")[0] || _wrap(content);
				wrapperCSS = wrapper.getAttribute("style") || "";
				refreshHeight();
				gsap.set(wrapper, smoothDuration ? {overflow: "hidden", position: "fixed", height: "100%", width: "100%", top: 0, left: 0, right: 0, bottom: 0} : {overflow: "visible", position: "relative", width: "100%", height: "auto", top: "auto", bottom: "auto", left: "auto", right: "auto"});
				return this;
			}
			return wrapper;
		}

		this.effects = (targets, config) => {
			effects || (effects = []);
			if (!targets) {
				return effects.slice(0);
			}
			targets = _toArray(targets);
			targets.forEach(target => {
				let i = effects.length;
				while (i--) {
					effects[i].trigger === target && effects[i].kill(); // will automatically splice() it from the effects Array in the onKill
				}
			});
			config = config || {};
			let {speed, lag, effectsPadding} = config,
				effectsToAdd = [],
				i, st;
			for (i = 0; i < targets.length; i++) {
				st = createEffect(targets[i], speed, lag, i, effectsPadding);
				st && effectsToAdd.push(st);
			}
			effects.push(...effectsToAdd);
			return effectsToAdd;
		};

		this.sections = (targets, config) => {
			sections || (sections = []);
			if (!targets) {
				return sections.slice(0);
			}
			let newSections = _toArray(targets).map(el => ScrollTrigger.create({
					trigger: el,
					start: "top 120%",
					end: "bottom -20%",
					onToggle: self => {
						el.style.opacity = self.isActive ? "1" : "0";
						el.style.pointerEvents = self.isActive ? "all" : "none";
					}
				})
			);
			config && config.add ? sections.push(...newSections) : (sections = newSections.slice(0));
			return newSections;
		}

		this.content(vars.content);
		this.wrapper(vars.wrapper);
		this.render = y => render(y || y === 0 ? y : currentY);
		this.getVelocity = () => tracker.getVelocity(-currentY);

		ScrollTrigger.scrollerProxy(wrapper, {
			scrollTop: scrollTop,
			scrollHeight: () => refreshHeight() && _body.scrollHeight,
			fixedMarkers: vars.fixedMarkers !== false && !!smoothDuration,
			content: content,
			getBoundingClientRect() {
				return {top: 0, left: 0, width: _win.innerWidth, height: _win.innerHeight};
			}
		});
		ScrollTrigger.defaults({scroller: wrapper});
		let existingScrollTriggers = ScrollTrigger.getAll().filter(st => st.scroller === _win || st.scroller === wrapper);
		existingScrollTriggers.forEach(st => st.revert(true, true)); // in case it's in an environment like React where child components that have ScrollTriggers instantiate BEFORE the parent that does ScrollSmoother.create(...);

		mainST = ScrollTrigger.create({
			animation: gsap.fromTo(scroll, {y: 0}, {
				y: () => -refreshHeight(),
				immediateRender: false,
				ease: "none",
				data: "ScrollSmoother",
				duration: 100, // for added precision
				onUpdate: function() {
					if (this._dur) { // skip when it's the "from" part of the tween (setting the startAt)
						let force = isProxyScrolling;
						if (force) {
							killScrub(mainST);
							scroll.y = currentY;
						}
						render(scroll.y, force);
						updateVelocity();
						onUpdate && !paused && onUpdate(self);
					}
				}
			}),
			onRefreshInit: self => {
				if (effects) {
					let pins = ScrollTrigger.getAll().filter(st => !!st.pin);
					effects.forEach(st => {
						if (!st.vars.pinnedContainer) {
							pins.forEach(pinST => {
								if (pinST.pin.contains(st.trigger)) {
									let v = st.vars;
									v.pinnedContainer = pinST.pin;
									st.vars = null; // otherwise, it'll self.kill(), triggering the onKill()
									st.init(v, st.animation);
								}
							});
						}
					});
				}
				let scrub = self.getTween();
				recordedRefreshScrub = scrub && scrub._end > scrub._dp._time; // don't use scrub.progress() < 1 because we may have called killScrub() recently in which case it'll report progress() as 1 when we were actually in the middle of a scrub. That's why we tap into the _end instead.
				recordedRefreshScroll = currentY;
				scroll.y = 0;
				if (smoothDuration) { // Safari 16 has a major bug - if you set wrapper.scrollTop to 0 (even if it's already 0), it blocks the whole page from scrolling page non-scrollable! See https://bugs.webkit.org/show_bug.cgi?id=245300 and https://codepen.io/GreenSock/pen/YzLZVOz An alternate is to set position to absolute and then back to fixed after setting scrollTop, but that's less performant.
					wrapper.style.pointerEvents = "none"; // Safari 16 has a major bug - if you set wrapper.scrollTop to 0 (even if it's already 0), it makes the entire page non-scrollable! The only workaround I know of is to change to position absolute and then back again. See https://bugs.webkit.org/show_bug.cgi?id=245300 and https://codepen.io/GreenSock/pen/YzLZVOz
					wrapper.scrollTop = 0; // set wrapper.scrollTop to 0 because in some very rare situations, the browser will auto-set that, like if there's a hash in the link or changing focus to an off-screen input
					setTimeout(() => wrapper.style.removeProperty("pointer-events"), 50);
				}
			},
			onRefresh: self => {
				self.animation.invalidate(); // because pinnedContainers may have been found in ScrollTrigger's _refreshAll() that extend the height. Without this, it may prevent the user from being able to scroll all the way down.
				self.setPositions(self.start, refreshHeight() / speed);
				recordedRefreshScrub || killScrub(self);
				scroll.y = -scrollFunc(); // in 3.11.1, we shifted to forcing the scroll position to 0 during the entire refreshAll() in ScrollTrigger and then restored the scroll position AFTER everything had been updated, thus we should always make these adjustments AFTER a full refresh rather than putting it in the onRefresh() of the individual mainST ScrollTrigger which would fire before the scroll position was restored.
				render(scroll.y);
				startupPhase || self.animation.progress(gsap.utils.clamp(0, 1, recordedRefreshScroll / -self.end));
				if (recordedRefreshScrub) { // we need to trigger the scrub to happen again
					self.progress -= 0.001;
					self.update();
				}
			},
			id: "ScrollSmoother",
			scroller: _win,
			invalidateOnRefresh: true,
			start: 0,
			refreshPriority: -9999, // because all other pins, etc. should be calculated first before this figures out the height of the body. BUT this should also update FIRST so that the scroll position on the proxy is up-to-date when all the ScrollTriggers calculate their progress! -9999 is a special number that ScrollTrigger looks for to handle in this way.
			end: () => refreshHeight() / speed,
			onScrubComplete: () => {
				tracker.reset();
				onStop && onStop(this);
			},
			scrub: smoothDuration || true,
		});

		this.smooth = function(value) {
			if (arguments.length) {
				smoothDuration = value || 0;
				speed = (smoothDuration && +vars.speed) || 1;
				mainST.scrubDuration(value);
			}
			return mainST.getTween() ? mainST.getTween().duration() : 0;
		};

		mainST.getTween() && (mainST.getTween().vars.ease = vars.ease || _expo);

		this.scrollTrigger = mainST;

		vars.effects && this.effects(vars.effects === true ? "[data-" + effectsPrefix + "speed], [data-" + effectsPrefix + "lag]" : vars.effects, {effectsPadding: vars.effectsPadding});
		vars.sections && this.sections(vars.sections === true ? "[data-section]" : vars.sections);

		existingScrollTriggers.forEach(st => {
			st.vars.scroller = wrapper;
			st.revert(false, true);
			st.init(st.vars, st.animation);
		});

		this.paused = function(value, allowNestedScroll) {
			if (arguments.length) {
				if (!!paused !== value) {
					if (value) { // pause
						mainST.getTween() && mainST.getTween().pause();
						scrollFunc(-currentY);
						tracker.reset();
						pausedNormalizer = ScrollTrigger.normalizeScroll();
						pausedNormalizer && pausedNormalizer.disable(); // otherwise the normalizer would try to scroll the page on things like wheel events.
						paused = ScrollTrigger.observe({
							preventDefault: true,
							type: "wheel,touch,scroll",
							debounce: false,
							allowClicks: true,
							onChangeY: () => scrollTop(-currentY) // refuse to scroll
						});
						paused.nested = _inputObserver(_docEl, "wheel,touch,scroll", true, allowNestedScroll !== false); // allow nested scrolling, like modals
					} else { // resume
						paused.nested.kill();
						paused.kill();
						paused = 0;
						pausedNormalizer && pausedNormalizer.enable();
						mainST.progress = (-currentY - mainST.start) / (mainST.end - mainST.start);
						killScrub(mainST);
					}
				}
				return this;
			}
			return !!paused;
		};

		this.kill = this.revert = () => {
			this.paused(false);
			killScrub(mainST);
			mainST.kill();
			let triggers = (effects || []).concat(sections || []),
				i = triggers.length;
			while (i--) { // make sure we go backwards because the onKill() will effects.splice(index, 1) and we don't want to skip
				triggers[i].kill();
			}
			ScrollTrigger.scrollerProxy(wrapper);
			ScrollTrigger.removeEventListener("killAll", addOnRefresh);
			ScrollTrigger.removeEventListener("refresh", onRefresh);
			wrapper.style.cssText = wrapperCSS;
			content.style.cssText = contentCSS;
			let defaults = ScrollTrigger.defaults({});
			defaults && defaults.scroller === wrapper && ScrollTrigger.defaults({scroller: _win});
			this.normalizer && ScrollTrigger.normalizeScroll(false);
			clearInterval(intervalID);
			_mainInstance = null;
			resizeObserver && resizeObserver.disconnect();
			_body.style.removeProperty("height");
			_win.removeEventListener("focusin", _onFocusIn);
		}

		this.refresh = (soft, force) => mainST.refresh(soft, force);

		if (normalizeScroll) {
			this.normalizer = ScrollTrigger.normalizeScroll(normalizeScroll === true ? { debounce: true, content: !smoothDuration && content } : normalizeScroll);
		}

		ScrollTrigger.config(vars); // in case user passes in ignoreMobileResize for example
		("overscrollBehavior" in _win.getComputedStyle(_body)) && gsap.set([_body, _docEl], {overscrollBehavior: "none"});
		("scrollBehavior" in _win.getComputedStyle(_body)) && gsap.set([_body, _docEl], {scrollBehavior: "auto"});

		// if the user hits the tab key (or whatever) to shift focus to an element that's off-screen, center that element.
		_win.addEventListener("focusin", _onFocusIn);

		intervalID = setInterval(updateVelocity, 250);

		_doc.readyState === "loading" || requestAnimationFrame(() => ScrollTrigger.refresh());

	}

	get progress() {
		return this.scrollTrigger ? this.scrollTrigger.animation._time / 100 : 0;
	}


	static register(core) {
		if (!_coreInitted) {
			gsap = core || _getGSAP();
			if (_windowExists() && window.document) {
				_win = window;
				_doc = document;
				_docEl = _doc.documentElement;
				_body = _doc.body;
			}
			if (gsap) {
				_toArray = gsap.utils.toArray;
				_clamp = gsap.utils.clamp;
				_expo = gsap.parseEase("expo");
				_context = gsap.core.context || function() {};
				_onResizeDelayedCall = gsap.delayedCall(0.2, () => ScrollTrigger.isRefreshing || (_mainInstance && _mainInstance.refresh())).pause();
				ScrollTrigger = gsap.core.globals().ScrollTrigger;
				gsap.core.globals("ScrollSmoother", ScrollSmoother); // must register the global manually because in Internet Explorer, functions (classes) don't have a "name" property.
			//	gsap.ticker.lagSmoothing(50, 100); // generally people don't want things to jump (honoring smoothness over time is better with smooth scrolling)
				if (_body && ScrollTrigger) {
					_root = [_win, _doc, _docEl, _body];
					_getVelocityProp = ScrollTrigger.core._getVelocityProp;
					_inputObserver = ScrollTrigger.core._inputObserver;
					ScrollSmoother.refresh = ScrollTrigger.refresh;
					_coreInitted = 1;
				}
			}
		}
		return _coreInitted;
	}


}

ScrollSmoother.version = "3.11.4";
ScrollSmoother.create = vars => (_mainInstance && vars && _mainInstance.content() === _toArray(vars.content)[0]) ? _mainInstance : new ScrollSmoother(vars);
ScrollSmoother.get = () => _mainInstance;

_getGSAP() && gsap.registerPlugin(ScrollSmoother);

export { ScrollSmoother as default };