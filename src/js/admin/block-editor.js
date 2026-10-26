const { registerBlockType } = wp.blocks;
const { useBlockProps } = wp.blockEditor;
const { createElement } = wp.element;
const { __ } = wp.i18n;

registerBlockType('pickory/product-filter', {
    title: __('Product Filter', 'pickory-product-filter'),
    icon: 'filter',
    category: 'woocommerce',
    edit: () => {
        const blockProps = useBlockProps({
            style: {
                padding: '20px',
                background: '#f7f7f7',
                textAlign: 'center',
                border: '1px dashed #ccc',
            },
        });

        return createElement(
            'div',
            blockProps,
            createElement(
                'p',
                null,
                __('🔍 Pickory Product Filter (renders on frontend)', 'pickory-product-filter')
            )
        );
    },
    save: () => null,
});
