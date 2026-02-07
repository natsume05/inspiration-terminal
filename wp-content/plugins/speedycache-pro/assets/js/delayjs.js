(function(){
let speedycache_js_events = ['mouseover','click','keydown','wheel','touchmove','touchstart'],
speedycache_djs_timeout = setTimeout(speedycache_delay_event, 10000);

speedycache_js_events.forEach((event) => {
	window.addEventListener(event, speedycache_delay_event, {passive: true});
});

function speedycache_delay_event(){
	
	speedycache_js_events.forEach((event) => {
		window.removeEventListener(event, speedycache_delay_event, {passive: true});
	});
	
	document.querySelectorAll('script[type="speedycache/javascript"]').forEach(async e => {
		await new Promise(resolve => speedycache_load_js(e, resolve));
	});
	
	if(speedycache_djs_timeout != null){
		clearTimeout(speedycache_djs_timeout);
		speedycache_djs_timeout = null;
	}
}

function speedycache_load_js(js, resolve){
	let async_js = document.createElement('script');
	
	let attr = js.getAttributeNames();
	attr.forEach(name => {
		if(name === 'type'){
			return;
		}

		async_js.setAttribute(name == 'data-src' ? 'src' : name, js.getAttribute(name));
	});

	async_js.setAttribute('type', 'text/javascript');
	
	if(!js.hasAttribute('data-src')){
		async_js.text = js.text;
	}

	async_js.addEventListener('load', resolve);

	js.after(async_js);
	js.remove();

}
})();
