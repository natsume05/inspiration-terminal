import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { Notice } from '@wordpress/components';

export default function Edit({attributes}) {
	let [title] = useSelect((select) => {
		return [
			select('core/editor').getEditedPostAttribute("title") || __('Example title', 'siteseo'),
		]
	}, []);

	let crumbs = [];

	if(attributes.prefix){
		crumbs.push({
			'title' : attributes.prefix,
			'url' : false
		});
	}

	if(!attributes.hideHome){
		crumbs.push({
			'title' : attributes.homeLabel ? attributes.homeLabel : __('Home'),
			'url' : true
		});
	}

	crumbs.push({
		'title' : title,
		'url' : false
	});

	return (
		<>
		<Notice status="warning" isDismissible={false} politeness="polite">
			<p>{__('This is just a preview, to see the final result, please view this page as a normal user', 'siteseo')}</p>
		</Notice>
		<div { ...useBlockProps() }>
			<ul className="siteseo-breadcrumbs">
				{crumbs.map((crub, i) => {
					return (<><li key={crub.url}>{crub.url ? (<a href="#" title={crub.title}>{crub.title}</a>) : (crub.title)}</li>
						<div className="siteseo-breadcrumbs-seperator"><span>{(crumbs.length-1) != i ? attributes.seperator : ''}</span></div>
						</>
					);
				})}
			</ul>
		</div>
		</>
	);
}
