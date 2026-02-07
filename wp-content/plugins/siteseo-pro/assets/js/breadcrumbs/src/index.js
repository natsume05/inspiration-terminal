import { registerBlockType } from '@wordpress/blocks';
import './style.css';

import Edit from './edit';
import metadata from './block.json';

function siteseo_breadcrumb_icon(){
	return (<svg xmlns="http://www.w3.org/2000/svg" width="664.82" height="495.38" viewBox="0 0 43.214 32.2"><path d="M1 32.1c-.6 0-1-.4-1-1V1c0-.6.4-1 1-1h8.1c.4 0 .9.2 1.2.5l11.6 14.6c.4.5.4 1.3 0 1.9L10.2 31.6c-.3.4-.8.6-1.3.6zM42.914 15.071l-11.7-14.5c-.5-.6-1.4-.8-2.1-.2l-2.3 1.9c-.7.5-.8 1.5-.2 2.1l9.5 11.7-9.5 11.8c-.5.6-.4 1.6.2 2.1l2.3 1.9c.7.5 1.5.4 2.1-.2l11.7-14.6c.4-.8.4-1.5 0-2z"/><path d="m32.407 15.071-11.7-14.5c-.5-.6-1.4-.8-2.1-.2l-2.3 1.9c-.7.5-.8 1.5-.2 2.1l9.5 11.7-9.5 11.8c-.5.6-.4 1.6.2 2.1l2.3 1.9c.7.5 1.5.4 2.1-.2l11.7-14.6c.4-.8.4-1.5 0-2z"/></svg>
	);
}


registerBlockType( metadata.name, {
	edit: Edit,
	icon : siteseo_breadcrumb_icon
} );
