(function (blocks, element, editor) {
    const el = element.createElement;
    const { InspectorControls } = editor;
    const { PanelBody, ToggleControl } = wp.components;

    blocks.registerBlockType('siteseo-pro/local-business', {
        title: 'Local Business',
        icon: 'store',
        category: 'siteseo',
        attributes: {
            displayOnHomepage: {
                type: 'boolean',
                default: true
            }
        },
        edit: function (props) {
            return [
                el('div', { className: props.className },
                    el('div', { className: 'local-business-preview' },
                        el('h3', null, 'Local Business'),
                        el('div', { dangerouslySetInnerHTML: { __html: siteseoProLocalBusiness.previewData } })
                    )
                ),
                el(InspectorControls, null,
                    el(PanelBody, { title: 'Settings' },
                        el(ToggleControl, {
                            label: 'Display on Homepage Only',
                            checked: props.attributes.displayOnHomepage,
                            onChange: (newVal) => props.setAttributes({ displayOnHomepage: newVal })
                        })
                    )
                )
            ];
        },
        save: function () {
            return null; // Use dynamic rendering
        }
    });
})(window.wp.blocks, window.wp.element, window.wp.blockEditor);